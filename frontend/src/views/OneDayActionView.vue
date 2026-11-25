<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ChevronDown, Download, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { reportApi } from '@/lib/reportApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { rolesApi } from '@/lib/rolesApi'
import { userOptionLabel } from '@/lib/userLabel'
import { todayDateInputValue, formatDateTime } from '@/lib/date'
import { cashMethodLabel, cashTypeBadge } from '@/lib/cashTxnTypes'
import {
  oneDayActionCsv,
  oneDayActionQuery,
  type OneDayActionFilterValues,
} from '@/lib/oneDayAction'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { OneDayActionSource, ReportHead } from '@/lib/reportSources'
import type {
  Item,
  OneDayActionCashRow,
  OneDayActionSection,
  OneDayActionSectionName,
  OneDayActionStockRow,
  Role,
  UserDetailListItem,
} from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| One Day Action Report — GET /report/one-day-action (app),
| /admin/reports/one-day-action (console)
|--------------------------------------------------------------------------
| The legacy screen (2026-11-25): two sections, Stock Report and Cash
| Management Report, each with its Total and its own Load More. Today by
| default; leave To Date empty for one day.
|
| Filters as on the legacy screen: User (who keyed the entry), Items (stock
| only), Stock Type (IN / OUT; on cash the Receive / Pay groups), Given By,
| Given To, Role (either party has it). Export is one CSV of both sections.
|
| The app scopes the report to the signed-in head. The console passes its
| own source and `heads`: every head unless the Head filter picks one.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  source?: OneDayActionSource
  heads?: ReportHead[]
}>()

const auth = useAuthStore()

const source = computed<OneDayActionSource>(
  () =>
    props.source ?? {
      fetch: (query) => reportApi.getOneDayAction(query, auth.user?.user_id ?? 1),
      loadUsers: () => userDetailsApi.list(undefined, 'stock'),
      loadItems: () => itemsApi.list(),
      loadRoles: () => rolesApi.list(),
    },
)

const DESCRIPTION = "The day's stock movements and cash transactions."
const ALL_HEADS_DESCRIPTION = "The day's stock movements and cash transactions, for every head or the one you pick."

const headOptions = computed(() => (props.heads ?? []).map((h) => ({ value: h.id, label: h.name })))
const stockTypeOptions: { value: 'IN' | 'OUT'; label: string }[] = [
  { value: 'IN', label: 'IN' },
  { value: 'OUT', label: 'OUT' },
]

function emptyFilters(): OneDayActionFilterValues {
  return {
    head_id: null,
    employee_id: null,
    item_id: null,
    type: null,
    given_by: null,
    given_to: null,
    role_id: null,
    from_date: todayDateInputValue(),
    to_date: '',
  }
}

const filters = reactive<OneDayActionFilterValues>(emptyFilters())

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])
const roles = ref<Role[]>([])

const userOptions = computed(() => users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })))
// GET /items is already in Orders No order; don't re-sort.
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))
const roleOptions = computed(() => roles.value.map((r) => ({ value: r.id, label: r.role })))

async function loadLookups() {
  const [usersData, itemsData, rolesData] = await Promise.allSettled([
    source.value.loadUsers(),
    source.value.loadItems(),
    source.value.loadRoles(),
  ])
  // Non-fatal: a filter just offers fewer options.
  if (usersData.status === 'fulfilled') users.value = usersData.value
  if (itemsData.status === 'fulfilled') items.value = itemsData.value
  if (rolesData.status === 'fulfilled') roles.value = rolesData.value
}

async function reloadUsers() {
  users.value = await source.value.loadUsers()
}
async function reloadItems() {
  items.value = await source.value.loadItems()
}
async function reloadRoles() {
  roles.value = await source.value.loadRoles()
}

/* ---------------------------------------------------------------- sections */

interface SectionState<Row> {
  rows: Row[]
  total: number
  pageNo: number
  totalPages: number
  open: boolean
  loadingMore: boolean
}

function emptySection<Row>(): SectionState<Row> {
  // Sections start closed (2026-11-25); the header still shows the Total.
  return { rows: [], total: 0, pageNo: 1, totalPages: 1, open: false, loadingMore: false }
}

const stock = reactive(emptySection<OneDayActionStockRow>()) as SectionState<OneDayActionStockRow>
const cash = reactive(emptySection<OneDayActionCashRow>()) as SectionState<OneDayActionCashRow>

const isLoading = ref(false)
const isExporting = ref(false)
const loadError = ref('')
const hasSearched = ref(false)
// The filters the shown rows were loaded with, so Load More and Export
// page against the same search even if the filters were edited since.
let searchedFilters: OneDayActionFilterValues = { ...filters }
// Bumped by every search; a page that arrives for an older search is dropped.
let searchSeq = 0

function applySection<Row>(state: SectionState<Row>, data: OneDayActionSection<Row> | undefined, append: boolean) {
  if (!data) return
  state.rows = append ? [...state.rows, ...data.records] : data.records
  state.total = data.total_count
  state.pageNo = data.pagination.current_page
  state.totalPages = data.pagination.total_pages
}

async function runSearch() {
  const seq = ++searchSeq
  isLoading.value = true
  loadError.value = ''
  searchedFilters = { ...filters }
  try {
    const result = await source.value.fetch(oneDayActionQuery(searchedFilters, { withHead: !!props.heads }))
    if (seq !== searchSeq) return
    applySection(stock, result.stock, false)
    applySection(cash, result.cash, false)
    hasSearched.value = true
  } catch (err) {
    if (seq !== searchSeq) return
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    if (seq === searchSeq) isLoading.value = false
  }
}

function sectionState(name: OneDayActionSectionName) {
  return name === 'stock' ? stock : cash
}

/** The next page of one section. Returns false when it failed or went stale. */
async function fetchNextPage(name: OneDayActionSectionName): Promise<boolean> {
  const state = sectionState(name)
  const seq = searchSeq
  const result = await source.value.fetch(
    oneDayActionQuery(searchedFilters, { withHead: !!props.heads, section: name, pageNo: state.pageNo + 1 }),
  )
  if (seq !== searchSeq) return false
  if (name === 'stock') applySection(stock, result.stock, true)
  else applySection(cash, result.cash, true)
  return true
}

async function loadMore(name: OneDayActionSectionName) {
  const state = sectionState(name)
  if (state.pageNo >= state.totalPages || state.loadingMore) return
  state.loadingMore = true
  loadError.value = ''
  try {
    await fetchNextPage(name)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more rows.'
  } finally {
    state.loadingMore = false
  }
}

function clearFilters() {
  Object.assign(filters, emptyFilters())
  runSearch()
}

/* ------------------------------------------------------------------ export */

// One CSV of both sections. Pages not loaded yet are loaded first (they then
// show on screen too), so the file holds the whole search, not just what
// was scrolled to.
async function exportCsv() {
  if (isExporting.value) return
  isExporting.value = true
  loadError.value = ''
  try {
    for (const name of ['stock', 'cash'] as const) {
      while (sectionState(name).pageNo < sectionState(name).totalPages) {
        if (!(await fetchNextPage(name))) return
      }
    }
    const blob = new Blob([oneDayActionCsv(stock.rows, cash.rows)], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `one-day-action-${searchedFilters.from_date || todayDateInputValue()}.csv`
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Export failed.'
  } finally {
    isExporting.value = false
  }
}

/* ------------------------------------------------------------------ tables */

const stockColumns: DataTableColumn<OneDayActionStockRow>[] = [
  { key: 'added_at', label: 'Time' },
  { key: 'id', label: 'Stock ID' },
  { key: 'stock_type', label: 'Stock Type' },
  { key: 'entry_type', label: 'Entry' },
  { key: 'given_by', label: 'Given By → Given To' },
  { key: 'item_name', label: 'Item' },
  { key: 'grams', label: 'Grams', align: 'right' },
  { key: 'touch', label: 'Touch', align: 'right' },
  { key: 'purity', label: 'Purity', align: 'right' },
  { key: 'waste_total', label: 'Wastage', align: 'right' },
  { key: 'remarks', label: 'Remarks' },
]

const cashColumns: DataTableColumn<OneDayActionCashRow>[] = [
  { key: 'added_at', label: 'Time' },
  { key: 'id', label: 'Txn ID' },
  { key: 'type', label: 'Type' },
  { key: 'given_by', label: 'Given By → Given To' },
  { key: 'category_name', label: 'Category' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'payment_method', label: 'Method' },
  { key: 'remarks', label: 'Remarks' },
]

// The endpoint's null sentinel is the literal "-".
function formatRowTime(value: string): string {
  if (!value || value === '-') return '—'
  return formatDateTime(value)
}

function toNumber(value: number | string | null): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}

// Weights keep the 4-decimal precision of the other stock reports; cash 2.
function formatGrams(value: number | string | null): string {
  if (value === null || value === undefined || value === '') return '—'
  return toNumber(value).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function formatAmount(value: number | string | null): string {
  if (value === null || value === undefined || value === '') return '—'
  return toNumber(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function flowClass(type: string): string {
  if (type === 'IN') return 'bg-inflow-50 text-inflow-700'
  if (type === 'OUT') return 'bg-outflow-50 text-outflow-700'
  return 'bg-slate-100 text-slate-600'
}

// Totals only once every row of the section is loaded, so they never
// describe part of the search.
const stockFullyLoaded = computed(() => stock.rows.length >= stock.total)
const cashFullyLoaded = computed(() => cash.rows.length >= cash.total)
const stockGrams = computed(() => stock.rows.reduce((sum, r) => sum + toNumber(r.grams), 0))
const stockPurity = computed(() => stock.rows.reduce((sum, r) => sum + toNumber(r.purity), 0))
const cashIn = computed(() => cash.rows.filter((r) => r.flow === 'IN').reduce((sum, r) => sum + toNumber(r.amount), 0))
const cashOut = computed(() => cash.rows.filter((r) => r.flow === 'OUT').reduce((sum, r) => sum + toNumber(r.amount), 0))

onMounted(() => {
  loadLookups()
  runSearch()
})
</script>

<template>
  <div>
    <PageHeader title="One Day Action Report" :description="heads ? ALL_HEADS_DESCRIPTION : DESCRIPTION">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="runSearch">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <BaseSelect
          v-if="heads"
          id="report_head"
          v-model="filters.head_id"
          label="Head"
          size="sm"
          placeholder="All heads…"
          :options="headOptions"
          class="sm:col-span-2 lg:col-span-3"
        />
        <BaseSelect
          id="oda_user"
          v-model="filters.employee_id"
          label="User (entered by)"
          size="sm"
          placeholder="Select user…"
          :options="userOptions"
          :reload="reloadUsers"
        />
        <BaseSelect
          id="oda_item"
          v-model="filters.item_id"
          label="Items"
          size="sm"
          placeholder="Select items…"
          :options="itemOptions"
          :reload="reloadItems"
        />
        <BaseSelect
          id="oda_stock_type"
          v-model="filters.type"
          label="Stock Type"
          size="sm"
          placeholder="Select stock type…"
          :options="stockTypeOptions"
        />
        <BaseSelect
          id="oda_given_by"
          v-model="filters.given_by"
          label="Given By"
          size="sm"
          placeholder="Select given by…"
          :options="userOptions"
          :reload="reloadUsers"
        />
        <BaseSelect
          id="oda_given_to"
          v-model="filters.given_to"
          label="Given To"
          size="sm"
          placeholder="Select given to…"
          :options="userOptions"
          :reload="reloadUsers"
        />
        <BaseSelect
          id="oda_role"
          v-model="filters.role_id"
          label="Role"
          size="sm"
          placeholder="Select role…"
          :options="roleOptions"
          :reload="reloadRoles"
        />
        <BaseInput id="oda_from_date" v-model="filters.from_date" label="From Date" type="date" size="sm" clearable />
        <BaseInput id="oda_to_date" v-model="filters.to_date" label="To Date" type="date" size="sm" clearable />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Leave To Date empty for a single day. Items narrows the stock section only. Stock Type on cash: IN is
        Receive, Sale Gold and the other incoming types; OUT is Pay, Purchase Gold and the other outgoing ones.
      </p>

      <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
        <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">Clear</BaseButton>
        <BaseButton
          variant="secondary"
          type="button"
          :icon="Download"
          :disabled="isExporting || isLoading || (stock.total === 0 && cash.total === 0)"
          @click="exportCsv"
        >
          {{ isExporting ? 'Exporting…' : 'Export' }}
        </BaseButton>
        <BaseButton type="button" :disabled="isLoading" @click="runSearch">
          {{ isLoading ? 'Loading…' : 'Search' }}
        </BaseButton>
      </div>
    </BaseCard>

    <p v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </p>

    <!-- Stock Report -->
    <section class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="oda_stock_title">
      <button
        type="button"
        class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-left hover:bg-slate-50"
        :aria-expanded="stock.open"
        aria-controls="oda_stock_body"
        @click="stock.open = !stock.open"
      >
        <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="{ '-rotate-90': !stock.open }" />
        <h2 id="oda_stock_title" class="text-sm font-semibold text-slate-900">Stock Report</h2>
        <span class="rounded bg-inflow-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-inflow-700">
          Total: {{ hasSearched ? stock.total : '…' }}
        </span>
        <span v-if="hasSearched && stock.total > 0 && stockFullyLoaded" class="ml-auto text-xs tabular-nums text-slate-500">
          Grams {{ formatGrams(stockGrams) }} · Purity {{ formatGrams(stockPurity) }}
        </span>
      </button>
      <div v-show="stock.open" id="oda_stock_body" class="border-t border-slate-200 p-3">
        <DataTable
          :columns="stockColumns"
          :rows="stock.rows"
          :empty-message="isLoading ? 'Loading…' : 'No stock movements for these filters.'"
        >
          <template #added_at="{ row }">
            <span class="whitespace-nowrap">{{ formatRowTime(row.added_at) }}</span>
          </template>
          <template #id="{ row }">
            <span class="tabular-nums text-slate-700">{{ row.id }}</span>
          </template>
          <template #stock_type="{ row }">
            <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold" :class="flowClass(row.stock_type)">
              {{ row.stock_type }}
            </span>
          </template>
          <template #entry_type="{ row }">
            <span class="text-xs whitespace-nowrap text-slate-500">{{ row.entry_type }}</span>
          </template>
          <template #given_by="{ row }">
            <span class="whitespace-nowrap">{{ row.given_by }} &rarr; {{ row.given_to }}</span>
          </template>
          <template #item_name="{ row }">
            <span class="whitespace-nowrap">{{ row.item_name }}</span>
          </template>
          <template #grams="{ row }">
            <span class="font-medium tabular-nums">{{ formatGrams(row.grams) }}</span>
          </template>
          <template #touch="{ row }">
            <span class="tabular-nums">{{ formatGrams(row.touch) }}</span>
          </template>
          <template #purity="{ row }">
            <span class="tabular-nums">{{ formatGrams(row.purity) }}</span>
          </template>
          <template #waste_total="{ row }">
            <span class="tabular-nums text-slate-500">{{ formatGrams(row.waste_total) }}</span>
          </template>
          <template #remarks="{ row }">
            <span class="text-slate-500">{{ row.remarks || '—' }}</span>
          </template>
        </DataTable>
        <div v-if="stock.pageNo < stock.totalPages" class="mt-3 flex items-center justify-center gap-3">
          <span class="text-xs text-slate-500">{{ stock.rows.length }} of {{ stock.total }}</span>
          <BaseButton variant="secondary" type="button" :disabled="stock.loadingMore" @click="loadMore('stock')">
            {{ stock.loadingMore ? 'Loading…' : 'Load More' }}
          </BaseButton>
        </div>
      </div>
    </section>

    <!-- Cash Management Report -->
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="oda_cash_title">
      <button
        type="button"
        class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-left hover:bg-slate-50"
        :aria-expanded="cash.open"
        aria-controls="oda_cash_body"
        @click="cash.open = !cash.open"
      >
        <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="{ '-rotate-90': !cash.open }" />
        <h2 id="oda_cash_title" class="text-sm font-semibold text-slate-900">Cash Management Report</h2>
        <span class="rounded bg-inflow-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-inflow-700">
          Total: {{ hasSearched ? cash.total : '…' }}
        </span>
        <span v-if="hasSearched && cash.total > 0 && cashFullyLoaded" class="ml-auto text-xs tabular-nums text-slate-500">
          IN {{ formatAmount(cashIn) }} · OUT {{ formatAmount(cashOut) }}
        </span>
      </button>
      <div v-show="cash.open" id="oda_cash_body" class="border-t border-slate-200 p-3">
        <DataTable
          :columns="cashColumns"
          :rows="cash.rows"
          :empty-message="isLoading ? 'Loading…' : 'No cash transactions for these filters.'"
        >
          <template #added_at="{ row }">
            <span class="whitespace-nowrap">{{ formatRowTime(row.added_at) }}</span>
          </template>
          <template #id="{ row }">
            <span class="tabular-nums text-slate-700">{{ row.id }}</span>
          </template>
          <template #type="{ row }">
            <span
              class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
              :class="cashTypeBadge(row.type).class"
            >
              {{ cashTypeBadge(row.type).label }}
            </span>
          </template>
          <template #given_by="{ row }">
            <span class="whitespace-nowrap">{{ row.given_by }} &rarr; {{ row.given_to }}</span>
          </template>
          <template #category_name="{ row }">
            <span class="whitespace-nowrap text-slate-600">{{ row.category_name || '—' }}</span>
          </template>
          <template #amount="{ row }">
            <span class="font-semibold tabular-nums text-slate-900">{{ formatAmount(row.amount) }}</span>
          </template>
          <template #payment_method="{ row }">
            <span class="whitespace-nowrap">{{ cashMethodLabel(row.payment_method, row.bank_name) }}</span>
          </template>
          <template #remarks="{ row }">
            <span class="text-slate-500">{{ row.remarks || '—' }}</span>
          </template>
        </DataTable>
        <div v-if="cash.pageNo < cash.totalPages" class="mt-3 flex items-center justify-center gap-3">
          <span class="text-xs text-slate-500">{{ cash.rows.length }} of {{ cash.total }}</span>
          <BaseButton variant="secondary" type="button" :disabled="cash.loadingMore" @click="loadMore('cash')">
            {{ cash.loadingMore ? 'Loading…' : 'Load More' }}
          </BaseButton>
        </div>
      </div>
    </section>
  </div>
</template>
