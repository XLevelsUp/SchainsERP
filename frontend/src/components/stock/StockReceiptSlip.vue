<script setup lang="ts">
import { computed } from 'vue'
import type { StockReceipt } from '@/lib/stockReceiptPrint'

/*
| Print-only legacy stock receipt — see lib/stockReceiptPrint.ts for where
| each figure comes from. Hidden on screen; shown only while
| useStockReceiptPrint() has set `printing-stock-receipt` on <body>.
|
| Same layout as the old software's slip (heading, date, OB, Time/Items/Pcs/
| Gms/%/Purity, Total, CB), drawn with full cell borders so every figure
| sits in its own box.
*/

const props = defineProps<{ receipt: StockReceipt }>()

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

// "2026-10-28 18:40:05" → "28-Oct-2026" and "18:40:05", read straight off
// the string so no timezone conversion can move the day.
const date = computed(() => {
  const [y, m, d] = props.receipt.addedAt.slice(0, 10).split('-')
  const month = MONTHS[Number(m) - 1]
  return y && month && d ? `${d}-${month}-${y}` : props.receipt.addedAt
})
const time = computed(() => props.receipt.addedAt.slice(11, 19))

// Legacy style: up to 3 decimals, trailing zeros dropped, no grouping —
// 1.350 prints as 1.35, 99.800 as 99.8.
function figure(value: number) {
  return Number.isFinite(value) ? String(Number(value.toFixed(3))) : '0'
}

function balance(value: number | null) {
  return value === null ? '—' : `${figure(value)}P`
}
</script>

<template>
  <div class="stock-receipt-print-root hidden">
    <table>
      <tbody>
        <tr>
          <td colspan="6" class="sr-heading">{{ receipt.givenBy }} TO {{ receipt.givenTo }}</td>
        </tr>
        <tr>
          <td colspan="6">{{ date }}</td>
        </tr>
        <tr>
          <td colspan="2">{{ receipt.balanceName }} Bal</td>
          <td colspan="2" class="sr-center">OB</td>
          <td colspan="2" class="sr-num">{{ balance(receipt.ob) }}</td>
        </tr>
        <tr class="sr-columns">
          <th>Time</th>
          <th>Items</th>
          <th>Pcs</th>
          <th>Gms</th>
          <th>%</th>
          <th>Purity</th>
        </tr>
        <tr>
          <td>{{ time }}</td>
          <td>{{ receipt.item || '—' }}</td>
          <td class="sr-num">{{ receipt.pcs ? figure(receipt.pcs) : '' }}</td>
          <td class="sr-num">{{ figure(receipt.grams) }}</td>
          <td class="sr-num">{{ figure(receipt.touch) }}</td>
          <td class="sr-num">{{ figure(receipt.purity) }}</td>
        </tr>
        <tr>
          <td colspan="3">Total</td>
          <td class="sr-num">{{ figure(receipt.grams) }}G</td>
          <td></td>
          <td class="sr-num">{{ figure(receipt.purity) }}P</td>
        </tr>
        <tr>
          <td colspan="2">{{ receipt.balanceName }} Bal</td>
          <td colspan="2" class="sr-center">CB</td>
          <td colspan="2" class="sr-num">{{ balance(receipt.cb) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<!--
  Not scoped: the @media print rule has to reach `body *` to hide the rest
  of the app. The body class must match STOCK_RECEIPT_PRINT_CLASS in
  lib/stockReceiptPrint.ts. Same isolation pattern as HeadStockSummaryPanel's
  slip; the `visibility: visible` selectors also out-rank ThermalPrintModal's
  unconditional `body *` rule once Cash Management has loaded it.
-->
<style>
.stock-receipt-print-root table {
  border-collapse: collapse;
  width: 2.9in;
  color: #000;
  font-size: 11px;
  font-weight: 700;
}

.stock-receipt-print-root th,
.stock-receipt-print-root td {
  border: 1px solid #000;
  padding: 2px 4px;
  vertical-align: top;
}

.stock-receipt-print-root .sr-heading {
  text-align: center;
  font-size: 12px;
}

.stock-receipt-print-root .sr-columns th {
  text-align: left;
  text-decoration: underline;
}

.stock-receipt-print-root .sr-center {
  text-align: center;
}

.stock-receipt-print-root .sr-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

@media print {
  body.printing-stock-receipt * {
    visibility: hidden;
  }

  body.printing-stock-receipt .stock-receipt-print-root,
  body.printing-stock-receipt .stock-receipt-print-root * {
    visibility: visible;
  }

  body.printing-stock-receipt .stock-receipt-print-root {
    display: block;
    position: fixed;
    inset: 0;
    margin: 0;
    padding: 0;
  }
}
</style>
