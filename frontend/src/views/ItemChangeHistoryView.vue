<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import StockIdLinks from '@/components/stock/StockIdLinks.vue'
import { itemHistoryApi } from '@/lib/itemHistoryApi'
import { itemsApi } from '@/lib/itemsApi'
import { formatDateTime } from '@/lib/date'
import {
  ITEM_CHANGE_PAGE_SIZE,
  defaultItemHistoryFilters,
  formatWeight,
  itemHistoryQuery,
  type ItemHistoryFilterValues,
} from '@/lib/itemHistory'
import { ApiError } from '@/lib/api'
import type { ItemChangeHistorySource, ReportHead } from '@/lib/reportSources'
import type { Item, ItemChangeResult, ItemChangeRow } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Item Change History — GET /stock/reports/item-changes (app),
| /admin/reports/item-changes (console), 2026-11-25
|--------------------------------------------------------------------------
| Every Item Change saved from the Stocks page: one row each, newest first,
| with the totals of the whole search. Opens on the current month (1st to
| today); leave To Date empty for one day. Item matches either side.
|
| The app shows the signed-in user's own changes (a head's include its
| mapped employees'). The console passes its own source and `heads`: every
| user unless the Head filter picks one.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  source?: ItemChangeHistorySource
  heads?: ReportHead[]
}>()

const source = computed<ItemChangeHistorySource>(
  () =>
    props.source ?? {
      fetch: itemHistoryApi.getItemChanges,
      loadItems: () => itemsApi.list(),
    },
)

const idWisePath = computed(() => (props.heads ? '/admin/id-wise-report' : '/id-wise-report'))

const headOptions = computed(() => (props.heads ?? []).map((h) => ({ value: h.id, label: h.name })))

const filters = reactive<ItemHistoryFilterValues>(defaultItemHistoryFilters())

const items = ref<Item[]>([])
// GET /items is already in Orders No order; don't re-sort.
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))

async function loadItems() {
  try {
    items.value = await source.value.loadItems()
  } catch {
    // Non-fatal: the Item filter just offers no options.
  }
}
async function reloadItems() {
  items.value = await source.value.loadItems()
}

const rows = ref<ItemChangeRow[]>([])
const result = ref<ItemChangeResult | null>(null)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const loadError = ref('')
// The filters the shown rows were loaded with, so Load More pages against
// the same search even if the filters were edited since.
let searchedFilters: ItemHistoryFilterValues = { ...filters }
let searchSeq = 0

function query(pageNo: number) {
  return itemHistoryQuery(searchedFilters, { withHead: !!props.heads, pageNo, pageSize: ITEM_CHANGE_PAGE_SIZE })
}

async function runSearch() {
  const seq = ++searchSeq
  isLoading.value = true
  loadError.value = ''
  searchedFilters = { ...filters }
  try {
    const data = await source.value.fetch(query(1))
    if (seq !== searchSeq) return
    result.value = data
    rows.value = data.records
  } catch (err) {
    if (seq !== searchSeq) return
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    if (seq === searchSeq) isLoading.value = false
  }
}

const hasMore = computed(() => !!result.value && result.value.pagination.current_page < result.value.pagination.total_pages)

async function loadMore() {
  if (!result.value || !hasMore.value || isLoadingMore.value) return
  const seq = searchSeq
  isLoadingMore.value = true
  loadError.value = ''
  try {
    const data = await source.value.fetch(query(result.value.pagination.current_page + 1))
    if (seq !== searchSeq) return
    result.value = data
    rows.value = [...rows.value, ...data.records]
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more rows.'
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  Object.assign(filters, defaultItemHistoryFilters())
  runSearch()
}

const columns: DataTableColumn<ItemChangeRow>[] = [
  { key: 'added_at', label: 'Date' },
  { key: 'user_name', label: 'User' },
  { key: 'from_item_name', label: 'From Item → To Item' },
  { key: 'grams', label: 'Grams', align: 'right' },
  { key: 'from_touch', label: 'From Touch', align: 'right' },
  { key: 'req_touch', label: 'Req. Touch', align: 'right' },
  { key: 'from_purity', label: 'From Purity', align: 'right' },
  { key: 'purity', label: 'To Purity', align: 'right' },
  { key: 'remarks', label: 'Remarks' },
  { key: 'out_stock_id', label: 'Stock IDs (Out / In)' },
]

function formatRowTime(value: string | null): string {
  return value ? formatDateTime(value) : '—'
}

onMounted(() => {
  loadItems()
  runSearch()
})
</script>

<template>
  <div>
    <PageHeader
      title="Item Change History"
      :description="
        heads
          ? 'Item changes made from the Stocks page, for every head or the one you pick.'
          : 'Item changes made from the Stocks page: yours and your employees\'.'
      "
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="runSearch">Refresh</BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseSelect
          v-if="heads"
          id="report_head"
          v-model="filters.head_id"
          label="Head"
          size="sm"
          placeholder="All heads…"
          :options="headOptions"
        />
        <BaseSelect
          id="ich_item"
          v-model="filters.item_id"
          label="Item"
          size="sm"
          placeholder="Any item…"
          :options="itemOptions"
          :reload="reloadItems"
        />
        <BaseInput id="ich_from_date" v-model="filters.from_date" label="From Date" type="date" size="sm" clearable />
        <BaseInput id="ich_to_date" v-model="filters.to_date" label="To Date" type="date" size="sm" clearable />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Leave To Date empty for a single day. Item matches the From or the To item.
      </p>
      <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
        <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">Clear</BaseButton>
        <BaseButton type="button" :disabled="isLoading" @click="runSearch">
          {{ isLoading ? 'Loading…' : 'Search' }}
        </BaseButton>
      </div>
    </BaseCard>

    <p v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </p>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="ich_title">
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-slate-200 px-4 py-3">
        <h2 id="ich_title" class="text-sm font-semibold text-slate-900">Item Changes</h2>
        <span class="rounded bg-inflow-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-inflow-700">
          Total: {{ result ? result.total_count : '…' }}
        </span>
        <span v-if="result && result.total_count > 0" class="ml-auto text-xs tabular-nums text-slate-500">
          Grams {{ formatWeight(result.totals.grams) }} · From purity {{ formatWeight(result.totals.from_purity) }} · To
          purity {{ formatWeight(result.totals.purity) }}
        </span>
      </div>
      <div class="p-3">
        <DataTable
          :columns="columns"
          :rows="rows"
          :empty-message="isLoading ? 'Loading…' : 'No item changes for these filters.'"
        >
          <template #added_at="{ row }">
            <span class="whitespace-nowrap">{{ formatRowTime(row.added_at) }}</span>
          </template>
          <template #user_name="{ row }">
            <span class="whitespace-nowrap">{{ row.user_name ?? '—' }}</span>
          </template>
          <template #from_item_name="{ row }">
            <span class="whitespace-nowrap font-medium text-slate-800">
              {{ row.from_item_name ?? `#${row.from_item_id}` }} &rarr; {{ row.to_item_name ?? `#${row.to_item_id}` }}
            </span>
          </template>
          <template #grams="{ row }">
            <span class="font-medium tabular-nums">{{ formatWeight(row.grams) }}</span>
          </template>
          <template #from_touch="{ row }">
            <span class="tabular-nums">{{ formatWeight(row.from_touch) }}</span>
          </template>
          <template #req_touch="{ row }">
            <span class="tabular-nums">{{ formatWeight(row.req_touch) }}</span>
          </template>
          <template #from_purity="{ row }">
            <span class="tabular-nums text-slate-600">{{ formatWeight(row.from_purity) }}</span>
          </template>
          <template #purity="{ row }">
            <span class="font-medium tabular-nums">{{ formatWeight(row.purity) }}</span>
          </template>
          <template #remarks="{ row }">
            <span class="text-slate-500">{{ [row.remarks, row.item_remarks].filter(Boolean).join(' · ') || '—' }}</span>
          </template>
          <template #out_stock_id="{ row }">
            <StockIdLinks :out-stock-id="row.out_stock_id" :in-stock-id="row.in_stock_id" :id-wise-path="idWisePath" />
          </template>
        </DataTable>
        <div v-if="hasMore" class="mt-3 flex items-center justify-center gap-3">
          <span class="text-xs text-slate-500">{{ rows.length }} of {{ result?.total_count }}</span>
          <BaseButton variant="secondary" type="button" :disabled="isLoadingMore" @click="loadMore">
            {{ isLoadingMore ? 'Loading…' : 'Load More' }}
          </BaseButton>
        </div>
      </div>
    </section>
  </div>
</template>
