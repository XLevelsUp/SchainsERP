<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PrintPreviewModal from '@/components/ui/PrintPreviewModal.vue'
import { headStocksApi } from '@/lib/headStocksApi'
import { ApiError } from '@/lib/api'
import { nowTimestamp } from '@/lib/date'
import type { HeadStockSummary } from '@/types'

/*
|--------------------------------------------------------------------------
| Head Stocks slip — GET /stock-details/head-stocks
|--------------------------------------------------------------------------
| Fetched again when the preview opens, with the same user and as-of
| filters as HeadStockSummaryPanel, so the slip shows the balance at the
| moment of printing rather than whenever the panel last loaded. No other
| endpoint is involved.
|
| Layout is the legacy slip: name, timestamp, Item Name/Grams/Purity. The
| on-screen "%" column is dropped, and zero-purity rows print blank rather
| than "0.000" (non-metal items carry no purity).
|
| Known gap: the legacy slip reads "NIHAA(PR)" — name plus the user's
| `type`. The login response (AuthUser) carries only user_id/name/user_name/
| role_id, so the caller passes the bare name rather than an invented suffix.
|--------------------------------------------------------------------------
*/

const props = withDefaults(
  defineProps<{
    userId: number
    title: string
    asOfDate?: string
    asOfTime?: string
  }>(),
  { asOfDate: '', asOfTime: '' },
)
const emit = defineEmits<{ close: [] }>()

const summary = ref<HeadStockSummary | null>(null)
const isLoading = ref(true)
const loadError = ref('')

// With an as-of filter set, the slip is labelled with that moment rather
// than "now" — otherwise a replayed balance would print under the wrong time.
const printedAt = props.asOfDate
  ? `${props.asOfDate} ${props.asOfTime || '00:00'}:00`
  : nowTimestamp()

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    summary.value = await headStocksApi.get(props.userId, {
      head_txn_from_date: props.asOfDate || undefined,
      head_txn_from_time: props.asOfTime || undefined,
    })
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load head stocks.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

function formatNumber(value: number) {
  return Number.isFinite(value)
    ? value.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
    : '0.000'
}

function formatPurity(value: number) {
  return value === 0 ? '' : formatNumber(value)
}

// Cash and active orders print as plain figures, without the 3-decimal
// weight formatting the gram columns use.
function formatPlain(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}
</script>

<template>
  <PrintPreviewModal
    title="Print Head Stocks"
    :subtitle="title"
    :is-loading="isLoading"
    :error="loadError"
    :ready="summary !== null"
    @close="emit('close')"
  >
    <table v-if="summary" class="slip">
      <tbody>
        <tr>
          <td colspan="3" class="slip-center">{{ title }}</td>
        </tr>
        <tr>
          <td colspan="3" class="slip-center">{{ printedAt }}</td>
        </tr>
        <tr>
          <th>Item Name</th>
          <th>Grams</th>
          <th>Purity</th>
        </tr>
        <tr v-if="summary.items.length === 0">
          <td colspan="3" class="slip-center">No items mapped yet.</td>
        </tr>
        <tr v-for="item in summary.items" :key="item.item_id">
          <td class="slip-center">{{ item.item_name }}</td>
          <td class="slip-num">{{ formatNumber(item.grams) }}</td>
          <td class="slip-num">{{ formatPurity(item.purity) }}</td>
        </tr>
        <tr>
          <td class="slip-center">Total</td>
          <td class="slip-num">{{ formatNumber(summary.totals.grams) }}</td>
          <td class="slip-num">{{ formatNumber(summary.totals.purity) }}</td>
        </tr>
        <tr>
          <td colspan="2" class="slip-center">Cash</td>
          <td class="slip-num">{{ formatPlain(summary.cash_balance) }}</td>
        </tr>
        <tr>
          <td colspan="2" class="slip-center">Active Orders</td>
          <td class="slip-num">{{ formatPlain(summary.active_orders) }}</td>
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

.slip th,
.slip td {
  border: 1px solid #000;
  padding: 2px 6px;
}

.slip th,
.slip-center {
  text-align: center;
  font-weight: 700;
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
