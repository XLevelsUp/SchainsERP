<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, TriangleAlert } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { reportApi } from '@/lib/reportApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { todayDateInputValue } from '@/lib/date'
import { ApiError } from '@/lib/api'
import type { Item, OneDayActionRow, UserDetailListItem } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| One Day Action — GET /report/one-day-action (PR #37, doc section 47)
|--------------------------------------------------------------------------
| One day's stock movements and cash transactions in a single feed, the
| replacement for the legacy Yii2 "One Day Action" screen.
|
| Three properties of the endpoint shape this view, all flagged to the
| backend rather than worked around silently:
|
| 1. Cash amounts arrive in the `grams` field (ReportService reuses the
|    column on purpose). Totalling that column across mixed rows would add
|    rupees to grams, so the summary splits by record_type and the table
|    formats the same column two ways. #24.
|
| 2. `added_at` is "DD-MM-YYYY HH:mm:ss" — the only endpoint in the app
|    that does not use "YYYY-MM-DD HH:mm:ss". `new Date()` returns Invalid
|    Date for it and lib/date's formatDateTime would pass it through raw,
|    so parseRowDate below does it explicitly. #25.
|
| 3. page_no/page_size are applied to the stock query and the cash query
|    separately, then the two lists are concatenated. Appending page 2 onto
|    page 1 still yields the correct *set* of rows, but the merged order is
|    wrong, so every append re-sorts client-side on the parsed date. The
|    count line shows the two source totals rather than inventing one
|    combined total. #23.
|
| The report is not scoped to the signed-in head — the service takes a
| $headId and never uses it — so this shows every user's activity. That is
| the banner at the top, not something the frontend can filter around:
| `employee_id` filters `added_by` (who keyed the entry), which answers a
| different question, hence its "Entered by" label.
|--------------------------------------------------------------------------
*/

// The backend's own default. Applied per source, so one "page" can hold up
// to twice this many rows.
const PAGE_SIZE = 500

const flowOptions: { value: 'IN' | 'OUT'; label: string }[] = [
  { value: 'IN', label: 'IN' },
  { value: 'OUT', label: 'OUT' },
]

// entry_type values the service actually queries for. Cash rows always come
// back as entry_type "CASH" and are unaffected by this filter.
const entryTypeOptions = [
  { value: 'NORMAL', label: 'NORMAL' },
  { value: 'SALES', label: 'SALES' },
  { value: 'HEADTOHEAD', label: 'HEADTOHEAD' },
  { value: 'OUT_CASH_CONVERTER', label: 'OUT_CASH_CONVERTER' },
  { value: 'IN_CASH_CONVERTER', label: 'IN_CASH_CONVERTER' },
  { value: 'GOLDCASHCONVERSION', label: 'GOLDCASHCONVERSION' },
  { value: 'CashToGold', label: 'CashToGold' },
]

const filters = reactive({
  from_date: todayDateInputValue(),
  to_date: '',
  employee_id: null as number | null,
  given_by_given_to: null as number | null,
  item_id: null as number | null,
  type: null as 'IN' | 'OUT' | null,
  stocktype: null as string | null,
})

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() =>
  items.value.map((i) => ({ value: i.item_id, label: i.item_name })),
)

const rows = ref<OneDayActionRow[]>([])
const stockCount = ref(0)
const cashCount = ref(0)
const pageNo = ref(1)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const loadError = ref('')
const hasSearched = ref(false)

const loadedTotal = computed(() => stockCount.value + cashCount.value)
const hasMore = computed(() => rows.value.length < loadedTotal.value)

const columns: DataTableColumn<OneDayActionRow>[] = [
  { key: 'added_at', label: 'Time' },
  { key: 'record_type', label: 'Source' },
  { key: 'stock_type', label: 'Flow' },
  { key: 'entry_type', label: 'Entry' },
  { key: 'given_by', label: 'Given By / Given To' },
  { key: 'item_name', label: 'Item' },
  { key: 'grams', label: 'Grams / Amount' },
  { key: 'touch', label: 'Touch' },
  { key: 'purity', label: 'Purity' },
  { key: 'waste_total', label: 'Wastage' },
  { key: 'remarks', label: 'Remarks' },
]

// "DD-MM-YYYY HH:mm:ss" (or "-" when the source timestamp was null).
// Returns null rather than an Invalid Date so callers can decide.
function parseRowDate(value: string): Date | null {
  const match = /^(\d{2})-(\d{2})-(\d{4})[ T](\d{2}):(\d{2}):(\d{2})$/.exec(value ?? '')
  if (!match) return null
  const [, dd, mm, yyyy, hh, min, ss] = match
  const date = new Date(
    Number(yyyy),
    Number(mm) - 1,
    Number(dd),
    Number(hh),
    Number(min),
    Number(ss),
  )
  return Number.isNaN(date.getTime()) ? null : date
}

function pad(value: number) {
  return String(value).padStart(2, '0')
}

// The same "DD/MM/YY HH:MM" shape lib/date's formatDateTime produces, so
// this column reads identically to every other date column in the app.
function formatRowTime(value: string): string {
  const date = parseRowDate(value)
  if (!date) return value || '—'
  const stamp = `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${pad(date.getFullYear() % 100)}`
  return `${stamp} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function isCash(row: OneDayActionRow) {
  return row.record_type === 'CASH'
}

function toNumber(value: number | string | null): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}

// Weights keep the 4-decimal precision the other stock reports use; cash
// amounts get 2, matching the cash module.
function formatGrams(value: number | string | null): string {
  if (value === null || value === undefined || value === '') return '—'
  return toNumber(value).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function formatAmount(value: number | string | null): string {
  if (value === null || value === undefined || value === '') return '—'
  return toNumber(value).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

// Split deliberately: `grams` means grams on STOCK rows and rupees on CASH
// rows, so there is no single total that would mean anything.
const stockGramsTotal = computed(() =>
  rows.value.filter((r) => !isCash(r)).reduce((sum, r) => sum + toNumber(r.grams), 0),
)
const stockPurityTotal = computed(() =>
  rows.value.filter((r) => !isCash(r)).reduce((sum, r) => sum + toNumber(r.purity), 0),
)
const cashAmountTotal = computed(() =>
  rows.value.filter(isCash).reduce((sum, r) => sum + toNumber(r.grams), 0),
)

// Same IN/OUT palette as the stock history and cash tables.
function flowClass(type: string): string {
  if (type === 'IN') return 'bg-emerald-50 text-emerald-700'
  if (type === 'OUT') return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-600'
}

function currentQuery(page: number) {
  return {
    from_date: filters.from_date || undefined,
    // The service defaults to_date to from_date, which is the single-day
    // case this report is named for — only send it when widening a range.
    to_date: filters.to_date || undefined,
    employee_id: filters.employee_id ?? undefined,
    given_by_given_to: filters.given_by_given_to ?? undefined,
    item_id: filters.item_id ?? undefined,
    type: filters.type ?? undefined,
    stocktype: filters.stocktype ?? undefined,
    page_size: PAGE_SIZE,
    page_no: page,
  }
}

// Rows arrive sorted within a page, but pages are concatenated per source,
// so the merged list has to be re-sorted. Undated rows sink to the bottom
// rather than being dropped.
function sortByDateDesc(list: OneDayActionRow[]): OneDayActionRow[] {
  return [...list].sort((a, b) => {
    const aDate = parseRowDate(a.added_at)
    const bDate = parseRowDate(b.added_at)
    if (!aDate && !bDate) return 0
    if (!aDate) return 1
    if (!bDate) return -1
    return bDate.getTime() - aDate.getTime()
  })
}

async function loadLookups() {
  try {
    const [usersData, itemsData] = await Promise.all([
      userDetailsApi.list(undefined, 'stock'),
      itemsApi.list(),
    ])
    users.value = usersData
    items.value = itemsData
  } catch {
    // Non-fatal — the filters just offer fewer options.
  }
}

async function runSearch() {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  pageNo.value = 1
  try {
    const result = await reportApi.getOneDayAction(currentQuery(1))
    rows.value = sortByDateDesc(result.transactions)
    stockCount.value = result.total_stock_count
    cashCount.value = result.total_cash_count
    hasSearched.value = true
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    isLoading.value = false
  }
}

async function loadMore() {
  if (!hasMore.value || isLoadingMore.value) return
  isLoadingMore.value = true
  loadError.value = ''
  try {
    const next = pageNo.value + 1
    const result = await reportApi.getOneDayAction(currentQuery(next))
    rows.value = sortByDateDesc([...rows.value, ...result.transactions])
    stockCount.value = result.total_stock_count
    cashCount.value = result.total_cash_count
    pageNo.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more rows.'
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  filters.from_date = todayDateInputValue()
  filters.to_date = ''
  filters.employee_id = null
  filters.given_by_given_to = null
  filters.item_id = null
  filters.type = null
  filters.stocktype = null
  runSearch()
}

function exportCsv() {
  const header = [
    'ID',
    'Source',
    'Date',
    'Flow',
    'Entry',
    'Given By',
    'Given To',
    'Item',
    'Grams / Amount',
    'Touch',
    'Purity',
    'Wastage %',
    'Wastage Grams',
    'Remarks',
    'Entered By (user id)',
  ]
  const escape = (value: unknown) => `"${String(value ?? '').replace(/"/g, '""')}"`
  const lines = [
    header.join(','),
    ...rows.value.map((r) =>
      [
        r.id,
        r.record_type,
        r.added_at,
        r.stock_type,
        r.entry_type,
        r.given_by,
        r.given_to,
        r.item_name,
        r.grams ?? '',
        r.touch ?? '',
        r.purity ?? '',
        r.waste_total ?? '',
        r.waste_value ?? '',
        r.remarks ?? '',
        r.added_by ?? '',
      ]
        .map(escape)
        .join(','),
    ),
  ]
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `one-day-action-${filters.from_date || todayDateInputValue()}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  loadLookups()
  runSearch()
})
</script>

<template>
  <div>
    <PageHeader
      title="One Day Action"
      description="A day's stock movements and cash transactions in one feed."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="runSearch">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <div
      class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
      <p>
        <strong>Known backend gaps (PENDING_WORK.md #22-25):</strong> this report is not scoped to
        the signed-in head — the service accepts a head id and never uses it, so every user's
        activity is listed. Cash amounts arrive in the <code>grams</code> field, so grams and
        rupees are totalled separately below and never combined. Paging is applied to the stock
        and cash queries separately, which is why the counts are shown per source.
      </p>
    </div>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput v-model="filters.from_date" label="From date" type="date" size="sm" clearable />
        <BaseInput v-model="filters.to_date" label="To date" type="date" size="sm" clearable />
        <BaseSelect
          v-model="filters.employee_id"
          label="Entered by"
          size="sm"
          placeholder="Anyone…"
          :options="userOptions"
        />
        <BaseSelect
          v-model="filters.given_by_given_to"
          label="Party (either side)"
          size="sm"
          placeholder="Anyone…"
          :options="userOptions"
        />
        <BaseSelect
          v-model="filters.item_id"
          label="Item"
          size="sm"
          placeholder="All items…"
          :options="itemOptions"
        />
        <BaseSelect
          v-model="filters.type"
          label="Flow"
          size="sm"
          placeholder="IN and OUT…"
          :options="flowOptions"
        />
        <BaseSelect
          v-model="filters.stocktype"
          label="Entry type"
          size="sm"
          placeholder="All entry types…"
          :options="entryTypeOptions"
        />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Leave "To date" empty for a single day. "Entered by" filters on who keyed the entry
        (<code>added_by</code>), not who the gold or cash moved between — use "Party" for that.
      </p>

      <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
        <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">
          Clear
        </BaseButton>
        <BaseButton
          variant="secondary"
          type="button"
          :disabled="rows.length === 0"
          @click="exportCsv"
        >
          Export CSV
        </BaseButton>
        <BaseButton type="button" :disabled="isLoading" @click="runSearch">
          {{ isLoading ? 'Loading…' : 'Search' }}
        </BaseButton>
      </div>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Stock movements</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">{{ stockCount }}</dd>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Cash transactions</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">{{ cashCount }}</dd>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Grams (loaded stock rows)</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">
          {{ formatGrams(stockGramsTotal) }}
        </dd>
        <dd class="mt-0.5 text-xs tabular-nums text-slate-500">
          Purity {{ formatGrams(stockPurityTotal) }}
        </dd>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Cash (loaded cash rows)</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">
          {{ formatAmount(cashAmountTotal) }}
        </dd>
      </div>
    </div>

    <p v-if="hasSearched" class="mb-2 text-sm text-slate-500">
      Showing <span class="font-medium text-slate-700">{{ rows.length }}</span> of
      <span class="font-medium text-slate-700">{{ stockCount }}</span> stock +
      <span class="font-medium text-slate-700">{{ cashCount }}</span> cash records
    </p>

    <DataTable
      :columns="columns"
      :rows="rows"
      :empty-message="isLoading ? 'Loading…' : 'No activity for this day.'"
    >
      <template #added_at="{ row }">
        <span class="whitespace-nowrap">{{ formatRowTime(row.added_at) }}</span>
      </template>
      <template #record_type="{ row }">
        <span
          class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold"
          :class="isCash(row) ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ row.record_type }}
        </span>
      </template>
      <template #stock_type="{ row }">
        <span
          class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold"
          :class="flowClass(row.stock_type)"
        >
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
        <span class="block text-right font-medium tabular-nums">
          {{ isCash(row) ? formatAmount(row.grams) : formatGrams(row.grams) }}
        </span>
      </template>
      <template #touch="{ row }">
        <span class="block text-right tabular-nums">{{ formatGrams(row.touch) }}</span>
      </template>
      <template #purity="{ row }">
        <span class="block text-right tabular-nums">{{ formatGrams(row.purity) }}</span>
      </template>
      <template #waste_total="{ row }">
        <span class="block text-right tabular-nums text-slate-500">
          {{ formatGrams(row.waste_total) }}
        </span>
      </template>
      <template #remarks="{ row }">
        <span class="text-slate-500">{{ row.remarks || '—' }}</span>
      </template>
    </DataTable>

    <div v-if="hasMore" class="mt-4 flex justify-center">
      <BaseButton variant="secondary" type="button" :disabled="isLoadingMore" @click="loadMore">
        {{ isLoadingMore ? 'Loading…' : 'Load more' }}
      </BaseButton>
    </div>
  </div>
</template>
