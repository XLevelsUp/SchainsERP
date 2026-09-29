<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Printer, EyeOff } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { stockHistoryApi } from '@/lib/stockHistoryApi'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { nowTimestamp } from '@/lib/date'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Item, StockHistoryRow, StockHistoryTotals, UserDetailListItem } from '@/types'

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
| Printing. Both buttons used to be a bare window.print(), which printed the
| entire page — sidebar, other panels, everything — because there was no
| isolation at all. Fixed the same way HeadStockSummaryPanel's slip is: a
| print-only root, toggled by a body class so an @media print rule can hide
| `body *` and re-show just that root, with `afterprint` clearing the class
| again. Two separate roots/classes because this panel has two different
| print targets: the row button prints one transaction as a receipt, the
| header button prints the current page of the table as a report — treating
| them as the same target would flash the wrong content for whichever button
| gets clicked second.
|
| The receipt is deliberately thinner than the legacy slip it is replacing.
| GET /stock-details/history (ReportService::getStockHistory) returns only
| id/item_name/stock_type/grams/pcs/touch/wastage/purity/user_id/user/
| remarks — no per-row timestamp, no OB/CB balance snapshot, and no company
| letterhead config anywhere in this app. Reproducing the legacy receipt's
| "Excess OB/CB" lines and exact transaction time would mean inventing
| numbers this endpoint does not provide, which is worse than omitting them.
| What IS reliable: the counterparty name (`user`) plus `stock_type` tells us
| the full From/To pair once we know the head's own name, which is why this
| panel now takes a `users` prop — the same list StockManagementView already
| loads for UserPickerPanel — purely to resolve that one name for the print
| header.
|
| Hide (POST /stock/hide) lives here because this is the only screen that
| lists stock_ids. It is NOT a delete: the rows stay in the ledger and keep
| affecting balances, they just stop being listed by getHistory and
| getAvailableMetals, both of which filter is_hided.
|
| Two things about it the operator has to be told before confirming, because
| neither is reversible from this app:
|   - hideStocks also hides the PARENT lot of any row that has a stock_in_id,
|     so hiding one child can take a whole lot out of the metal picker.
|   - there is no unhide endpoint. Nothing in the API sets is_hided back to
|     false, so this is one-way until the backend adds one.
|--------------------------------------------------------------------------
*/

const props = withDefaults(
  defineProps<{ items: Item[]; employeeId?: number | null; users?: UserDetailListItem[] }>(),
  { employeeId: null, users: () => [] },
)

const auth = useAuthStore()
const toast = useToastStore()

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
  // A selection only ever refers to rows currently on screen. Dropping it on
  // every load stops a filter or page change from carrying hidden-away ids
  // into the next Hide.
  selectedIds.value = new Set()
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
/*
| Hide selection. Keyed by stock id rather than row index so it survives a
| re-sort, and cleared on every load so a selection can never outlive the
| page it was made on.
*/
const selectedIds = ref<Set<number>>(new Set())
const isHiding = ref(false)
const confirmingHide = ref(false)

const selectedCount = computed(() => selectedIds.value.size)
const allOnPageSelected = computed(
  () => rows.value.length > 0 && rows.value.every((row) => selectedIds.value.has(row.id)),
)

function toggleRow(id: number) {
  // Reassigned rather than mutated — a Set mutation is not reactive.
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function toggleAllOnPage() {
  const next = new Set(selectedIds.value)
  if (allOnPageSelected.value) rows.value.forEach((row) => next.delete(row.id))
  else rows.value.forEach((row) => next.add(row.id))
  selectedIds.value = next
}

async function confirmHide() {
  if (isHiding.value || selectedIds.value.size === 0) return
  isHiding.value = true
  try {
    await stockApi.postHideStocks([...selectedIds.value])
    toast.show(`Hid ${selectedIds.value.size} transaction(s).`, 'success')
    confirmingHide.value = false
    selectedIds.value = new Set()
    await load()
  } catch (err) {
    toast.show(
      err instanceof ApiError ? err.message : 'Failed to hide the selected transactions.',
      'error',
    )
  } finally {
    isHiding.value = false
  }
}

// The one name this panel doesn't otherwise carry: whichever user the
// history is being viewed AS. Falls back to the signed-in operator when no
// one is picked in UserPickerPanel, matching load()'s own `head_id` fallback
// (props.employeeId ?? auth.user.user_id) so the printed name always agrees
// with whose ledger is actually on screen.
const headName = computed(() => {
  if (props.employeeId !== null) {
    const picked = props.users.find((u) => u.id === props.employeeId)
    if (picked) return picked.full_name || picked.name
  }
  return auth.user?.name ?? 'Head'
})

// stock_type + the counterparty name (`user`) is all this endpoint gives us
// per row, but together they're the complete From/To pair once we know our
// own name.
function fromToLine(row: StockHistoryRow): string {
  const counterparty = row.user || '—'
  return row.stock_type === 'OUT'
    ? `From: ${headName.value} => To: ${counterparty}`
    : `From: ${counterparty} => To: ${headName.value}`
}

const PRINT_ROW_CLASS = 'printing-txn-row'
const PRINT_TABLE_CLASS = 'printing-txn-table'

// Stamped at print time, not claimed as the transaction's own time — the API
// doesn't return one. Shared by both print targets.
const printedAt = ref('')

async function printWithClass(bodyClass: string) {
  printedAt.value = nowTimestamp()
  document.body.classList.add(bodyClass)
  await nextTick()
  // afterprint rather than a synchronous cleanup: window.print() blocks until
  // the dialog closes in Chrome but returns immediately in Safari, where
  // removing the class right after the call would strip the styling
  // mid-print.
  window.addEventListener('afterprint', () => document.body.classList.remove(bodyClass), {
    once: true,
  })
  window.print()
}

// Row-level receipt. Which row is "being printed" has to be tracked
// explicitly (there is no per-row DOM to scope a print to) — one row at a
// time, so a second click before afterprint fires just retargets it.
const printingRow = ref<StockHistoryRow | null>(null)

function printRow(row: StockHistoryRow) {
  printingRow.value = row
  printWithClass(PRINT_ROW_CLASS)
}

// Header button — the current page of the table, not the whole app shell.
function printTable() {
  printWithClass(PRINT_TABLE_CLASS)
}

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-900">Transaction History</h2>
      <div class="flex items-center gap-2">
        <button
          v-if="selectedCount > 0"
          type="button"
          class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
          @click="confirmingHide = true"
        >
          <EyeOff class="h-3.5 w-3.5" /> Hide {{ selectedCount }} selected
        </button>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-40"
          aria-label="Print transaction history"
          :disabled="rows.length === 0"
          @click="printTable"
        >
          <Printer class="h-4 w-4" />
        </button>
      </div>
    </div>

    <div
      v-if="confirmingHide"
      class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900"
    >
      <p class="font-medium">Hide {{ selectedCount }} transaction(s)?</p>
      <p class="mt-1">
        They stay in the ledger and keep affecting balances — they just stop appearing in this
        history and in the metal picker. Any selected row drawn from a parent lot hides that lot
        too. <strong>There is no way to un-hide from this app.</strong>
      </p>
      <div class="mt-2 flex items-center gap-2">
        <BaseButton variant="secondary" :disabled="isHiding" @click="confirmingHide = false">
          Cancel
        </BaseButton>
        <BaseButton :disabled="isHiding" @click="confirmHide">
          {{ isHiding ? 'Hiding…' : 'Hide them' }}
        </BaseButton>
      </div>
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
      <BaseInput v-model="fromDate" type="date" size="sm" placeholder="From date" clearable />
      <BaseInput v-model="toDate" type="date" size="sm" placeholder="To date" clearable />
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>

    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">Loading…</div>

    <template v-else>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr class="border-b border-slate-200 font-semibold text-slate-900">
              <td colspan="5" class="px-4 py-2">Total</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.grams) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.pcs) }}</td>
              <td class="px-3 py-2"></td>
              <td class="px-3 py-2"></td>
              <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(totals.purity) }}</td>
              <td colspan="3" class="px-4 py-2"></td>
            </tr>
            <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <th scope="col" class="w-8 px-3 py-2 text-left">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                  aria-label="Select all on this page"
                  :checked="allOnPageSelected"
                  :disabled="rows.length === 0"
                  @change="toggleAllOnPage"
                />
              </th>
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
              <td colspan="13" class="px-4 py-8 text-center text-slate-500">
                No transactions recorded yet.
              </td>
            </tr>
            <tr
              v-for="(row, index) in rows"
              :key="row.id"
              class="hover:bg-slate-50"
              :class="selectedIds.has(row.id) ? 'bg-brand-50/60' : ''"
            >
              <td class="px-3 py-2">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                  :aria-label="`Select transaction ${row.id}`"
                  :checked="selectedIds.has(row.id)"
                  @change="toggleRow(row.id)"
                />
              </td>
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
                  @click="printRow(row)"
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

  <!--
    Print-only receipt for one row. Hidden on screen; the @media print rule
    below hides everything else on the page while `printing-txn-row` is set.
  -->
  <div v-if="printingRow" class="txn-print-root hidden">
    <table>
      <tbody>
        <tr>
          <td colspan="2" class="tp-title">Transaction #{{ printingRow.id }}</td>
        </tr>
        <tr>
          <td colspan="2" class="tp-stamp">Printed {{ printedAt }}</td>
        </tr>
        <tr>
          <td class="tp-label">Item</td>
          <td class="tp-value">{{ printingRow.item_name || '—' }}</td>
        </tr>
        <tr>
          <td class="tp-label">Type</td>
          <td class="tp-value">{{ printingRow.stock_type === 'IN' ? 'In' : 'Out' }}</td>
        </tr>
        <tr>
          <td class="tp-label">Grams</td>
          <td class="tp-value">{{ formatNumber(printingRow.grams) }}</td>
        </tr>
        <tr v-if="printingRow.pcs">
          <td class="tp-label">Pcs</td>
          <td class="tp-value">{{ formatNumber(printingRow.pcs) }}</td>
        </tr>
        <tr>
          <td class="tp-label">Touch</td>
          <td class="tp-value">{{ formatNumber(printingRow.touch) }}</td>
        </tr>
        <tr>
          <td class="tp-label">Wastage</td>
          <td class="tp-value">{{ formatNumber(printingRow.wastage) }}</td>
        </tr>
        <tr>
          <td class="tp-label">Purity</td>
          <td class="tp-value">{{ formatNumber(printingRow.purity) }}</td>
        </tr>
        <tr>
          <td colspan="2" class="tp-parties">{{ fromToLine(printingRow) }}</td>
        </tr>
        <tr v-if="printingRow.remarks">
          <td class="tp-label">Remarks</td>
          <td class="tp-value">{{ printingRow.remarks }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!--
    Print-only report for the current page of the table. Same isolation
    pattern, different body class, so the row receipt above and this can
    never both be visible for the same print.
  -->
  <div class="txn-print-root txn-print-table hidden">
    <table>
      <caption>
        Transaction History — {{ headName }}
        <span class="tp-stamp">Printed {{ printedAt }}</span>
      </caption>
      <thead>
        <tr>
          <th>Item</th>
          <th>Type</th>
          <th>Grams</th>
          <th>Pcs</th>
          <th>Touch</th>
          <th>Wastage</th>
          <th>Purity</th>
          <th>Party</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.item_name || '—' }}</td>
          <td>{{ row.stock_type === 'IN' ? 'In' : 'Out' }}</td>
          <td class="tp-num">{{ formatNumber(row.grams) }}</td>
          <td class="tp-num">{{ row.pcs ? formatNumber(row.pcs) : '' }}</td>
          <td class="tp-num">{{ formatNumber(row.touch) }}</td>
          <td class="tp-num">{{ formatNumber(row.wastage) }}</td>
          <td class="tp-num">{{ formatNumber(row.purity) }}</td>
          <td>{{ row.user || '—' }}</td>
          <td>{{ row.remarks || '—' }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td>Total</td>
          <td></td>
          <td class="tp-num">{{ formatNumber(totals.grams) }}</td>
          <td class="tp-num">{{ formatNumber(totals.pcs) }}</td>
          <td></td>
          <td></td>
          <td class="tp-num">{{ formatNumber(totals.purity) }}</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>

<!--
  Not scoped: the @media print rule has to reach `body *` to hide the rest
  of the app, which a scoped style cannot do. Everything else is namespaced
  under .txn-print-root so nothing leaks onto the normal page.
-->
<style>
.txn-print-root table {
  border-collapse: collapse;
  color: #000;
  font-size: 11px;
}

.txn-print-root:not(.txn-print-table) table {
  width: 2.9in;
}

.txn-print-root th,
.txn-print-root td {
  border: 1px solid #000;
  padding: 2px 6px;
}

.txn-print-root .tp-title,
.txn-print-root .tp-stamp,
.txn-print-root .tp-parties {
  text-align: center;
  font-weight: 700;
}

.txn-print-root .tp-label {
  font-weight: 700;
  width: 40%;
}

.txn-print-root .tp-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.txn-print-root.txn-print-table caption {
  caption-side: top;
  margin-bottom: 6px;
  text-align: left;
  font-weight: 700;
}

.txn-print-root.txn-print-table .tp-stamp {
  display: block;
  font-weight: 400;
  font-size: 10px;
}

@media print {
  body.printing-txn-row *,
  body.printing-txn-table * {
    visibility: hidden;
  }

  body.printing-txn-row .txn-print-root:not(.txn-print-table),
  body.printing-txn-row .txn-print-root:not(.txn-print-table) *,
  body.printing-txn-table .txn-print-root.txn-print-table,
  body.printing-txn-table .txn-print-root.txn-print-table * {
    visibility: visible;
  }

  /* Beats Tailwind's `.hidden` on specificity, so each slip only ever
     appears for its own print and never on screen. */
  body.printing-txn-row .txn-print-root:not(.txn-print-table),
  body.printing-txn-table .txn-print-root.txn-print-table {
    display: block;
    position: fixed;
    inset: 0;
    margin: 0;
    padding: 0;
  }
}
</style>
