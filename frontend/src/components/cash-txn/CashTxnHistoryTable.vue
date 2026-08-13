<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import DataTable from '@/components/ui/DataTable.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { CashTxnHistoryRow, CashTxnHistoryType } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Cash Txn History table — GET /cash-txn-details/in-history|out-history
|--------------------------------------------------------------------------
| Scoped to one head/user pair, same as the quick-action modals above it
| in CashManagementView. `refreshKey` is a plain prop the parent bumps
| after a save so the just-recorded entry shows up without a manual
| reload.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  direction: 'in' | 'out'
  headId: number
  userId: number
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
  { key: 'created_at', label: 'Date' },
  { key: 'type', label: 'Type' },
  { key: 'category_id', label: 'Category' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'remarks', label: 'Remarks' },
]

// Mirrors the badge colors already used on the modals that create each
// type, so the history reads as one system with the quick-action buttons
// above it rather than inventing a new palette.
const typeMeta: Record<CashTxnHistoryType, { label: string; class: string }> = {
  EXPENSE: { label: 'Pay', class: 'bg-red-50 text-red-700' },
  INCOME: { label: 'Receive', class: 'bg-emerald-50 text-emerald-700' },
  AUTO_ENTRY: { label: 'Auto Entry', class: 'bg-amber-50 text-amber-700' },
  PURCHASE_GOLD: { label: 'Purchase Gold', class: 'bg-amber-50 text-amber-700' },
  SALE_GOLD: { label: 'Sale Gold', class: 'bg-purple-50 text-purple-700' },
  CASH_TO_GOLD: { label: 'Cash To Gold', class: 'bg-red-50 text-red-700' },
  GOLD_TO_CASH: { label: 'Gold To Cash', class: 'bg-blue-50 text-blue-700' },
  INTERNAL_TRANSFER: { label: 'Internal', class: 'bg-slate-100 text-slate-500' },
  OUT_CASH_CONVERTER: { label: 'Out Cash Converter', class: 'bg-amber-50 text-amber-800' },
  IN_CASH_CONVERTER: { label: 'In Cash Converter', class: 'bg-purple-50 text-purple-800' },
}

function typeBadge(type: CashTxnHistoryType) {
  return typeMeta[type] ?? { label: type, class: 'bg-slate-100 text-slate-600' }
}

function methodLabel(row: CashTxnHistoryRow) {
  if (row.payment_method === 'BANK') return row.bank?.account_name ?? 'Bank'
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
    const query = { head_id: props.headId, cash_user_id: props.userId, per_page: PER_PAGE, page: page.value }
    const result =
      props.direction === 'in'
        ? await cashTxnDetailsApi.getInHistory(query)
        : await cashTxnDetailsApi.getOutHistory(query)
    rows.value = result.data
    lastPage.value = result.last_page
    total.value = result.total
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load history.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)

watch(
  () => [props.headId, props.userId, props.refreshKey],
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
      <DataTable :columns="columns" :rows="rows" empty-message="No entries recorded yet.">
        <template #created_at="{ value }">{{ formatDateTime(value as string) }}</template>
        <template #type="{ value }">
          <span
            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
            :class="typeBadge(value as CashTxnHistoryType).class"
          >
            {{ typeBadge(value as CashTxnHistoryType).label }}
          </span>
        </template>
        <template #category_id="{ row }">{{ row.category?.category_name ?? '—' }}</template>
        <template #amount="{ value }">
          <span class="font-semibold tabular-nums text-slate-900">
            {{ Number(value).toLocaleString() }}
          </span>
        </template>
        <template #payment_method="{ row }">{{ methodLabel(row) }}</template>
        <template #remarks="{ value }">{{ value || '—' }}</template>
      </DataTable>

      <div v-if="total > 0" class="mt-2 flex items-center justify-between text-xs text-slate-500">
        <span>{{ rangeLabel }}</span>
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
