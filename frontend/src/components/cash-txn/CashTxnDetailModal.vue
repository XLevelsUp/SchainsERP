<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { CashTxnDetailRow } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash transaction detail — GET /cash-txn-details/{id}
|--------------------------------------------------------------------------
| Read-only on purpose. The same apiResource registration also exposes
| update and destroy, and neither is safe to call: both were written against
| the pre-PR-#13 schema and read columns (given_by, souce_type, bank_id,
| opening_account_balance) that cash_txn_details no longer has, so destroy
| deletes the row while silently skipping its balance restore. See the
| header comment in lib/cashTxnDetailsApi.ts. No edit or delete action is
| offered here until the backend catches those two up with the schema.
|
| What this adds over the history row that opens it: the four opening and
| four closing balance snapshots, the bank entry date, and the receipt
| attachments — none of which CashTxnHistoryResource returns.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ txnId: number }>()
const emit = defineEmits<{ close: [] }>()

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
function money(value: string | number | null): string {
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
  return d.bank?.bank_name ? `Bank — ${d.bank.bank_name}` : 'Bank'
})

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
