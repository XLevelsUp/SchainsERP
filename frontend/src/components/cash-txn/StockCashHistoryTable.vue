<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import DataTable from '@/components/ui/DataTable.vue'
import { stockCashHistoryApi } from '@/lib/stockCashHistoryApi'
import { ApiError } from '@/lib/api'
import type { StockCashHistoryRow, StockCashHistoryType } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Stock Cash History table — GET /stock-details/cash-transaction-history
|--------------------------------------------------------------------------
| The gold/stock side of the same head/user pair CashTxnHistoryTable shows
| the cash side of — Purchase Gold, Sale Gold, Cash To Gold, and Gold To
| Cash all write stock_details rows here in addition to cash_txn_details
| rows, so this is what makes those show up anywhere in Cash Management.
| `refreshKey` follows the same convention as CashTxnHistoryTable.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  headId: number
  userId: number
  refreshKey?: number
}>()

const PER_PAGE = 10

const rows = ref<StockCashHistoryRow[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const loadError = ref('')

const columns: DataTableColumn<StockCashHistoryRow>[] = [
  { key: 'stock_type', label: 'Type' },
  { key: 'item', label: 'Item' },
  { key: 'grams', label: 'Grams' },
  { key: 'no_of_pcs', label: 'Pcs' },
  { key: 'touch', label: 'Touch' },
  { key: 'wastage', label: 'Wastage' },
  { key: 'purity', label: 'Purity' },
  { key: 'user', label: 'With' },
  { key: 'remarks', label: 'Remarks' },
]

const typeMeta: Record<StockCashHistoryType, { label: string; class: string }> = {
  IN: { label: 'In', class: 'bg-emerald-50 text-emerald-700' },
  OUT: { label: 'Out', class: 'bg-red-50 text-red-700' },
}

function typeBadge(type: StockCashHistoryType) {
  return typeMeta[type] ?? { label: type, class: 'bg-slate-100 text-slate-600' }
}

function formatNumber(value: number | string) {
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 4 }) : String(value)
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
    const result = await stockCashHistoryApi.list({
      head_id: props.headId,
      cash_user_id: props.userId,
      per_page: PER_PAGE,
      page: page.value,
    })
    rows.value = result.data
    lastPage.value = result.meta.last_page
    total.value = result.meta.total
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load stock history.'
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
      <DataTable :columns="columns" :rows="rows" empty-message="No stock movements recorded yet.">
        <template #stock_type="{ value }">
          <span
            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
            :class="typeBadge(value as StockCashHistoryType).class"
          >
            {{ typeBadge(value as StockCashHistoryType).label }}
          </span>
        </template>
        <template #item="{ value }">{{ value || '—' }}</template>
        <template #grams="{ value }">
          <span class="tabular-nums">{{ formatNumber(value as number) }}</span>
        </template>
        <template #no_of_pcs="{ value }">
          <span class="tabular-nums">{{ formatNumber(value as number) }}</span>
        </template>
        <template #touch="{ value }">
          <span class="tabular-nums">{{ formatNumber(value as number) }}</span>
        </template>
        <template #wastage="{ value }">
          <span class="tabular-nums">{{ formatNumber(value as number) }}</span>
        </template>
        <template #purity="{ value }">
          <span class="font-semibold tabular-nums text-slate-900">{{ formatNumber(value as number) }}</span>
        </template>
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
