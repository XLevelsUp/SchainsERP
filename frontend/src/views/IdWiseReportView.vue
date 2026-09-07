<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Printer } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { stockReportsApi } from '@/lib/stockReportsApi'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import type { IdWiseReportResult, IdWiseTransaction } from '@/types'

/*
|--------------------------------------------------------------------------
| ID Wise Report — GET /stock/reports/id-wise?stock_id= (PR #33)
|--------------------------------------------------------------------------
| Given any stock row's id, traces it back to the parent lot and lists
| every child transaction drawn from that lot (rows whose stock_in_id
| points at it), alongside a received/consumed/balance reconciliation.
| Mirrors the legacy "Stock ID Wise details" screen as its own menu item.
|
| The queried row itself is not repeated inside `transactions` — that array
| is child drawdowns only (StockDetails::where('stock_in_id', lot_id)). A
| freshly-created lot with nothing drawn from it yet legitimately returns
| an empty list; the Lot Details panel above still shows its own numbers,
| so the screen isn't blank. Flagged to backend as a possible follow-up if
| exact legacy parity (always at least one row) is wanted.
|
| The endpoint 404s for an unknown stock_id and 422s for a non-numeric one
| — both arrive as ApiError.message and are shown inline, same as every
| other report screen's error handling.
|--------------------------------------------------------------------------
*/

const stockIdInput = ref('')
const report = ref<IdWiseReportResult | null>(null)
const hasSearched = ref(false)
const isLoading = ref(false)
const loadError = ref('')

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'item_name', label: 'Item' },
  { key: 'type', label: 'Type' },
  { key: 'entry_type', label: 'Entry Type' },
  { key: 'stock_type', label: 'Stock Type' },
  { key: 'grams', label: 'Grams' },
  { key: 'touch', label: 'Touch' },
  { key: 'wastage', label: 'Wastage' },
  { key: 'purity', label: 'Purity' },
  { key: 'given_by', label: 'Given By' },
  { key: 'given_to', label: 'Given To' },
  { key: 'remarks', label: 'Remarks' },
  { key: 'added_at', label: 'Added At' },
  { key: 'print', label: 'Print' },
] as const

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

const totals = computed(() => {
  const rows: IdWiseTransaction[] = report.value?.transactions ?? []
  return rows.reduce(
    (acc, row) => ({
      grams: acc.grams + row.grams,
      wastage: acc.wastage + row.wastage,
      purity: acc.purity + row.purity,
    }),
    { grams: 0, wastage: 0, purity: 0 },
  )
})

// Auto-search debounce — waits for a pause in typing (roughly enough to
// enter a 3-digit id) rather than firing on every keystroke. Manual submit
// (Enter / Search button) bypasses the wait and searches immediately.
const AUTO_SEARCH_DELAY_MS = 400
let autoSearchTimer: ReturnType<typeof setTimeout> | undefined

function parseStockId(): number | null {
  const value = stockIdInput.value.trim()
  if (!value) return null
  const id = Number(value)
  return Number.isInteger(id) ? id : null
}

async function runSearch(stockId: number) {
  if (isLoading.value) return

  isLoading.value = true
  loadError.value = ''
  try {
    report.value = await stockReportsApi.getIdWise(stockId)
    hasSearched.value = true
  } catch (err) {
    report.value = null
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    isLoading.value = false
  }
}

function submitSearch() {
  if (autoSearchTimer) clearTimeout(autoSearchTimer)
  const stockId = parseStockId()
  if (stockId === null) {
    loadError.value = 'Enter a valid stock ID.'
    return
  }
  runSearch(stockId)
}

watch(stockIdInput, () => {
  if (autoSearchTimer) clearTimeout(autoSearchTimer)

  const stockId = parseStockId()
  if (stockId === null) {
    // Empty/incomplete input — clear any stale result quietly, no error.
    report.value = null
    hasSearched.value = false
    loadError.value = ''
    return
  }

  autoSearchTimer = setTimeout(() => runSearch(stockId), AUTO_SEARCH_DELAY_MS)
})

onBeforeUnmount(() => {
  if (autoSearchTimer) clearTimeout(autoSearchTimer)
})

function printReport() {
  window.print()
}
</script>

<template>
  <div>
    <PageHeader
      title="Stock ID Wise Details"
      description="Trace any stock ID back to its parent lot and every transaction drawn from it."
    />

    <BaseCard class="mb-4">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitSearch">
        <BaseInput
          v-model="stockIdInput"
          label="Stock ID"
          type="number"
          step="1"
          size="sm"
          placeholder="e.g. 112"
          class="max-w-xs"
        />
        <BaseButton type="submit" :disabled="isLoading">
          {{ isLoading ? 'Searching…' : 'Search' }}
        </BaseButton>
      </form>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <template v-if="report">
      <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="mb-3 text-sm font-semibold text-slate-900">
            Lot #{{ report.lot_details.lot_id }} — {{ report.lot_details.item_name }}
          </div>
          <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
              <dt class="text-xs text-slate-500">Original grams</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.lot_details.original_grams) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Touch</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.lot_details.touch) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Purity</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.lot_details.purity) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Current balance</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.lot_details.current_balance) }}
              </dd>
            </div>
          </dl>
          <p class="mt-3 text-xs text-slate-500">
            Created by <span class="font-medium text-slate-700">{{ report.lot_details.lot_creator }}</span>
            on {{ formatDateTime(report.lot_details.added_at) }}
          </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="mb-3 text-sm font-semibold text-slate-900">Reconciliation</div>
          <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
              <dt class="text-xs text-slate-500">Received</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.quantity_summary.total_received) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Consumed</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.quantity_summary.total_consumed) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Reconciled balance</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.quantity_summary.reconciled_balance) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Actual balance</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900">
                {{ formatNumber(report.quantity_summary.actual_balance) }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="mb-2 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">Stock Details</h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Print report"
          @click="printReport"
        >
          <Printer class="h-4 w-4" />
        </button>
      </div>

      <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <th v-for="column in columns" :key="column.key" scope="col" class="px-3 py-2 text-left">
                {{ column.label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="report.transactions.length === 0">
              <td :colspan="columns.length" class="px-4 py-8 text-center text-slate-500">
                No transactions have been drawn from this lot yet.
              </td>
            </tr>
            <tr v-for="row in report.transactions" :key="row.id" class="hover:bg-slate-50">
              <td class="px-3 py-2 text-slate-500">{{ row.id }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.item_name || '—' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.type }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.entry_type }}</td>
              <td class="px-3 py-2">
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="row.stock_type === 'IN' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                >
                  {{ row.stock_type }}
                </span>
              </td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.grams) }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.touch) }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatNumber(row.wastage) }}</td>
              <td class="px-3 py-2 text-right tabular-nums font-semibold text-slate-900">
                {{ formatNumber(row.purity) }}
              </td>
              <td class="px-3 py-2 text-slate-700">{{ row.given_by || '—' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.given_to || '—' }}</td>
              <td class="px-3 py-2 text-slate-500">{{ row.remarks || '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap text-slate-500">{{ formatDateTime(row.added_at) }}</td>
              <td class="px-3 py-2 text-center">
                <button
                  type="button"
                  class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                  :aria-label="`Print transaction ${row.id}`"
                  @click="printReport"
                >
                  <Printer class="h-4 w-4" />
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="report.transactions.length > 0" class="border-t border-slate-200 bg-slate-50">
            <tr class="font-semibold text-slate-900">
              <td colspan="5" class="px-3 py-2">Total</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.grams) }}</td>
              <td class="px-3 py-2"></td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.wastage) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.purity) }}</td>
              <td colspan="4" class="px-3 py-2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </template>

    <p v-else-if="hasSearched === false && !loadError" class="text-sm text-slate-500">
      Enter a stock ID above to view its lot details and transaction lineage.
    </p>
  </div>
</template>
