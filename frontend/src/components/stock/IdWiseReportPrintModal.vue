<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PrintPreviewModal from '@/components/ui/PrintPreviewModal.vue'
import { stockReportsApi } from '@/lib/stockReportsApi'
import { ApiError } from '@/lib/api'
import { formatDateTime, nowTimestamp } from '@/lib/date'
import type { IdWiseReportResult } from '@/types'

/*
|--------------------------------------------------------------------------
| Stock ID Wise report sheet — GET /stock/reports/id-wise?stock_id=
|--------------------------------------------------------------------------
| The whole report for one stock id — lot details, reconciliation and every
| transaction in the lot — re-requested when the preview opens so a lot
| balance that moved since the search is printed as it is now. Replaces a
| bare window.print() of the entire page.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ stockId: number }>()
const emit = defineEmits<{ close: [] }>()

const report = ref<IdWiseReportResult | null>(null)
const isLoading = ref(true)
const loadError = ref('')
const printedAt = nowTimestamp()

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    report.value = await stockReportsApi.getIdWise(props.stockId)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

const totals = computed(() =>
  (report.value?.transactions ?? []).reduce(
    (acc, row) => ({
      grams: acc.grams + row.grams,
      wastage: acc.wastage + row.wastage,
      purity: acc.purity + row.purity,
    }),
    { grams: 0, wastage: 0, purity: 0 },
  ),
)

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}
</script>

<template>
  <PrintPreviewModal
    title="Print Stock ID Wise Details"
    :subtitle="`Stock ID ${stockId}`"
    :is-loading="isLoading"
    :error="loadError"
    :ready="report !== null"
    max-width="max-w-5xl"
    @close="emit('close')"
  >
    <div v-if="report" class="sheet">
      <p class="sheet-title">
        Stock ID Wise Details — Lot #{{ report.lot_details.lot_id }} ({{ report.lot_details.item_name }})
      </p>
      <p class="sheet-stamp">
        Queried ID {{ report.stock_summary.queried_id }} · Printed {{ printedAt }}
      </p>

      <table class="summary">
        <tbody>
          <tr>
            <th>Original grams</th>
            <td class="num">{{ formatNumber(report.lot_details.original_grams) }}</td>
            <th>Touch</th>
            <td class="num">{{ formatNumber(report.lot_details.touch) }}</td>
            <th>Purity</th>
            <td class="num">{{ formatNumber(report.lot_details.purity) }}</td>
            <th>Current balance</th>
            <td class="num">{{ formatNumber(report.lot_details.current_balance) }}</td>
          </tr>
          <tr>
            <th>Received</th>
            <td class="num">{{ formatNumber(report.quantity_summary.total_received) }}</td>
            <th>Consumed</th>
            <td class="num">{{ formatNumber(report.quantity_summary.total_consumed) }}</td>
            <th>Reconciled balance</th>
            <td class="num">{{ formatNumber(report.quantity_summary.reconciled_balance) }}</td>
            <th>Actual balance</th>
            <td class="num">{{ formatNumber(report.quantity_summary.actual_balance) }}</td>
          </tr>
          <tr>
            <th>Created by</th>
            <td colspan="3">{{ report.lot_details.lot_creator }}</td>
            <th>Created on</th>
            <td colspan="3">{{ formatDateTime(report.lot_details.added_at) }}</td>
          </tr>
        </tbody>
      </table>

      <table class="detail">
        <thead>
          <tr>
            <th>ID</th>
            <th>Item</th>
            <th>Type</th>
            <th>Entry Type</th>
            <th>Stock Type</th>
            <th>Grams</th>
            <th>Touch</th>
            <th>Wastage</th>
            <th>Purity</th>
            <th>Given By</th>
            <th>Given To</th>
            <th>Remarks</th>
            <th>Added At</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="report.transactions.length === 0">
            <td colspan="13" class="center">No transactions in this lot.</td>
          </tr>
          <tr v-for="row in report.transactions" :key="row.id">
            <td class="num">{{ row.id }}</td>
            <td>{{ row.item_name || '—' }}</td>
            <td>{{ row.type }}</td>
            <td>{{ row.entry_type }}</td>
            <td>{{ row.stock_type }}</td>
            <td class="num">{{ formatNumber(row.grams) }}</td>
            <td class="num">{{ formatNumber(row.touch) }}</td>
            <td class="num">{{ formatNumber(row.wastage) }}</td>
            <td class="num">{{ formatNumber(row.purity) }}</td>
            <td>{{ row.given_by || '—' }}</td>
            <td>{{ row.given_to || '—' }}</td>
            <td>{{ row.remarks || '—' }}</td>
            <td class="nowrap">{{ formatDateTime(row.added_at) }}</td>
          </tr>
        </tbody>
        <tfoot v-if="report.transactions.length > 0">
          <tr>
            <td colspan="5">Total</td>
            <td class="num">{{ formatNumber(totals.grams) }}</td>
            <td></td>
            <td class="num">{{ formatNumber(totals.wastage) }}</td>
            <td class="num">{{ formatNumber(totals.purity) }}</td>
            <td colspan="4"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </PrintPreviewModal>
</template>

<style scoped>
.sheet {
  color: #000;
  font-size: 11px;
}

.sheet-title {
  font-size: 12px;
  font-weight: 700;
}

.sheet-stamp {
  margin-bottom: 6px;
  font-size: 10px;
}

.summary,
.detail {
  width: 100%;
  border-collapse: collapse;
}

.summary {
  margin-bottom: 8px;
}

.sheet th,
.sheet td {
  border: 1px solid #000;
  padding: 2px 6px;
}

.sheet th {
  text-align: left;
  white-space: nowrap;
}

.detail tfoot td {
  font-weight: 700;
}

.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.center {
  text-align: center;
}

.nowrap {
  white-space: nowrap;
}
</style>
