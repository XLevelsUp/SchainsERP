<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Pencil } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { cashCategoriesApi } from '@/lib/cashCategoriesApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { goldEntriesApi } from '@/lib/goldEntriesApi'
import { numericWastageEntriesApi } from '@/lib/numericWastageEntriesApi'
import type {
  BankDetail,
  CashCategory,
  CashTxnDetailRow,
  CashTxnEditPayload,
  CashTxnSourceType,
  GoldCorrectionRequest,
  NumericWastageCorrectionRequest,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Cash transaction detail — GET /cash-txn-details/{id}
|--------------------------------------------------------------------------
| What this adds over the history row that opens it: the opening and closing
| balance snapshots (only your own side unless you're a head), the bank entry
| date, and the receipt attachments.
|
| Editing (BACKEND_TODO §17 phases 1–2, since 2026-11-12): heads can edit Cash
| In / Cash Out and Cash Auto Entry entries here — amount, payment method and
| bank (not for auto entries, which are always cash), category,
| remarks and date. The backend corrects both parties' balances, the bank
| and every later entry's snapshots in one go. The parties can't change.
| Other entry types show why they can't be edited yet
| (edit_blocked_reason). There is no delete, by decision.
|
| Entries that belong to a gold entry (cash_to_gold_id) are corrected
| through it instead (§17 phase 3): "Correct gold entry" loads the gold
| entry's original figures and asks the parent to open the matching gold
| modal in correct mode (emit 'correct-gold').
|
| A numeric wastage line's cash entry (numeric_wastage) is corrected
| through that line the same way: "Correct numeric wastage" loads the line
| and asks the parent to open NumericWastageCorrectionModal (emit
| 'correct-numeric-wastage').
|--------------------------------------------------------------------------
*/

const props = defineProps<{ txnId: number }>()
const emit = defineEmits<{
  close: []
  updated: []
  'correct-gold': [request: GoldCorrectionRequest]
  'correct-numeric-wastage': [request: NumericWastageCorrectionRequest]
}>()

const auth = useAuthStore()
const toast = useToastStore()

const detail = ref<CashTxnDetailRow | null>(null)
const isLoading = ref(true)
const loadError = ref('')

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    detail.value = await cashTxnDetailsApi.getOne(props.txnId)
  } catch (err) {
    loadError.value =
      err instanceof ApiError ? err.message : 'Failed to load this transaction.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

// decimal:2 casts arrive as strings; balance_after_txn and the remainder
// fields can be null.
function money(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  return Number.isFinite(n)
    ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : String(value)
}

function userLabel(ref_: CashTxnDetailRow['givenByUser']): string {
  if (!ref_) return '—'
  return ref_.name ? `${ref_.name} (${ref_.user_id})` : String(ref_.user_id)
}

// bank_entry_date is a plain date column, not a timestamp — formatDateTime
// would imply a precision it doesn't have.
const bankEntryDate = computed(() => detail.value?.bank_entry_date || '—')

const methodLabel = computed(() => {
  const d = detail.value
  if (!d) return '—'
  if (d.payment_method !== 'BANK') return 'Cash on hand'
  const name = d.bank?.account_name ?? d.bank?.bank_name
  return name ? `Bank — ${name}` : 'Bank'
})

// ---------------------------------------------------------------------------
// Edit (heads only)
// ---------------------------------------------------------------------------

const canEdit = computed(() => auth.isHead && detail.value?.edit_blocked_reason === null)
// Cash Auto Entry only ever takes cash from the sender, so its payment method
// is fixed (BACKEND_TODO §17 phase 2).
const isAutoEntry = computed(() => detail.value?.type === 'AUTO_ENTRY')
const editBlockedReason = computed(() =>
  auth.isHead && detail.value?.edit_blocked_reason ? detail.value.edit_blocked_reason : null,
)

// ---------------------------------------------------------------------------
// Correct the gold entry this cash entry belongs to (heads only)
// ---------------------------------------------------------------------------

const goldEntryId = computed(() => detail.value?.cash_to_gold_id ?? null)
const canCorrectGold = computed(() => auth.isHead && goldEntryId.value !== null)
const isOpeningCorrection = ref(false)
const goldCorrectionBlocked = ref('')

function partyName(userId: number): string {
  const d = detail.value
  const ref_ = [d?.givenByUser, d?.givenToUser].find((u) => u?.user_id === userId)
  return ref_?.name ?? `#${userId}`
}

async function openGoldCorrection() {
  const id = goldEntryId.value
  if (id === null || isOpeningCorrection.value) return
  isOpeningCorrection.value = true
  goldCorrectionBlocked.value = ''
  try {
    const entry = await goldEntriesApi.get(id)
    if (entry.correct_blocked_reason || !entry.kind || !entry.input) {
      goldCorrectionBlocked.value = entry.correct_blocked_reason ?? 'This gold entry can\'t be corrected.'
      return
    }
    emit('correct-gold', {
      goldEntryId: id,
      kind: entry.kind,
      input: entry.input,
      headName: partyName(entry.input.head_id),
      customerName: partyName(entry.input.customer_id),
    })
  } catch (err) {
    goldCorrectionBlocked.value =
      err instanceof ApiError ? err.message : 'Failed to load the gold entry.'
  } finally {
    isOpeningCorrection.value = false
  }
}

// ---------------------------------------------------------------------------
// Correct the numeric wastage line this cash entry belongs to (heads only)
// ---------------------------------------------------------------------------

const numericWastage = computed(() => detail.value?.numeric_wastage ?? null)
const canCorrectNumericWastage = computed(() => auth.isHead && numericWastage.value !== null)
const numericWastageLabel = computed(() => {
  const ref_ = numericWastage.value
  return ref_ ? `Correct numeric wastage ${ref_.direction} #${ref_.id}` : ''
})
const numericWastageBlocked = ref('')

async function openNumericWastageCorrection() {
  const ref_ = numericWastage.value
  if (ref_ === null || isOpeningCorrection.value) return
  isOpeningCorrection.value = true
  numericWastageBlocked.value = ''
  try {
    const entry = await numericWastageEntriesApi.get(ref_.direction, ref_.id)
    if (entry.correct_blocked_reason || !entry.input) {
      numericWastageBlocked.value =
        entry.correct_blocked_reason ?? 'This numeric wastage entry can\'t be corrected.'
      return
    }
    emit('correct-numeric-wastage', {
      direction: ref_.direction,
      id: ref_.id,
      input: entry.input,
      givenByName: partyName(entry.input.given_by),
      givenToName: partyName(entry.input.given_to),
    })
  } catch (err) {
    numericWastageBlocked.value =
      err instanceof ApiError ? err.message : 'Failed to load the numeric wastage entry.'
  } finally {
    isOpeningCorrection.value = false
  }
}

const isEditing = ref(false)
const isSaving = ref(false)
const editError = ref('')
const categories = ref<CashCategory[]>([])
const banks = ref<BankDetail[]>([])
const editForm = reactive({
  amount: '',
  payment_method: 'CASH_ON_HAND' as CashTxnSourceType,
  bank_account_id: null as number | null,
  category_id: null as number | null,
  remarks: '',
  created_at: '',
})
let originalCreatedAt = ''

const methodOptions: { value: CashTxnSourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]
const bankOptions = computed(() =>
  banks.value
    .filter((b) => b.is_active || b.bank_id === editForm.bank_account_id)
    .map((b) => ({ value: b.bank_id, label: b.account_name })),
)
const categoryOptions = computed(() =>
  categories.value
    .filter((c) => c.is_active || c.category_id === editForm.category_id)
    .map((c) => ({ value: c.category_id, label: c.category_name })),
)

// created_at arrives as a UTC instant; the datetime input wants local time.
function toLocalInput(value: string | null): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`
}

async function startEdit() {
  const d = detail.value
  if (!d) return
  editForm.amount = String(Number(d.amount))
  editForm.payment_method = d.payment_method
  editForm.bank_account_id = d.bank_account_id
  editForm.category_id = d.category_id
  editForm.remarks = d.remarks ?? ''
  editForm.created_at = toLocalInput(d.created_at)
  originalCreatedAt = editForm.created_at
  editError.value = ''
  isEditing.value = true
  if (categories.value.length === 0 || banks.value.length === 0) {
    try {
      const [categoryList, bankList] = await Promise.all([cashCategoriesApi.list(), bankDetailsApi.list()])
      categories.value = categoryList
      banks.value = bankList
    } catch {
      // The pickers stay empty; amount, method and remarks can still be saved.
    }
  }
}

function validateEdit(): string | null {
  const raw = editForm.amount.trim()
  const amount = Number(raw)
  if (!raw || !Number.isFinite(amount) || amount < 0.01) return 'Enter an amount of at least 0.01.'
  if (!/^\d+(\.\d{1,2})?$/.test(raw)) return 'Amount can have at most 2 decimal places.'
  if (editForm.payment_method === 'BANK' && editForm.bank_account_id === null) {
    return 'Choose a bank account for a bank payment.'
  }
  if (editForm.remarks.length > 5000) return 'Remarks must be 5000 characters or fewer.'
  return null
}

async function saveEdit() {
  const d = detail.value
  if (isSaving.value || !d) return
  const invalid = validateEdit()
  if (invalid) {
    editError.value = invalid
    return
  }

  const payload: CashTxnEditPayload = {
    amount: Number(editForm.amount.trim()),
    category_id: editForm.category_id,
    remarks: editForm.remarks.trim() || null,
  }
  if (!isAutoEntry.value) {
    payload.payment_method = editForm.payment_method
    payload.bank_account_id = editForm.payment_method === 'BANK' ? editForm.bank_account_id : null
  }
  // Only send the date when it was changed, so a save never shifts it.
  if (editForm.created_at && editForm.created_at !== originalCreatedAt) {
    payload.created_at = `${editForm.created_at.replace('T', ' ')}:00`
  }

  isSaving.value = true
  editError.value = ''
  try {
    detail.value = await cashTxnDetailsApi.update(d.txn_id, payload)
    isEditing.value = false
    toast.show(`Transaction #${d.txn_id} updated.`, 'success')
    emit('updated')
  } catch (err) {
    if (err instanceof ApiError) {
      editError.value = err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message
    } else {
      editError.value = 'Failed to update this transaction.'
    }
  } finally {
    isSaving.value = false
  }
}

const balanceRows = computed(() => {
  const d = detail.value
  if (!d) return []
  return [
    {
      party: 'Sender',
      who: userLabel(d.givenByUser),
      openingCash: d.sender_opening_cash,
      openingRtgs: d.sender_opening_rtgs,
      closingCash: d.sender_closing_cash,
      closingRtgs: d.sender_closing_rtgs,
    },
    {
      party: 'Recipient',
      who: userLabel(d.givenToUser),
      openingCash: d.recipient_opening_cash,
      openingRtgs: d.recipient_opening_rtgs,
      closingCash: d.recipient_closing_cash,
      closingRtgs: d.recipient_closing_rtgs,
    },
  ]
})
</script>

<template>
  <BaseModal
    :title="`Transaction #${txnId}`"
    max-width="max-w-3xl"
    @close="emit('close')"
  >
    <div v-if="isLoading" class="py-10 text-center text-sm text-slate-500">Loading…</div>

    <p
      v-else-if="loadError"
      class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <template v-else-if="detail">
      <div v-if="canEdit && !isEditing" class="flex justify-end">
        <BaseButton variant="secondary" :icon="Pencil" @click="startEdit">
          Edit
        </BaseButton>
      </div>
      <div v-else-if="canCorrectGold" class="flex flex-col items-end gap-1">
        <BaseButton
          variant="secondary"
          :icon="Pencil"
          :disabled="isOpeningCorrection"
          @click="openGoldCorrection"
        >
          {{ isOpeningCorrection ? 'Loading…' : `Correct gold entry #${goldEntryId}` }}
        </BaseButton>
        <p v-if="goldCorrectionBlocked" class="text-xs text-slate-500">
          {{ goldCorrectionBlocked }}
        </p>
      </div>
      <div v-else-if="canCorrectNumericWastage" class="flex flex-col items-end gap-1">
        <BaseButton
          variant="secondary"
          :icon="Pencil"
          :disabled="isOpeningCorrection"
          @click="openNumericWastageCorrection"
        >
          {{ isOpeningCorrection ? 'Loading…' : numericWastageLabel }}
        </BaseButton>
        <p v-if="numericWastageBlocked" class="text-xs text-slate-500">
          {{ numericWastageBlocked }}
        </p>
      </div>
      <p v-else-if="editBlockedReason" class="text-xs text-slate-500">
        Editing not available: {{ editBlockedReason }}
      </p>

      <form
        v-if="isEditing"
        class="space-y-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4"
        @submit.prevent="saveEdit"
      >
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <BaseInput
            v-model="editForm.amount"
            label="Amount (₹)"
            type="number"
            step="0.01"
            min="0.01"
            size="sm"
            required
          />
          <BaseSelect
            v-if="!isAutoEntry"
            v-model="editForm.payment_method"
            label="Payment method"
            size="sm"
            :options="methodOptions"
          />
          <BaseSelect
            v-if="!isAutoEntry && editForm.payment_method === 'BANK'"
            v-model="editForm.bank_account_id"
            label="Bank account"
            size="sm"
            placeholder="Select a bank…"
            :options="bankOptions"
          />
          <BaseSelect
            v-model="editForm.category_id"
            label="Category"
            size="sm"
            placeholder="No category"
            :options="categoryOptions"
          />
          <BaseInput
            v-model="editForm.created_at"
            label="Date & time"
            type="datetime-local"
            size="sm"
          />
        </div>
        <BaseTextarea v-model="editForm.remarks" label="Remarks" :rows="2" />

        <p v-if="isAutoEntry" class="text-xs text-slate-500">
          A Cash Auto Entry only takes cash from the sender and is always cash on hand. Saving
          updates the sender's cash balance and the opening and closing balances of their later
          entries. The parties can't be changed.
        </p>
        <p v-else class="text-xs text-slate-500">
          Saving updates both parties' balances, the bank, and the opening and closing balances
          of every later entry for these two parties. The parties themselves can't be changed.
        </p>
        <p v-if="editError" class="text-sm text-red-600">{{ editError }}</p>

        <div class="flex justify-end gap-2">
          <BaseButton
            variant="secondary"
            type="button"
            :disabled="isSaving"
            @click="isEditing = false"
          >
            Cancel
          </BaseButton>
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : 'Save changes' }}
          </BaseButton>
        </div>
      </form>

      <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
        <div>
          <dt class="text-xs text-slate-500">Type</dt>
          <dd class="text-sm font-medium text-slate-900">{{ detail.type }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Amount</dt>
          <dd class="text-sm font-semibold tabular-nums text-slate-900">
            {{ money(detail.amount) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Method</dt>
          <dd class="text-sm text-slate-900">{{ methodLabel }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">From</dt>
          <dd class="text-sm text-slate-900">{{ userLabel(detail.givenByUser) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">To</dt>
          <dd class="text-sm text-slate-900">{{ userLabel(detail.givenToUser) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Balance after</dt>
          <dd class="text-sm tabular-nums text-slate-900">
            {{ money(detail.balance_after_txn) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Recorded</dt>
          <dd class="text-sm text-slate-900">{{ formatDateTime(detail.created_at) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Bank entry date</dt>
          <dd class="text-sm text-slate-900">{{ bankEntryDate }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Entered by</dt>
          <dd class="text-sm tabular-nums text-slate-900">{{ detail.added_by }}</dd>
        </div>
      </dl>

      <div>
        <h3 class="mb-2 text-sm font-semibold text-slate-900">Balances around this entry</h3>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th
                  class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase"
                >
                  Party
                </th>
                <th
                  class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase"
                >
                  Opening cash
                </th>
                <th
                  class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase"
                >
                  Closing cash
                </th>
                <th
                  class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase"
                >
                  Opening RTGS
                </th>
                <th
                  class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase"
                >
                  Closing RTGS
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="row in balanceRows" :key="row.party">
                <td class="px-3 py-2 text-sm text-slate-700">
                  <span class="font-medium text-slate-900">{{ row.party }}</span>
                  <span class="block text-xs text-slate-500">{{ row.who }}</span>
                </td>
                <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">
                  {{ money(row.openingCash) }}
                </td>
                <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">
                  {{ money(row.closingCash) }}
                </td>
                <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">
                  {{ money(row.openingRtgs) }}
                </td>
                <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">
                  {{ money(row.closingRtgs) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <h3 class="mb-1 text-sm font-semibold text-slate-900">Remarks</h3>
          <p class="text-sm whitespace-pre-line text-slate-600">{{ detail.remarks || '—' }}</p>
        </div>
        <div>
          <h3 class="mb-1 text-sm font-semibold text-slate-900">Reminder</h3>
          <p class="text-sm text-slate-600">
            {{ detail.remainder || '—' }}
            <span v-if="detail.remainder_at" class="block text-xs text-slate-500">
              {{ formatDateTime(detail.remainder_at) }}
            </span>
          </p>
        </div>
      </div>

      <div>
        <h3 class="mb-1 text-sm font-semibold text-slate-900">
          Receipts
          <span class="font-normal text-slate-500">({{ detail.images.length }})</span>
        </h3>
        <p v-if="detail.images.length === 0" class="text-sm text-slate-500">
          No receipt images attached.
        </p>
        <template v-else>
          <ul class="space-y-1">
            <li
              v-for="image in detail.images"
              :key="image.image_id"
              class="font-mono text-xs break-all text-slate-600"
            >
              {{ image.image_path }}
            </li>
          </ul>
          <!--
            Paths, not previews. show() decorates each image with an
            `image_full_url` built from `$image->image_url`, but the column is
            `image_path` — so that URL always resolves to the bare storage root
            and every preview would render broken. Building a URL here instead
            would mean inventing a storage contract the backend hasn't stated.
          -->
          <p class="mt-1 text-xs text-slate-500">
            Stored paths shown rather than previews — the API's image URL field is built from a
            column that doesn't exist, so it can't be used yet.
          </p>
        </template>
      </div>
    </template>
  </BaseModal>
</template>
