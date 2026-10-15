<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Eye, Printer } from 'lucide-vue-next'
import DataTable from '@/components/ui/DataTable.vue'
import ThermalPrintModal from './ThermalPrintModal.vue'
import CashTxnDetailModal from './CashTxnDetailModal.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import { formatDateOnly } from '@/lib/date'
import type { CashTxnHistoryRow, CashTxnHistoryType } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Cash Txn History table — GET /cash-txn-details/in-history|out-history
|--------------------------------------------------------------------------
| `userId` is optional as of PR #46 (2026-09-22, backend commit 60b3816):
| the backend now scopes by `head_id` alone (Out = sender is the head,
| In = recipient is the head) when `cash_user_id` is omitted, matching the
| legacy screen's "pick a Head, see everything" behaviour. Passing a
| `userId` narrows to that one counterparty, same as before
| (PENDING_WORK.md #36, fixed). `refreshKey` is a plain prop the parent
| bumps after a save so the just-recorded entry shows up without a manual
| reload.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  direction: 'in' | 'out'
  headId: number
  userId: number | null
  fromDate?: string
  toDate?: string
  refreshKey?: number
}>()

const PER_PAGE = 10

const rows = ref<CashTxnHistoryRow[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const loadError = ref('')

const columns: DataTableColumn<CashTxnHistoryRow>[] = [
  { key: 'txn_id', label: 'ID' },
  { key: 'added_at', label: 'Date' },
  { key: 'type', label: 'Type' },
  { key: 'category', label: 'Category' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'payment_method', label: 'Method' },
  { key: 'remarks', label: 'Remarks' },
]

const printTxnId = ref<number | null>(null)
const detailTxnId = ref<number | null>(null)

// Coloured by cash direction, the same way Cash Management's quick-action
// buttons are: oxblood for types where cash leaves the head (the Pay
// group), verdigris where it reaches the head (the Receive group). The
// label tells the types apart; the colour says which way the money went.
const OUT_BADGE = 'bg-outflow-50 text-outflow-700'
const IN_BADGE = 'bg-inflow-50 text-inflow-700'
const typeMeta: Record<CashTxnHistoryType, { label: string; class: string }> = {
  EXPENSE: { label: 'Pay', class: OUT_BADGE },
  INCOME: { label: 'Receive', class: IN_BADGE },
  AUTO_ENTRY: { label: 'Auto Entry', class: IN_BADGE },
  PURCHASE_GOLD: { label: 'Purchase Gold', class: OUT_BADGE },
  SALE_GOLD: { label: 'Sale Gold', class: IN_BADGE },
  CASH_TO_GOLD: { label: 'Cash To Gold', class: IN_BADGE },
  GOLD_TO_CASH: { label: 'Gold To Cash', class: OUT_BADGE },
  INTERNAL_TRANSFER: { label: 'Internal', class: 'bg-slate-100 text-slate-500' },
  OUT_CASH_CONVERTER: { label: 'Out Cash Converter', class: OUT_BADGE },
  IN_CASH_CONVERTER: { label: 'In Cash Converter', class: IN_BADGE },
}

function typeBadge(type: CashTxnHistoryType) {
  return typeMeta[type] ?? { label: type, class: 'bg-slate-100 text-slate-600' }
}

function methodLabel(row: CashTxnHistoryRow) {
  if (row.payment_method === 'BANK') return row.bank?.bank_name ?? 'Bank'
  return 'Cash on hand'
}

const rangeLabel = computed(() => {
  if (total.value === 0) return '0 of 0'
  const from = (page.value - 1) * PER_PAGE + 1
  const to = Math.min(page.value * PER_PAGE, total.value)
  return `${from}–${to} of ${total.value}`
})

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    const query = {
      head_id: props.headId,
      cash_user_id: props.userId ?? undefined,
      from_date: props.fromDate || undefined,
      to_date: props.toDate || undefined,
      per_page: PER_PAGE,
      page: page.value,
    }
    const result =
      props.direction === 'in'
        ? await cashTxnDetailsApi.getInHistory(query)
        : await cashTxnDetailsApi.getOutHistory(query)
    rows.value = result.data
    lastPage.value = result.meta.last_page
    total.value = result.meta.total
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load history.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)

watch(
  () => [props.headId, props.userId, props.fromDate, props.toDate, props.refreshKey],
  () => {
    page.value = 1
    load()
  },
)
watch(page, load)

function prevPage() {
  if (page.value > 1) page.value -= 1
}
function nextPage() {
  if (page.value < lastPage.value) page.value += 1
}
</script>

<template>
  <div>
    <p
      v-if="loadError"
      class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <div v-if="isLoading" class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
      Loading…
    </div>

    <template v-else>
      <!-- size="sm": these always render two-up on Cash Management. -->
      <DataTable :columns="columns" :rows="rows" size="sm" empty-message="No entries recorded yet.">
        <template #added_at="{ value }">
          <span class="font-figure whitespace-nowrap tabular-nums">{{ formatDateOnly(value as string | null) }}</span>
        </template>
        <template #type="{ value }">
          <span
            class="inline-flex rounded px-1.5 py-0.5 font-figure text-[11px] leading-4 font-semibold tracking-[0.06em] whitespace-nowrap uppercase"
            :class="typeBadge(value as CashTxnHistoryType).class"
          >
            {{ typeBadge(value as CashTxnHistoryType).label }}
          </span>
        </template>
        <template #category="{ row }">{{ row.category?.category_name ?? '—' }}</template>
        <template #amount="{ value }">
          <span class="font-figure text-[15px] font-semibold tabular-nums text-ink-900">
            {{ Number(value).toLocaleString() }}
          </span>
        </template>
        <template #payment_method="{ row }">
          <span class="whitespace-nowrap">{{ methodLabel(row) }}</span>
        </template>
        <template #remarks="{ value }">{{ value || '—' }}</template>
        <template #txn_id="{ value }">
          <div class="flex items-center gap-1">
            <span class="font-figure tabular-nums text-slate-500">{{ value }}</span>
            <div class="flex items-center gap-0.5">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink-900"
                aria-label="View transaction detail"
                title="View transaction detail"
                @click="detailTxnId = value as number"
              >
                <Eye class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink-900"
                aria-label="Print receipt"
                title="Print receipt"
                @click="printTxnId = value as number"
              >
                <Printer class="h-4 w-4" />
              </button>
            </div>
          </div>
        </template>
      </DataTable>

      <ThermalPrintModal
        v-if="printTxnId !== null"
        :txn-id="printTxnId"
        @close="printTxnId = null"
      />

      <CashTxnDetailModal
        v-if="detailTxnId !== null"
        :txn-id="detailTxnId"
        @close="detailTxnId = null"
      />

      <div v-if="total > 0" class="mt-2 flex items-center justify-between text-xs text-slate-500">
        <span class="font-figure tabular-nums">{{ rangeLabel }}</span>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="page === 1"
            aria-label="Previous page"
            @click="prevPage"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
          <span>Page {{ page }} of {{ lastPage }}</span>
          <button
            type="button"
            class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="page === lastPage"
            aria-label="Next page"
            @click="nextPage"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
