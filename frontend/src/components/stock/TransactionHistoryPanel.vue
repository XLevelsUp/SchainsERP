<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Printer, EyeOff } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DirectionTag from '@/components/ui/DirectionTag.vue'
import StockReceiptPrintModal from '@/components/stock/StockReceiptPrintModal.vue'
import StockHistoryPrintModal from '@/components/stock/StockHistoryPrintModal.vue'
import { stockHistoryApi } from '@/lib/stockHistoryApi'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type {
  Item,
  StockHistoryQuery,
  StockHistoryRow,
  StockHistoryTotals,
  UserDetailListItem,
} from '@/types'

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
| Printing. Both buttons open a print preview that calls only the endpoint
| that owns its data, then prints just that sheet:
|   - the row button → StockReceiptPrintModal → GET /stock/reports/id-wise
|     for that stock id. It is the only stock endpoint keyed by one id, and
|     it carries what /stock-details/history lacks: the row's own added_at
|     and both party names from given_by/given_to. The backend has no
|     per-row OB/CB for a single id, so the legacy "Excess OB/CB" lines are
|     still not printed (BACKEND_TODO.md §15).
|   - the header button → StockHistoryPrintModal → GET /stock-details/history
|     re-requested with the exact query this table last loaded (buildQuery),
|     so it prints the page on screen.
|
| `users` (the same list StockManagementView loads for UserPickerPanel) is
| only used to resolve the viewed-as name for the panel title and the
| history sheet's caption.
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
  defineProps<{
    items: Item[]
    employeeId?: number | null
    users?: UserDetailListItem[]
    reloadItems?: () => unknown
  }>(),
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

const hasFilters = computed(
  () => typeFilter.value !== null || itemFilter.value !== null || fromDate.value !== '' || toDate.value !== '',
)

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

const rangeLabel = computed(() => {
  if (total.value === 0) return '0 of 0'
  const from = (page.value - 1) * PER_PAGE + 1
  const to = Math.min(page.value * PER_PAGE, total.value)
  return `${from}–${to} of ${total.value}`
})

// The table's query, also handed to the history print so it prints exactly
// the page on screen.
function buildQuery(sessionUserId: number): StockHistoryQuery {
  return {
    // Report from the picked user's perspective when one is selected — the
    // backend derives each row's IN/OUT direction and counterparty name from
    // head_id, so this has to follow the picker, not the session. Falls back
    // to the logged-in head when nothing is picked. employee_id stays for an
    // unchanged payload shape; it duplicates head_id and is a no-op filter.
    head_id: props.employeeId ?? sessionUserId,
    employee_id: props.employeeId ?? undefined,
    type: typeFilter.value ?? undefined,
    item_id: itemFilter.value ?? undefined,
    from_date: fromDate.value || undefined,
    to_date: toDate.value || undefined,
    page_size: PER_PAGE,
    page: page.value,
  }
}

async function load() {
  if (!auth.user) return
  isLoading.value = true
  loadError.value = ''
  // A selection only ever refers to rows currently on screen. Dropping it on
  // every load stops a filter or page change from carrying hidden-away ids
  // into the next Hide.
  selectedIds.value = new Set()
  try {
    const result = await stockHistoryApi.list(buildQuery(auth.user.user_id))
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

// Print previews — see the header comment for which endpoint each calls.
// The receipt keeps the clicked row for its pcs, which id-wise does not
// return; the history sheet gets the query as it stood when Print was hit.
const receiptRow = ref<StockHistoryRow | null>(null)
const historyPrintQuery = ref<StockHistoryQuery | null>(null)

function printRow(row: StockHistoryRow) {
  receiptRow.value = row
}

function printTable() {
  if (auth.user) historyPrintQuery.value = buildQuery(auth.user.user_id)
}

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-2.5">
      <h2 class="flex min-w-0 items-baseline gap-2 text-sm font-semibold text-slate-900">
        Transaction History
        <!-- Whose ledger this is — the picked user, or the signed-in head. -->
        <span class="truncate text-xs font-normal text-slate-500">{{ headName }}</span>
      </h2>
      <div class="flex shrink-0 items-center gap-2">
        <button
          v-if="selectedCount > 0"
          type="button"
          class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-outflow-700 hover:bg-outflow-50"
          @click="confirmingHide = true"
        >
          <EyeOff class="h-3.5 w-3.5" /> Hide {{ selectedCount }} selected
        </button>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-ink-900 disabled:cursor-not-allowed disabled:opacity-40"
          aria-label="Print transaction history"
          title="Print this page of the history"
          :disabled="rows.length === 0"
          @click="printTable"
        >
          <Printer class="h-4 w-4" />
        </button>
      </div>
    </div>

    <div
      v-if="confirmingHide"
      class="border-b border-outflow-100 bg-outflow-50 px-4 py-3 text-xs text-outflow-700"
      role="alertdialog"
      aria-labelledby="hide-confirm-title"
    >
      <p id="hide-confirm-title" class="font-semibold">Hide {{ selectedCount }} transaction(s)?</p>
      <p class="mt-1 text-slate-700">
        They stay in the ledger and keep affecting balances — they just stop appearing in this
        history and in the metal picker. Any selected row drawn from a parent lot hides that lot
        too. <strong class="text-outflow-700">There is no way to un-hide from this app.</strong>
      </p>
      <div class="mt-2 flex items-center gap-2">
        <BaseButton variant="secondary" :disabled="isHiding" @click="confirmingHide = false">
          Cancel
        </BaseButton>
        <BaseButton variant="outflow" :disabled="isHiding" @click="confirmHide">
          {{ isHiding ? 'Hiding…' : 'Hide them' }}
        </BaseButton>
      </div>
    </div>

    <div class="grid gap-2 border-b border-slate-200 bg-slate-50/60 px-4 py-2 sm:grid-cols-4">
      <BaseSelect
        v-model="typeFilter"
        :options="typeOptions"
        placeholder="Select Type…"
        size="sm"
      />
      <BaseSelect
        v-model="itemFilter"
        :options="itemOptions"
        :reload="reloadItems"
        placeholder="Select Item…"
        size="sm"
      />
      <BaseInput v-model="fromDate" type="date" size="sm" placeholder="From date" clearable />
      <BaseInput v-model="toDate" type="date" size="sm" placeholder="To date" clearable />
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>

    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">
      Loading transactions…
    </div>

    <template v-else>
      <!--
        Vertical scroll after roughly four rows, so this panel stays close to
        Head Stocks' height in the column beside it rather than running on and
        pushing the two columns out of alignment.

        max-height, not a fixed height: a page with one or two rows stays
        short instead of leaving dead space. The value is in rem rather than a
        row count because rows wrap to different heights (a "Gold Chain =>
        Gold Ring" item cell is two lines, a plain "Metal" is one), so "four
        rows" is approximate by nature.

        The header is sticky so Total and the column labels stay put while the
        rows move under them — the totals are for the whole filtered set, not
        the visible slice, so scrolling them out of view would be worse than
        the scrolling itself.

        Printing is unaffected: it renders StockHistoryPrintModal's own
        sheet, never this table.
      -->
      <div class="max-h-60 overflow-y-auto overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="sticky top-0 z-10 bg-white">
            <tr class="border-b border-slate-200 bg-gold-50/60 font-semibold text-ink-900">
              <td colspan="5" class="px-4 py-2 text-xs">Total <span class="font-normal text-slate-500">· all pages</span></td>
              <td class="px-3 py-2 text-right font-figure tabular-nums">{{ formatNumber(totals.grams) }}</td>
              <td class="px-3 py-2 text-right font-figure tabular-nums">{{ formatNumber(totals.pcs) }}</td>
              <td class="px-3 py-2"></td>
              <td class="px-3 py-2"></td>
              <td class="px-4 py-2 text-right font-figure tabular-nums text-gold-700">{{ formatNumber(totals.purity) }}</td>
              <td colspan="3" class="px-4 py-2"></td>
            </tr>
            <!--
              Bottom border lives here, not on <thead>: tbody's divide-y gives
              no top border to its first row, so without this the rows would
              slide right up against the sticky header with no edge between.
            -->
            <tr
              class="border-b border-slate-200 font-figure text-[11px] font-semibold tracking-[0.06em] whitespace-nowrap text-slate-500 uppercase"
            >
              <th scope="col" class="w-8 px-3 py-2 text-left">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 accent-ink-900"
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
              <th scope="col" class="px-3 py-2 text-right">Touch %</th>
              <th scope="col" class="px-3 py-2 text-right">Wastage</th>
              <th scope="col" class="px-4 py-2 text-right">Purity g</th>
              <th scope="col" class="px-3 py-2 text-left">User</th>
              <th scope="col" class="px-4 py-2 text-left">Remarks</th>
              <th scope="col" class="px-4 py-2 text-center">Print</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="rows.length === 0">
              <td colspan="13" class="px-4 py-8 text-center text-slate-500">
                {{ hasFilters ? 'No transactions match these filters.' : 'No transactions recorded yet.' }}
              </td>
            </tr>
            <tr
              v-for="(row, index) in rows"
              :key="row.id"
              :class="selectedIds.has(row.id) ? 'bg-gold-50' : 'hover:bg-slate-50'"
            >
              <td class="px-3 py-1.5">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 accent-ink-900"
                  :aria-label="`Select transaction ${row.id}`"
                  :checked="selectedIds.has(row.id)"
                  @change="toggleRow(row.id)"
                />
              </td>
              <td class="px-4 py-1.5 font-figure tabular-nums text-slate-400">{{ (page - 1) * PER_PAGE + index + 1 }}</td>
              <td class="px-3 py-1.5 font-figure tabular-nums text-slate-500">{{ row.id }}</td>
              <td class="px-3 py-1.5 whitespace-nowrap text-slate-800">{{ row.item_name || '—' }}</td>
              <td class="px-3 py-1.5">
                <DirectionTag :direction="row.stock_type === 'IN' ? 'in' : 'out'" variant="pill" />
              </td>
              <td class="px-3 py-1.5 text-right font-figure tabular-nums text-slate-900">{{ formatNumber(row.grams) }}</td>
              <td class="px-3 py-1.5 text-right font-figure tabular-nums text-slate-600">{{ formatNumber(row.pcs) }}</td>
              <td class="px-3 py-1.5 text-right font-figure tabular-nums text-slate-600">{{ formatNumber(row.touch) }}</td>
              <td class="px-3 py-1.5 text-right font-figure tabular-nums text-slate-600">{{ formatNumber(row.wastage) }}</td>
              <td class="px-4 py-1.5 text-right font-figure font-semibold tabular-nums text-gold-700">
                {{ formatNumber(row.purity) }}
              </td>
              <td class="px-3 py-1.5 whitespace-nowrap text-slate-700">{{ row.user || '—' }}</td>
              <td class="max-w-[12rem] truncate px-4 py-1.5 text-slate-500" :title="row.remarks || undefined">{{ row.remarks || '—' }}</td>
              <td class="px-4 py-1.5 text-center">
                <button
                  type="button"
                  class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-ink-900"
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
        <span class="font-figure tabular-nums">{{ rangeLabel }}</span>
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

  <StockReceiptPrintModal
    v-if="receiptRow"
    :stock-id="receiptRow.id"
    :pcs="receiptRow.pcs"
    @close="receiptRow = null"
  />

  <StockHistoryPrintModal
    v-if="historyPrintQuery"
    :query="historyPrintQuery"
    :head-name="headName"
    @close="historyPrintQuery = null"
  />
</template>
