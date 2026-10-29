<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PrintPreviewModal from '@/components/ui/PrintPreviewModal.vue'
import { stockReportsApi } from '@/lib/stockReportsApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { IdWiseTransaction } from '@/types'

/*
|--------------------------------------------------------------------------
| Stock transaction receipt — GET /stock/reports/id-wise?stock_id=
|--------------------------------------------------------------------------
| The backend has no stock equivalent of /cash-txn-details/print-report, so
| this uses the one endpoint that returns a single stock row by id. The
| response is the row's whole lot; the row itself is always in
| `transactions` (ReportService::getIdWiseStockReport selects the parent lot
| plus every row whose stock_in_id points at it), so it is picked out by id.
|
| That gives the receipt what GET /stock-details/history never had: the
| transaction's own `added_at`, and both party names straight from
| given_by/given_to instead of inferring one side from the viewer.
|
| Still missing from every stock endpoint — flagged in BACKEND_TODO.md §15:
|   - the legacy slip's "Excess OB/CB" balance lines (no per-row OB/CB
|     snapshot is returned for a single id), so none is printed;
|   - no_of_pcs, which id-wise omits. The Transaction History row already
|     has it for the same stock_id, so that caller passes it in as `pcs`.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  stockId: number
  pcs?: number
}>()
const emit = defineEmits<{ close: [] }>()

const txn = ref<IdWiseTransaction | null>(null)
const isLoading = ref(true)
const loadError = ref('')

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    const report = await stockReportsApi.getIdWise(props.stockId)
    txn.value = report.transactions.find((row) => row.id === props.stockId) ?? null
    if (!txn.value) loadError.value = `Transaction #${props.stockId} was not returned by the stock report.`
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the transaction.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

// `type` is the operation; stock_type is the side of it this row records.
// Plain stock rows (NORMAL/IN/OUT) read as "Stock In/Out"; everything else
// names its operation, with the side in brackets.
const TYPE_LABELS: Record<string, string> = {
  GMS: 'GMS',
  NUMERICWASTE: 'Numeric Wastage',
  ITEMCHANGE: 'Item Change',
  ITEMCONVERSION: 'Item Conversion',
  EMPTOEMP: 'Auto Entry',
  EMPTOHEAD: 'Auto Entry',
  ANOTHERHEADTOEMP: 'Auto Entry',
  HEADTOHEAD: 'Auto Entry',
}

const typeLabel = computed(() => {
  if (!txn.value) return ''
  const side = txn.value.stock_type === 'IN' ? 'In' : 'Out'
  const operation = TYPE_LABELS[txn.value.type]
  return operation ? `${operation} (${side})` : `Stock ${side}`
})

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}
</script>

<template>
  <PrintPreviewModal
    title="Print Receipt"
    :subtitle="`Stock transaction #${stockId}`"
    :is-loading="isLoading"
    :error="loadError"
    :ready="txn !== null"
    @close="emit('close')"
  >
    <table v-if="txn" class="slip">
      <tbody>
        <tr>
          <td colspan="2" class="slip-title">Transaction #{{ txn.id }}</td>
        </tr>
        <tr>
          <td colspan="2" class="slip-center">{{ formatDateTime(txn.added_at) }}</td>
        </tr>
        <tr>
          <td colspan="2" class="slip-center slip-bold">From: {{ txn.given_by }} => To: {{ txn.given_to }}</td>
        </tr>
        <tr>
          <td class="slip-label">Type</td>
          <td>{{ typeLabel }}</td>
        </tr>
        <tr>
          <td class="slip-label">Item</td>
          <td>{{ txn.item_name || '—' }}</td>
        </tr>
        <tr>
          <td class="slip-label">Grams</td>
          <td class="slip-num">{{ formatNumber(txn.grams) }}</td>
        </tr>
        <tr v-if="pcs">
          <td class="slip-label">Pcs</td>
          <td class="slip-num">{{ formatNumber(pcs) }}</td>
        </tr>
        <tr>
          <td class="slip-label">Touch %</td>
          <td class="slip-num">{{ formatNumber(txn.touch) }}</td>
        </tr>
        <tr>
          <td class="slip-label">Wastage</td>
          <td class="slip-num">{{ formatNumber(txn.wastage) }}</td>
        </tr>
        <tr>
          <td class="slip-label">Purity g</td>
          <td class="slip-num">{{ formatNumber(txn.purity) }}</td>
        </tr>
        <tr v-if="txn.remarks">
          <td class="slip-label">Remarks</td>
          <td>{{ txn.remarks }}</td>
        </tr>
        <tr v-if="txn.item_remarks">
          <td class="slip-label">Item remarks</td>
          <td>{{ txn.item_remarks }}</td>
        </tr>
      </tbody>
    </table>
  </PrintPreviewModal>
</template>

<style scoped>
.slip {
  width: 2.9in;
  margin: 0 auto;
  border-collapse: collapse;
  color: #000;
  font-size: 11px;
}

.slip td {
  border: 1px solid #000;
  padding: 2px 6px;
}

.slip-title {
  text-align: center;
  font-size: 12px;
  font-weight: 700;
}

.slip-center {
  text-align: center;
}

.slip-bold,
.slip-label {
  font-weight: 700;
}

.slip-label {
  width: 40%;
}

.slip-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

@media print {
  .slip {
    margin: 0;
  }
}
</style>
