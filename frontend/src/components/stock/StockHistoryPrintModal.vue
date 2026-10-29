<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PrintPreviewModal from '@/components/ui/PrintPreviewModal.vue'
import { stockHistoryApi } from '@/lib/stockHistoryApi'
import { ApiError } from '@/lib/api'
import { nowTimestamp } from '@/lib/date'
import type { StockHistoryQuery, StockHistoryResult } from '@/types'

/*
|--------------------------------------------------------------------------
| Transaction History report — GET /stock-details/history
|--------------------------------------------------------------------------
| Prints the page of history the operator is looking at: TransactionHistory
| Panel hands over the exact query it last loaded with (head, type, item,
| dates, page size, page), and this re-requests that one page so the print
| is what the server holds now. Totals are the backend's, across every page
| of the filtered set, same as the on-screen Total row.
|
| The endpoint has no per-row timestamp, so the sheet is stamped with the
| time it was printed instead.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  query: StockHistoryQuery
  headName: string
}>()
const emit = defineEmits<{ close: [] }>()

const result = ref<StockHistoryResult | null>(null)
const isLoading = ref(true)
const loadError = ref('')
const printedAt = nowTimestamp()

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    result.value = await stockHistoryApi.list(props.query)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load transaction history.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}
</script>

<template>
  <PrintPreviewModal
    title="Print Transaction History"
    :subtitle="headName"
    :is-loading="isLoading"
    :error="loadError"
    :ready="result !== null"
    max-width="max-w-4xl"
    @close="emit('close')"
  >
    <table v-if="result" class="report">
      <caption>
        Transaction History — {{ headName }}
        <span class="report-stamp">
          Printed {{ printedAt }} · Page {{ result.transactions.current_page }} of
          {{ result.transactions.last_page }}
        </span>
      </caption>
      <thead>
        <tr>
          <th>ID</th>
          <th>Item</th>
          <th>Type</th>
          <th>Grams</th>
          <th>Pcs</th>
          <th>Touch %</th>
          <th>Wastage</th>
          <th>Purity g</th>
          <th>Party</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="result.transactions.data.length === 0">
          <td colspan="10" class="report-empty">No transactions on this page.</td>
        </tr>
        <tr v-for="row in result.transactions.data" :key="row.id">
          <td class="report-num">{{ row.id }}</td>
          <td>{{ row.item_name || '—' }}</td>
          <td>{{ row.stock_type === 'IN' ? 'In' : 'Out' }}</td>
          <td class="report-num">{{ formatNumber(row.grams) }}</td>
          <td class="report-num">{{ row.pcs ? formatNumber(row.pcs) : '' }}</td>
          <td class="report-num">{{ formatNumber(row.touch) }}</td>
          <td class="report-num">{{ formatNumber(row.wastage) }}</td>
          <td class="report-num">{{ formatNumber(row.purity) }}</td>
          <td>{{ row.user || '—' }}</td>
          <td>{{ row.remarks || '—' }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3">Total · all pages</td>
          <td class="report-num">{{ formatNumber(result.totals.grams) }}</td>
          <td class="report-num">{{ formatNumber(result.totals.pcs) }}</td>
          <td></td>
          <td></td>
          <td class="report-num">{{ formatNumber(result.totals.purity) }}</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </PrintPreviewModal>
</template>

<style scoped>
.report {
  width: 100%;
  border-collapse: collapse;
  color: #000;
  font-size: 11px;
}

.report caption {
  caption-side: top;
  margin-bottom: 6px;
  text-align: left;
  font-weight: 700;
}

.report-stamp {
  display: block;
  font-size: 10px;
  font-weight: 400;
}

.report th,
.report td {
  border: 1px solid #000;
  padding: 2px 6px;
}

.report th {
  text-align: left;
  white-space: nowrap;
}

.report tfoot td {
  font-weight: 700;
}

.report-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.report-empty {
  text-align: center;
}
</style>
