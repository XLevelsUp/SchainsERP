<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Printer } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { headStocksApi } from '@/lib/headStocksApi'
import { ApiError } from '@/lib/api'
import { nowTimestamp } from '@/lib/date'
import { useAuthStore } from '@/stores/auth'
import type { HeadStockSummary } from '@/types'

/*
|--------------------------------------------------------------------------
| Head Stocks panel — GET /stock-details/head-stocks
|--------------------------------------------------------------------------
| Legacy "Stock Details" screen's top-left item-wise balance summary for
| the logged-in head: grams/%/purity per item, plus grand total, cash
| balance, and active-orders weight. The date/time fields replay the
| balance as of that moment (head_txn_from_date/head_txn_from_time);
| left blank, the backend returns live totals.
|
| The printer button prints a compact slip — head name, timestamp, and an
| Item Name/Grams/Purity table — not the whole page. It renders from the
| same `summary` object as the on-screen table, so the two can never
| disagree; only the presentation differs (the on-screen "%" column is
| dropped, and zero-purity rows print blank rather than "0.000", matching
| the legacy slip). Isolation follows ThermalPrintModal's pattern: an
| @media print block hides `body *` and re-shows one print root, rather
| than a second window that popup blockers would eat.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()

const summary = ref<HeadStockSummary | null>(null)
const isLoading = ref(false)
const loadError = ref('')
const asOfDate = ref('')
const asOfTime = ref('')

function formatNumber(value: number) {
  return Number.isFinite(value)
    ? value.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
    : '0.000'
}

async function load() {
  if (!auth.user) return
  isLoading.value = true
  loadError.value = ''
  try {
    summary.value = await headStocksApi.get(auth.user.user_id, {
      head_txn_from_date: asOfDate.value || undefined,
      head_txn_from_time: asOfTime.value || undefined,
    })
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load head stocks.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
watch([asOfDate, asOfTime], load)

// The signed-in head's name titles the slip.
//
// Known gap: the legacy slip reads "NIHAA(PR)" — name plus the user's
// `type`, the same convention UserPickerPanel's formatUserLabel uses. The
// login response (AuthUser) carries only user_id/name/user_name/role_id,
// so `type` is not available here and the suffix cannot be reproduced
// without either adding it to the auth payload (backend) or fetching
// GET /user-details/{id} just for this label. Printing the bare name
// rather than inventing a suffix.
const printTitle = computed(() => auth.user?.name || auth.user?.user_name || '')

// Blank, not "0.000": non-metal items (stone, f-items) carry no purity and
// the legacy slip leaves that cell empty so the column reads as gold only.
function formatPurity(value: number) {
  return value === 0 ? '' : formatNumber(value)
}

// Cash and active orders print as plain figures, without the 3-decimal
// weight formatting the gram columns use.
function formatPlain(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

// Stamped when the button is pressed. With an as-of filter set, the slip is
// labelled with that moment rather than "now" — otherwise a replayed
// balance would print under the wrong time.
const printedAt = ref('')

// The print isolation is keyed off a body class rather than applying to
// every print. TransactionHistoryPanel sits on this same page and prints
// the whole page by design — an unconditional `body * { visibility:hidden }`
// would silently turn its print into this slip.
const PRINT_BODY_CLASS = 'printing-head-stock'

async function printPanel() {
  printedAt.value = asOfDate.value ? `${asOfDate.value} ${asOfTime.value || '00:00'}:00` : nowTimestamp()
  document.body.classList.add(PRINT_BODY_CLASS)
  // Let the stamp render before the browser snapshots the page.
  await nextTick()
  // afterprint rather than a synchronous cleanup: window.print() blocks
  // until the dialog closes in Chrome but returns immediately in Safari,
  // where removing the class here would strip the styling mid-print.
  window.addEventListener('afterprint', () => document.body.classList.remove(PRINT_BODY_CLASS), {
    once: true,
  })
  window.print()
}

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-900">Head Stocks</h2>
      <button
        type="button"
        class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Print head stocks"
        :disabled="!summary"
        @click="printPanel"
      >
        <Printer class="h-4 w-4" />
      </button>
    </div>

    <div class="flex gap-2 border-b border-slate-200 px-4 py-3">
      <BaseInput v-model="asOfDate" type="date" size="sm" class="flex-1" clearable />
      <BaseInput v-model="asOfTime" type="time" size="sm" class="flex-1" clearable />
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>

    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">Loading…</div>

    <div v-else-if="summary" class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <th scope="col" class="px-4 py-2 text-left">Item</th>
            <th scope="col" class="px-3 py-2 text-right">Grams</th>
            <th scope="col" class="px-3 py-2 text-right">%</th>
            <th scope="col" class="px-4 py-2 text-right">Purity</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="summary.items.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No items mapped yet.</td>
          </tr>
          <tr v-for="item in summary.items" :key="item.item_id">
            <td class="px-4 py-2 text-slate-700">{{ item.item_name }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">
              {{ formatNumber(item.grams) }}
            </td>
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">
              {{ formatNumber(item.percentage) }}
            </td>
            <td class="px-4 py-2 text-right tabular-nums text-slate-700">
              {{ formatNumber(item.purity) }}
            </td>
          </tr>
        </tbody>
        <tfoot class="divide-y divide-slate-100 border-t border-slate-200">
          <tr class="font-semibold text-slate-900">
            <td class="px-4 py-2">Total</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(summary.totals.grams) }}</td>
            <td class="px-3 py-2"></td>
            <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(summary.totals.purity) }}</td>
          </tr>
          <tr class="text-slate-700">
            <td class="px-4 py-2" colspan="3">Cash</td>
            <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(summary.cash_balance) }}</td>
          </tr>
          <tr class="text-slate-700">
            <td class="px-4 py-2" colspan="3">Active Orders</td>
            <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(summary.active_orders) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </BaseCard>

  <!--
    Print-only slip. Hidden on screen (`hidden`), laid out only for print
    (`print:block`) — the @media block below then hides everything else on
    the page so this is all that reaches the printer.
  -->
  <div v-if="summary" class="head-stock-print-root hidden">
    <table>
      <tbody>
        <tr>
          <td colspan="3" class="hs-title">{{ printTitle }}</td>
        </tr>
        <tr>
          <td colspan="3" class="hs-stamp">{{ printedAt }}</td>
        </tr>
        <tr>
          <th>Item Name</th>
          <th>Grams</th>
          <th>Purity</th>
        </tr>
        <tr v-for="item in summary.items" :key="item.item_id">
          <td class="hs-name">{{ item.item_name }}</td>
          <td class="hs-num">{{ formatNumber(item.grams) }}</td>
          <td class="hs-num">{{ formatPurity(item.purity) }}</td>
        </tr>
        <tr>
          <td class="hs-name">Total</td>
          <td class="hs-num">{{ formatNumber(summary.totals.grams) }}</td>
          <td class="hs-num">{{ formatNumber(summary.totals.purity) }}</td>
        </tr>
        <tr>
          <td colspan="2" class="hs-name">Cash</td>
          <td class="hs-num">{{ formatPlain(summary.cash_balance) }}</td>
        </tr>
        <tr>
          <td colspan="2" class="hs-name">Active Orders</td>
          <td class="hs-num">{{ formatPlain(summary.active_orders) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<!--
  Not scoped: the @media print rule has to reach `body *` to hide the rest
  of the app, which a scoped style cannot do. Every other selector is
  namespaced under .head-stock-print-root so nothing leaks.
-->
<style>
.head-stock-print-root table {
  border-collapse: collapse;
  width: 2.9in;
  color: #000;
  font-size: 11px;
}

.head-stock-print-root th,
.head-stock-print-root td {
  border: 1px solid #000;
  padding: 2px 6px;
}

.head-stock-print-root th,
.head-stock-print-root .hs-title,
.head-stock-print-root .hs-stamp,
.head-stock-print-root .hs-name {
  text-align: center;
  font-weight: 700;
}

.head-stock-print-root .hs-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

/*
  Scoped to the body class set by printPanel(), so this only takes effect
  for *this* panel's print button. Any other print on the page (Transaction
  History prints the whole page) is left exactly as it was.
*/
@media print {
  body.printing-head-stock * {
    visibility: hidden;
  }

  body.printing-head-stock .head-stock-print-root,
  body.printing-head-stock .head-stock-print-root * {
    visibility: visible;
  }

  /* Beats Tailwind's `.hidden` on specificity, so the slip only ever
     appears for its own print and never on screen. */
  body.printing-head-stock .head-stock-print-root {
    display: block;
    position: fixed;
    inset: 0;
    margin: 0;
    padding: 0;
  }
}
</style>
