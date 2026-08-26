<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Trash } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import PartyBalanceCards from './PartyBalanceCards.vue'
import { cashAutoEntryApi } from '@/lib/cashAutoEntryApi'
import { cashCategoriesApi } from '@/lib/cashCategoriesApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { CashAutoEntryTransactionInput, CashCategory } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash Auto Entry — batch of expense/income rows against one head/user pair
|--------------------------------------------------------------------------
| POST /cash-txn-details/auto-entry (PR #16). type is hardcoded to
| AUTO_ENTRY here to match the button that opens this modal — the backend
| also accepts EXPENSE/INCOME, but those reverse who the money moves from
| (see CashAutoEntryService), which isn't what "Auto Entry" meant in the
| legacy button grid.
|
| Only the head's cash_balance is actually debited server-side — the chosen
| cash user's row is never credited (CashAutoEntryService replicates legacy
| Expense behavior: "money leaving the system"). That's a backend
| characteristic, not something the frontend can change. What the frontend
| controls is which party's balance the preview panel shows, and this entry
| is conceptually "for" the chosen cash user (the employee/party these
| expenses are being tracked against), not the head and not whoever's
| logged in — so the preview shows their balance, not the head's.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  headId: number
  headName: string
  cashUserId: number
  cashUserName: string
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toastStore = useToastStore()

// Preview is a single party — the chosen cash user (see the direction note
// above), not the two-party comparison the Pay/Receive modal shows.
const isLoadingBalance = ref(true)
const cashUserBalance = reactive({ cash: 0, rtgs: 0 })

async function loadCashUserBalance() {
  isLoadingBalance.value = true
  try {
    const { user } = await userDetailsApi.get(props.cashUserId)
    cashUserBalance.cash = user.rak_cash_balance
    cashUserBalance.rtgs = user.rak_rtgs_balance
  } catch {
    // Non-fatal — the form still works without the balance preview.
  } finally {
    isLoadingBalance.value = false
  }
}
onMounted(loadCashUserBalance)

const categories = ref<CashCategory[]>([])
async function loadCategories() {
  try {
    categories.value = (await cashCategoriesApi.list()).filter((c) => c.is_active)
  } catch {
    // Non-fatal — rows can still be saved with category left unset.
  }
}
onMounted(loadCategories)

const categoryOptions = computed(() =>
  categories.value.map((c) => ({
    value: c.category_id,
    label: c.category_type ? `${c.category_name} (${c.category_type})` : c.category_name,
  })),
)

function makeEmptyRow(): CashAutoEntryTransactionInput {
  return { amount: null, category_id: null, remarks: '', added_at: '', images: [] }
}

const rows = reactive<CashAutoEntryTransactionInput[]>([makeEmptyRow()])
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)

function addRow() {
  rows.push(makeEmptyRow())
}
function removeRow(index: number) {
  rows.splice(index, 1)
}

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

const total = computed(() => rows.reduce((sum, r) => sum + (r.amount ?? 0), 0))

// CB preview — every row is a cash expense, so the running total comes
// straight off the chosen cash user's Hand Cash; RTGS is never touched by
// this endpoint. Null (shown as "—") until at least one row has an amount.
const cashUserCbCash = computed(() =>
  total.value > 0 ? cashUserBalance.cash - total.value : null,
)

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (rows.length === 0) {
    formError.value = 'Add at least one row.'
  }

  rows.forEach((row, index) => {
    if (row.amount === null || row.amount < 0.01) {
      fieldErrors[`transactions.${index}.amount`] = `Row ${index + 1}: amount is required (min 0.01).`
    }
    const oversized = row.images.find((f) => f.size > 5 * 1024 * 1024)
    if (oversized) {
      fieldErrors[`transactions.${index}.images`] =
        `Row ${index + 1}: ${oversized.name} is over 5 MB.`
    }
  })

  const firstError = formError.value || Object.values(fieldErrors)[0]
  if (firstError) {
    formError.value = firstError
    toastStore.show(firstError, 'error')
    return false
  }
  return true
}

async function handleSubmit() {
  if (!validate()) return

  isSaving.value = true
  try {
    const actingUserId = auth.user?.user_id ?? 1
    const result = await cashAutoEntryApi.create(
      {
        type: 'AUTO_ENTRY',
        head_id: props.headId,
        cash_user_id: props.cashUserId,
        transactions: rows.map((r) => ({ ...r })),
      },
      actingUserId,
    )
    toastStore.show(`${result.length} auto entry row(s) saved successfully.`, 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      if (err.errors) {
        for (const [key, messages] of Object.entries(err.errors)) {
          fieldErrors[key] = messages[0]
        }
        toastStore.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toastStore.show(err.message, 'error')
      }
    } else {
      formError.value = 'Failed to save auto entry rows.'
      toastStore.show('Failed to save auto entry rows.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="Auto Entry" :badge="cashUserName" badge-class="bg-amber-500" max-width="max-w-7xl" @close="emit('close')">
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <div>
      <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Balances</p>
      <PartyBalanceCards
        :left-label="cashUserName"
        :left-ob-cash="cashUserBalance.cash"
        :left-ob-rtgs="cashUserBalance.rtgs"
        :left-cb-cash="cashUserCbCash"
        :left-cb-rtgs="cashUserBalance.rtgs"
        :is-loading="isLoadingBalance"
        active-column="cash"
      />
    </div>

    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      Every row moves Hand Cash only — there's no bank/RTGS option for auto entries.
    </p>

    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <div
        v-for="(row, index) in rows"
        :key="index"
        class="flex flex-col gap-2 rounded-lg border border-slate-200 p-2.5"
      >
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Row {{ index + 1 }}</h3>
          <button
            type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
            aria-label="Remove row"
            :disabled="rows.length === 1"
            @click="removeRow(index)"
          >
            <Trash class="h-4 w-4" />
          </button>
        </div>

        <div class="grid gap-2 sm:grid-cols-4">
          <BaseInput
            :id="`amount_${index}`"
            :model-value="row.amount === null ? '' : String(row.amount)"
            label="Amount"
            type="number"
            step="0.001"
            required
            size="sm"
            :error="fieldErrors[`transactions.${index}.amount`]"
            @update:model-value="(v) => (row.amount = v === '' ? null : Number(v))"
          />
          <BaseSelect
            :id="`category_${index}`"
            :model-value="row.category_id"
            label="Category"
            size="sm"
            placeholder="No category"
            :options="categoryOptions"
            @update:model-value="(v) => (row.category_id = v as number | null)"
          />
          <BaseInput
            :id="`added_at_${index}`"
            v-model="row.added_at"
            label="Date"
            type="date"
            size="sm"
          />
          <BaseInput
            :id="`remarks_${index}`"
            v-model="row.remarks"
            label="Remarks"
            size="sm"
          />
        </div>

        <p v-if="row.added_at" class="text-[11px] leading-none text-amber-700">
          Date not saved — the backend accepts this field but doesn't store it yet.
        </p>

        <div>
          <BaseFileInput
            :id="`images_${index}`"
            v-model="row.images"
            label="Receipt images (optional)"
            hint="JPG, PNG, or WEBP, up to 5 MB each."
            compact
          />
          <p v-if="fieldErrors[`transactions.${index}.images`]" class="mt-2 text-sm text-red-600">
            {{ fieldErrors[`transactions.${index}.images`] }}
          </p>
        </div>
      </div>

      <BaseButton variant="secondary" type="button" :icon="Plus" @click="addRow">
        Add row
      </BaseButton>

      <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
        <span>{{ rows.length }} row(s)</span>
        <span>Total: {{ total.toFixed(2) }}</span>
      </div>

      <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save all rows' }}
        </BaseButton>
        <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
