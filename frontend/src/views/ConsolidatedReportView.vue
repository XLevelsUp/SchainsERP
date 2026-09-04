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
import type {
  ConsolidatedQuery,
  ConsolidatedRow,
  ConsolidatedTotals,
  Item,
  UserDetailListItem,
} from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Consolidated Report — GET /stock/reports/consolidated (PR #31)
|--------------------------------------------------------------------------
| Outward and inward history for one employee or retailer, side by side,
| with totals aggregated in PostgreSQL over the whole filtered set (not
| just the loaded page) — so the summary figures stay correct no matter how
| little of the detail has been paged in.
|
| The head is the signed-in user; there is no head_id param to override it.
|
| Party role picks WHICH KEY is sent (user_id vs retailer_id) rather than
| prefixing a value the way items-obcb does — this endpoint reads a bare
| number and decides from the key that carried it. Same users list for
| both, since retailers are ordinary user_details rows here.
|
| Paging quirk worth knowing: one response carries both sections, but
| page_no_out and page_no_in are separate params. So "load more" on one
| side still re-runs the other side's query server-side. We deliberately
| consume only the section being paged and leave the other untouched —
| otherwise the already-loaded rows on the other side would be replaced by
| whatever page happened to ride along.
|
| page_size defaults to 1000 server-side. We send a much smaller page so
| the first paint stays fast on employees with tens of thousands of rows.
|--------------------------------------------------------------------------
*/

const PAGE_SIZE = 50

type PartyRole = 'user' | 'retailer'

const roleOptions: { value: PartyRole; label: string }[] = [
  { value: 'user', label: 'Employee' },
  { value: 'retailer', label: 'Retailer' },
]

const filters = reactive({
  party_role: 'user' as PartyRole,
  party_id: null as number | null,
  item_id: null as number | null,
  from_date: '',
  from_time: '',
  to_date: '',
  to_time: '',
})

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() =>
  items.value.map((i) => ({ value: i.item_id, label: i.item_name })),
)

const EMPTY_TOTALS: ConsolidatedTotals = { grams: 0, purity: 0, wastage: 0 }

const summaryOut = ref<ConsolidatedTotals>({ ...EMPTY_TOTALS })
const summaryIn = ref<ConsolidatedTotals>({ ...EMPTY_TOTALS })

const outRows = ref<ConsolidatedRow[]>([])
const inRows = ref<ConsolidatedRow[]>([])
const outTotalCount = ref(0)
const inTotalCount = ref(0)
const outPage = ref(1)
const inPage = ref(1)

const hasSearched = ref(false)
const isLoading = ref(false)
const isLoadingMoreOut = ref(false)
const isLoadingMoreIn = ref(false)
const loadError = ref('')

const hasMoreOut = computed(() => outRows.value.length < outTotalCount.value)
const hasMoreIn = computed(() => inRows.value.length < inTotalCount.value)

const columns: DataTableColumn<ConsolidatedRow>[] = [
  { key: 'stock_id', label: 'ID' },
  { key: 'added_at', label: 'Date' },
  { key: 'item_name', label: 'Item' },
  { key: 'entry_type', label: 'Entry' },
  { key: 'given_by_name', label: 'Given By / Given To' },
  { key: 'grams', label: 'Grams' },
  { key: 'touch', label: 'Touch' },
  { key: 'purity', label: 'Purity' },
  { key: 'waste_value', label: 'Wastage' },
  { key: 'remarks', label: 'Remarks' },
]

// Detail rows arrive as decimal:4 strings; summary totals arrive as
// numbers; waste_value can be null. One formatter covers all three rather
// than three near-identical ones.
function formatNumber(value: number | string | null) {
  if (value === null) return '—'
  const n = Number(value)
  return Number.isFinite(n)
    ? n.toLocaleString(undefined, { maximumFractionDigits: 4 })
    : String(value)
}

// Sends exactly one of user_id / retailer_id — the key is what tells the
// backend which party a bare id refers to.
function currentQuery(pageOut: number, pageIn: number): ConsolidatedQuery {
  const query: ConsolidatedQuery = {
    item_id: filters.item_id ?? undefined,
    from_date: filters.from_date || undefined,
    // A time is only applied server-side when its own date is present, so
    // a lone time would silently do nothing. The inputs are disabled
    // without a date, but a disabled input keeps its value — so clearing
    // the date after typing a time would otherwise still send it.
    from_time: filters.from_date ? filters.from_time || undefined : undefined,
    to_date: filters.to_date || undefined,
    to_time: filters.to_date ? filters.to_time || undefined : undefined,
    page_size: PAGE_SIZE,
    page_no_out: pageOut,
    page_no_in: pageIn,
  }
  if (filters.party_id !== null) {
    if (filters.party_role === 'retailer') query.retailer_id = filters.party_id
    else query.user_id = filters.party_id
  }
  return query
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
  outPage.value = 1
  inPage.value = 1
  try {
    const result = await stockReportsApi.getConsolidated(currentQuery(1, 1))
    summaryOut.value = result.summary.out
    summaryIn.value = result.summary.in
    outRows.value = result.out_details.records
    inRows.value = result.in_details.records
    outTotalCount.value = result.out_details.total_count
    inTotalCount.value = result.in_details.total_count
    hasSearched.value = true
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    isLoading.value = false
  }
}

async function loadMoreOut() {
  if (!hasMoreOut.value || isLoadingMoreOut.value) return
  isLoadingMoreOut.value = true
  loadError.value = ''
  try {
    const next = outPage.value + 1
    // in_details rides along on this response; ignored on purpose so the
    // inward rows already loaded aren't replaced by page 1 again.
    const result = await stockReportsApi.getConsolidated(currentQuery(next, 1))
    outRows.value = [...outRows.value, ...result.out_details.records]
    outTotalCount.value = result.out_details.total_count
    outPage.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more outward rows.'
  } finally {
    isLoadingMoreOut.value = false
  }
}

async function loadMoreIn() {
  if (!hasMoreIn.value || isLoadingMoreIn.value) return
  isLoadingMoreIn.value = true
  loadError.value = ''
  try {
    const next = inPage.value + 1
    // Mirror of loadMoreOut — only in_details is consumed here.
    const result = await stockReportsApi.getConsolidated(currentQuery(1, next))
    inRows.value = [...inRows.value, ...result.in_details.records]
    inTotalCount.value = result.in_details.total_count
    inPage.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more inward rows.'
  } finally {
    isLoadingMoreIn.value = false
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
      title="Consolidated Report"
      description="Outward and inward stock history for one party, with totals across the full filtered range."
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
        <div class="hidden lg:block"></div>
        <BaseInput v-model="filters.from_date" label="From date" type="date" size="sm" />
        <BaseInput
          v-model="filters.from_time"
          label="From time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.from_date"
        />
        <BaseInput v-model="filters.to_date" label="To date" type="date" size="sm" />
        <BaseInput
          v-model="filters.to_time"
          label="To time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.to_date"
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

    <div v-if="hasSearched" class="mb-6 grid gap-4 lg:grid-cols-2">
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-3 flex items-center gap-2">
          <span class="inline-flex rounded bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-700">
            OUT
          </span>
          <span class="text-sm font-semibold text-slate-900">Outward totals</span>
          <span class="ml-auto text-xs text-slate-400">{{ outTotalCount }} rows</span>
        </div>
        <dl class="grid grid-cols-3 gap-3">
          <div>
            <dt class="text-xs text-slate-500">Grams</dt>
            <dd class="text-base font-semibold tabular-nums text-slate-900">
              {{ formatNumber(summaryOut.grams) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Purity</dt>
            <dd class="text-base font-semibold tabular-nums text-slate-900">
              {{ formatNumber(summaryOut.purity) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Wastage</dt>
            <dd class="text-base font-semibold tabular-nums text-slate-900">
              {{ formatNumber(summaryOut.wastage) }}
            </dd>
          </div>
        </dl>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-3 flex items-center gap-2">
          <span
            class="inline-flex rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold text-emerald-700"
          >
            IN
          </span>
          <span class="text-sm font-semibold text-slate-900">Inward totals</span>
          <span class="ml-auto text-xs text-slate-400">{{ inTotalCount }} rows</span>
        </div>
        <dl class="grid grid-cols-3 gap-3">
          <div>
            <dt class="text-xs text-slate-500">Grams</dt>
            <dd class="text-base font-semibold tabular-nums text-slate-900">
              {{ formatNumber(summaryIn.grams) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Purity</dt>
            <dd class="text-base font-semibold tabular-nums text-slate-900">
              {{ formatNumber(summaryIn.purity) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Wastage</dt>
            <dd class="text-base font-semibold tabular-nums text-slate-900">
              {{ formatNumber(summaryIn.wastage) }}
            </dd>
          </div>
        </dl>
      </div>
    </div>

    <section class="mb-6">
      <div class="mb-2 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">Outward movements</h2>
        <span v-if="hasSearched" class="text-sm text-slate-500">
          Showing <span class="font-medium text-slate-700">{{ outRows.length }}</span> of
          <span class="font-medium text-slate-700">{{ outTotalCount }}</span>
        </span>
      </div>
      <DataTable
        :columns="columns"
        :rows="outRows"
        :empty-message="isLoading ? 'Loading…' : 'No outward movements match these filters.'"
      >
        <template #added_at="{ row }">
          <span class="whitespace-nowrap">{{ formatDateTime(row.added_at) }}</span>
        </template>
        <template #item_name="{ row }">
          <span class="whitespace-nowrap">{{ row.item_name ?? '—' }}</span>
        </template>
        <template #entry_type="{ row }">
          <span class="text-xs whitespace-nowrap text-slate-500">{{ row.entry_type }}</span>
        </template>
        <template #given_by_name="{ row }">
          <span class="whitespace-nowrap">
            {{ row.given_by_name ?? '—' }} → {{ row.given_to_name ?? '—' }}
          </span>
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
        <template #waste_value="{ row }">
          <span class="block text-right tabular-nums text-slate-500">{{
            formatNumber(row.waste_value)
          }}</span>
        </template>
        <template #remarks="{ row }">
          <span class="text-slate-500">{{ row.remarks || '—' }}</span>
        </template>
      </DataTable>
      <div v-if="hasMoreOut" class="mt-3 flex justify-center">
        <BaseButton
          variant="secondary"
          type="button"
          :disabled="isLoadingMoreOut"
          @click="loadMoreOut"
        >
          {{ isLoadingMoreOut ? 'Loading…' : 'Load more outward' }}
        </BaseButton>
      </div>
    </section>

    <section>
      <div class="mb-2 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">Inward movements</h2>
        <span v-if="hasSearched" class="text-sm text-slate-500">
          Showing <span class="font-medium text-slate-700">{{ inRows.length }}</span> of
          <span class="font-medium text-slate-700">{{ inTotalCount }}</span>
        </span>
      </div>
      <DataTable
        :columns="columns"
        :rows="inRows"
        :empty-message="isLoading ? 'Loading…' : 'No inward movements match these filters.'"
      >
        <template #added_at="{ row }">
          <span class="whitespace-nowrap">{{ formatDateTime(row.added_at) }}</span>
        </template>
        <template #item_name="{ row }">
          <span class="whitespace-nowrap">{{ row.item_name ?? '—' }}</span>
        </template>
        <template #entry_type="{ row }">
          <span class="text-xs whitespace-nowrap text-slate-500">{{ row.entry_type }}</span>
        </template>
        <template #given_by_name="{ row }">
          <span class="whitespace-nowrap">
            {{ row.given_by_name ?? '—' }} → {{ row.given_to_name ?? '—' }}
          </span>
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
        <template #waste_value="{ row }">
          <span class="block text-right tabular-nums text-slate-500">{{
            formatNumber(row.waste_value)
          }}</span>
        </template>
        <template #remarks="{ row }">
          <span class="text-slate-500">{{ row.remarks || '—' }}</span>
        </template>
      </DataTable>
      <div v-if="hasMoreIn" class="mt-3 flex justify-center">
        <BaseButton
          variant="secondary"
          type="button"
          :disabled="isLoadingMoreIn"
          @click="loadMoreIn"
        >
          {{ isLoadingMoreIn ? 'Loading…' : 'Load more inward' }}
        </BaseButton>
      </div>
    </section>
  </div>
</template>
