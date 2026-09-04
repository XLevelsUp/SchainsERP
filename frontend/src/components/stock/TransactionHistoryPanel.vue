<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Printer } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { stockHistoryApi } from '@/lib/stockHistoryApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Item, StockHistoryRow, StockHistoryTotals } from '@/types'

/*
|--------------------------------------------------------------------------
| Transaction History panel — GET /stock-details/history
|--------------------------------------------------------------------------
| Legacy "Stock Details" screen's top-right paginated history table, scoped
| to the picked user (or the logged-in head when none is picked): Type/Item/
| date-range filters, a grand-totals row (across the full filtered set, not
| just the current page — the backend computes this before paginating), then
| the row-level list.
|
| The per-row Print icon has no dedicated backend endpoint (unlike the
| single-transaction thermal print for cash entries) — it triggers a plain
| browser print for now, same stand-in as HeadStockSummaryPanel's print
| button, until/unless a real print endpoint is requested.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; employeeId?: number | null }>()

const auth = useAuthStore()

const PER_PAGE = 10

const typeFilter = ref<'IN' | 'OUT' | null>(null)
const itemFilter = ref<number | null>(null)
const fromDate = ref('')
const toDate = ref('')
const page = ref(1)

const rows = ref<StockHistoryRow[]>([])
const totals = ref<StockHistoryTotals>({ grams: 0, purity: 0, pcs: 0 })
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(false)
const loadError = ref('')

const typeOptions = [
  { value: 'OUT' as const, label: 'Out' },
  { value: 'IN' as const, label: 'In' },
]

const itemOptions = computed(() =>
  props.items.map((item) => ({ value: item.item_id, label: item.item_name })),
)

const typeMeta: Record<'IN' | 'OUT', string> = {
  IN: 'bg-emerald-50 text-emerald-700',
  OUT: 'bg-red-50 text-red-700',
}

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

const rangeLabel = computed(() => {
  if (total.value === 0) return '0 of 0'
  const from = (page.value - 1) * PER_PAGE + 1
  const to = Math.min(page.value * PER_PAGE, total.value)
  return `${from}–${to} of ${total.value}`
})

async function load() {
  if (!auth.user) return
  isLoading.value = true
  loadError.value = ''
  try {
    const result = await stockHistoryApi.list({
      // Report from the picked user's perspective when one is selected — the
      // backend derives each row's IN/OUT direction and counterparty name from
      // head_id, so this has to follow the picker, not the session. Falls back
      // to the logged-in head when nothing is picked. employee_id stays for an
      // unchanged payload shape; it duplicates head_id and is a no-op filter.
      head_id: props.employeeId ?? auth.user.user_id,
      employee_id: props.employeeId ?? undefined,
      type: typeFilter.value ?? undefined,
      item_id: itemFilter.value ?? undefined,
      from_date: fromDate.value || undefined,
      to_date: toDate.value || undefined,
      page_size: PER_PAGE,
      page: page.value,
    })
    rows.value = result.transactions.data
    lastPage.value = result.transactions.last_page
    total.value = result.transactions.total
    totals.value = result.totals
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load transaction history.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
watch([typeFilter, itemFilter, fromDate, toDate, () => props.employeeId], () => {
  page.value = 1
  load()
})
watch(page, load)

function prevPage() {
  if (page.value > 1) page.value -= 1
}
function nextPage() {
  if (page.value < lastPage.value) page.value += 1
}
function printPanel() {
  window.print()
}

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-900">Transaction History</h2>
      <button
        type="button"
        class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        aria-label="Print transaction history"
        @click="printPanel"
      >
        <Printer class="h-4 w-4" />
      </button>
    </div>

    <div class="grid gap-2 border-b border-slate-200 px-4 py-3 sm:grid-cols-4">
      <BaseSelect
        v-model="typeFilter"
        :options="typeOptions"
        placeholder="Select Type…"
        size="sm"
      />
      <BaseSelect
        v-model="itemFilter"
        :options="itemOptions"
        placeholder="Select Item…"
        size="sm"
      />
      <BaseInput v-model="fromDate" type="date" size="sm" placeholder="From date" />
      <BaseInput v-model="toDate" type="date" size="sm" placeholder="To date" />
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>

    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">Loading…</div>

    <template v-else>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr class="border-b border-slate-200 font-semibold text-slate-900">
              <td colspan="4" class="px-4 py-2">Total</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.grams) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.pcs) }}</td>
              <td class="px-3 py-2"></td>
              <td class="px-3 py-2"></td>
              <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(totals.purity) }}</td>
              <td colspan="3" class="px-4 py-2"></td>
            </tr>
            <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <th scope="col" class="px-4 py-2 text-left">Sno</th>
              <th scope="col" class="px-3 py-2 text-left">ID</th>
              <th scope="col" class="px-3 py-2 text-left">Item</th>
              <th scope="col" class="px-3 py-2 text-left">Type</th>
              <th scope="col" class="px-3 py-2 text-right">Grams</th>
              <th scope="col" class="px-3 py-2 text-right">Pcs</th>
              <th scope="col" class="px-3 py-2 text-right">Touch</th>
              <th scope="col" class="px-3 py-2 text-right">Wastage</th>
              <th scope="col" class="px-4 py-2 text-right">Purity</th>
              <th scope="col" class="px-3 py-2 text-left">User</th>
              <th scope="col" class="px-4 py-2 text-left">Remarks</th>
              <th scope="col" class="px-4 py-2 text-center">Print</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="rows.length === 0">
              <td colspan="12" class="px-4 py-8 text-center text-slate-500">
                No transactions recorded yet.
              </td>
            </tr>
            <tr v-for="(row, index) in rows" :key="row.id" class="hover:bg-slate-50">
              <td class="px-4 py-2 text-slate-500">{{ (page - 1) * PER_PAGE + index + 1 }}</td>
              <td class="px-3 py-2 text-slate-500">{{ row.id }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.item_name || '—' }}</td>
              <td class="px-3 py-2">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="typeMeta[row.stock_type]">
                  {{ row.stock_type === 'IN' ? 'In' : 'Out' }}
                </span>
              </td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.grams) }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.pcs) }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.touch) }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.wastage) }}</td>
              <td class="px-4 py-2 text-right tabular-nums font-semibold text-slate-900">
                {{ formatNumber(row.purity) }}
              </td>
              <td class="px-3 py-2 text-slate-700">{{ row.user || '—' }}</td>
              <td class="px-4 py-2 text-slate-500">{{ row.remarks || '—' }}</td>
              <td class="px-4 py-2 text-center">
                <button
                  type="button"
                  class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                  :aria-label="`Print transaction ${row.id}`"
                  @click="printPanel"
                >
                  <Printer class="h-4 w-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="total > 0" class="flex items-center justify-between border-t border-slate-200 px-4 py-2 text-xs text-slate-500">
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
  </BaseCard>
</template>
