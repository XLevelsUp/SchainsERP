<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { stockReportsApi } from '@/lib/stockReportsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import type { Item, ItemsObcbRow, UserDetailListItem } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Items OB & CB — GET /stock/reports/items-obcb (PR #31)
|--------------------------------------------------------------------------
| Running opening/closing balance ledger per stock movement. The head the
| report is compiled for is the signed-in user: getHistoryItemsObcb takes
| it from the bearer token and exposes no head_id query param to override
| it, so no head picker is offered here.
|
| Party role (Employee/Retailer) is a real toggle, not cosmetic. The
| endpoint takes a single `employee_id` string and parses a bare number as
| a USER id, so targeting a retailer requires the "retailer_" prefix.
| Retailers are ordinary user_details rows in this system (what differs is
| which stock_details columns they land in, not the entity), which is why
| both roles pick from the same users list.
|
| Load-more paging rather than numbered pages, matching the Cash
| Transactions report — total_count comes back with every page, so there is
| enough to know when to stop.
|
| Export now calls the backend's own endpoint
| (GET /stock/reports/items-obcb/export, added in PR #43–#45) instead of
| serialising the loaded rows in the browser. That matters: the old version
| could only ever export the pages already fetched, so "Export CSV" after a
| search returning 4,000 rows produced a 50-row file. The server applies the
| same filters with pagination switched off and streams the lot.
|--------------------------------------------------------------------------
*/

const PAGE_SIZE = 50

type PartyRole = 'user' | 'retailer'

const roleOptions: { value: PartyRole; label: string }[] = [
  { value: 'user', label: 'Employee' },
  { value: 'retailer', label: 'Retailer' },
]

const flowOptions: { value: 'IN' | 'OUT'; label: string }[] = [
  { value: 'IN', label: 'IN' },
  { value: 'OUT', label: 'OUT' },
]

const filters = reactive({
  party_role: 'user' as PartyRole,
  party_id: null as number | null,
  item_id: null as number | null,
  from_date: '',
  from_time: '',
  to_date: '',
  to_time: '',
  type: null as 'IN' | 'OUT' | null,
})

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() =>
  items.value.map((i) => ({ value: i.item_id, label: i.item_name })),
)

const rows = ref<ItemsObcbRow[]>([])
const totalCount = ref(0)
const pageNo = ref(1)
const hasSearched = ref(false)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const loadError = ref('')

const hasMore = computed(() => rows.value.length < totalCount.value)

const columns: DataTableColumn<ItemsObcbRow>[] = [
  { key: 'stock_id', label: 'ID' },
  { key: 'added_at', label: 'Date' },
  { key: 'item_name', label: 'Item' },
  { key: 'stock_type', label: 'Flow' },
  { key: 'entry_type', label: 'Entry' },
  { key: 'given_by_name', label: 'Given By / Given To' },
  { key: 'grams', label: 'Grams' },
  { key: 'touch', label: 'Touch' },
  { key: 'purity', label: 'Purity' },
  { key: 'ob_grams', label: 'OB Gm' },
  { key: 'cb_grams', label: 'CB Gm' },
  { key: 'ob_purity', label: 'OB Pur' },
  { key: 'cb_purity', label: 'CB Pur' },
  { key: 'remarks', label: 'Remarks' },
]

function formatNumber(value: number | string) {
  const n = Number(value)
  return Number.isFinite(n)
    ? n.toLocaleString(undefined, { maximumFractionDigits: 4 })
    : String(value)
}

// Same IN/OUT palette the stock history and cash tables already use, so
// this screen reads as one system with them.
function flowClass(type: string): string {
  if (type === 'IN') return 'bg-emerald-50 text-emerald-700'
  if (type === 'OUT') return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-600'
}

// employee_id is only sent once a party is picked; omitting it reports from
// the head's own perspective, which is the endpoint's default branch.
//
// Filters only, no paging — shared by the paged search and the CSV export.
// The export endpoint sets `is_export` server-side, which makes
// ReportService skip pagination entirely, so sending page_size/page_no there
// would be noise.
function filterQuery() {
  return {
    employee_id:
      filters.party_id !== null ? `${filters.party_role}_${filters.party_id}` : undefined,
    item_id: filters.item_id ?? undefined,
    from_date: filters.from_date || undefined,
    // The service only applies a time when its own date is present
    // ("$fromDate $fromTime"), so a lone time would silently do nothing.
    // The inputs are disabled without a date, but a disabled input keeps
    // its value — so clearing the date after typing a time would otherwise
    // still send it. Mirror the backend's own condition here.
    from_time: filters.from_date ? filters.from_time || undefined : undefined,
    to_date: filters.to_date || undefined,
    to_time: filters.to_date ? filters.to_time || undefined : undefined,
    type: filters.type ?? undefined,
  }
}

function currentQuery(page: number) {
  return {
    ...filterQuery(),
    page_size: PAGE_SIZE,
    page_no: page,
  }
}

// The CSV covers every matching row, not just the pages loaded so far —
// worth saying out loud in the UI, because the button sits under a table
// that may be showing 50 of 4,000.
const isExporting = ref(false)

async function exportCsv() {
  if (isExporting.value) return
  isExporting.value = true
  loadError.value = ''
  try {
    await stockReportsApi.exportItemsObcb(filterQuery())
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to export the report.'
  } finally {
    isExporting.value = false
  }
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
    const result = await stockReportsApi.getItemsObcb(currentQuery(1))
    rows.value = result.records
    totalCount.value = result.total_count
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
    const result = await stockReportsApi.getItemsObcb(currentQuery(next))
    rows.value = [...rows.value, ...result.records]
    totalCount.value = result.total_count
    pageNo.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more rows.'
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  filters.party_role = 'user'
  filters.party_id = null
  filters.item_id = null
  filters.from_date = ''
  filters.from_time = ''
  filters.to_date = ''
  filters.to_time = ''
  filters.type = null
  runSearch()
}

onMounted(() => {
  loadLookups()
  runSearch()
})
</script>

<template>
  <div>
    <PageHeader
      title="Items OB &amp; CB"
      description="Running opening and closing balances per stock movement, for the signed-in head."
    />

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseSelect
          v-model="filters.party_role"
          label="Party role"
          size="sm"
          :options="roleOptions"
        />
        <BaseSelect
          v-model="filters.party_id"
          label="Party"
          size="sm"
          placeholder="All (head view)…"
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
        <BaseInput v-model="filters.from_date" label="From date" type="date" size="sm" clearable />
        <BaseInput
          v-model="filters.from_time"
          label="From time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.from_date"
          clearable
        />
        <BaseInput v-model="filters.to_date" label="To date" type="date" size="sm" clearable />
        <BaseInput
          v-model="filters.to_time"
          label="To time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.to_date"
          clearable
        />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Times narrow their own date — set a date first. Without a time, the range runs from
        00:00:00 to 23:59:59.
      </p>

      <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
        <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">
          Clear
        </BaseButton>
        <BaseButton
          variant="secondary"
          type="button"
          :disabled="isExporting || isLoading"
          @click="exportCsv"
        >
          {{ isExporting ? 'Exporting…' : 'Export CSV' }}
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

    <p v-if="hasSearched" class="mb-2 text-sm text-slate-500">
      Showing <span class="font-medium text-slate-700">{{ rows.length }}</span> of
      <span class="font-medium text-slate-700">{{ totalCount }}</span> movements
    </p>

    <DataTable
      :columns="columns"
      :rows="rows"
      :empty-message="isLoading ? 'Loading…' : 'No stock movements match these filters.'"
    >
      <template #added_at="{ row }">
        <span class="whitespace-nowrap">{{ formatDateTime(row.added_at) }}</span>
      </template>
      <template #item_name="{ row }">
        <span class="whitespace-nowrap">
          {{ row.item_name }}
          <span v-if="row.to_item_name" class="text-slate-400"> → {{ row.to_item_name }}</span>
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
      <template #given_by_name="{ row }">
        <span class="whitespace-nowrap">{{ row.given_by_name }} → {{ row.given_to_name }}</span>
      </template>
      <template #grams="{ row }">
        <span class="block text-right tabular-nums">{{ formatNumber(row.grams) }}</span>
      </template>
      <template #touch="{ row }">
        <span class="block text-right tabular-nums">{{ formatNumber(row.touch) }}</span>
      </template>
      <template #purity="{ row }">
        <span class="block text-right tabular-nums">{{ formatNumber(row.purity) }}</span>
      </template>
      <template #ob_grams="{ row }">
        <span class="block text-right tabular-nums text-slate-500">{{
          formatNumber(row.ob_grams)
        }}</span>
      </template>
      <template #cb_grams="{ row }">
        <span class="block text-right font-medium tabular-nums">{{
          formatNumber(row.cb_grams)
        }}</span>
      </template>
      <template #ob_purity="{ row }">
        <span class="block text-right tabular-nums text-slate-500">{{
          formatNumber(row.ob_purity)
        }}</span>
      </template>
      <template #cb_purity="{ row }">
        <span class="block text-right font-medium tabular-nums">{{
          formatNumber(row.cb_purity)
        }}</span>
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
