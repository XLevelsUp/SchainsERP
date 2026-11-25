<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ChevronDown, RefreshCw } from 'lucide-vue-next'
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
  ITEM_CONVERSION_GROUP_PAGE_SIZE,
  alloySummary,
  defaultItemHistoryFilters,
  formatWeight,
  itemHistoryQuery,
  type ItemHistoryFilterValues,
} from '@/lib/itemHistory'
import { ApiError } from '@/lib/api'
import type { ItemConversionGroupSource, ReportHead } from '@/lib/reportSources'
import type { Item, ItemConversionGroup, ItemConversionGroupResult, ItemConversionRow } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Item Conversion Group History — GET /stock/reports/item-conversion-groups
| (app), /admin/reports/item-conversion-groups (console), 2026-11-25
|--------------------------------------------------------------------------
| Every Item Conversion saved from the Stocks page, grouped by item pair
| (source → target), the pairs in Orders No order. Each group shows its
| count and totals and folds open to its conversions, newest first, with
| their alloys. Opens on the current month (1st to today); leave To Date
| empty for one day.
|
| The app shows the signed-in user's own conversions (a head's include its
| mapped employees'). The console passes its own source and `heads`: every
| user unless the Head filter picks one.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  source?: ItemConversionGroupSource
  heads?: ReportHead[]
}>()

const source = computed<ItemConversionGroupSource>(
  () =>
    props.source ?? {
      fetch: itemHistoryApi.getConversionGroups,
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

const groups = ref<ItemConversionGroup[]>([])
const result = ref<ItemConversionGroupResult | null>(null)
// Folded groups, by key; every group starts open.
const folded = ref(new Set<string>())
const isLoading = ref(false)
const isLoadingMore = ref(false)
const loadError = ref('')
let searchedFilters: ItemHistoryFilterValues = { ...filters }
let searchSeq = 0

function query(pageNo: number) {
  return itemHistoryQuery(searchedFilters, {
    withHead: !!props.heads,
    pageNo,
    pageSize: ITEM_CONVERSION_GROUP_PAGE_SIZE,
  })
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
    groups.value = data.groups
    folded.value = new Set()
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
    groups.value = [...groups.value, ...data.groups]
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more groups.'
  } finally {
    isLoadingMore.value = false
  }
}

function toggle(key: string) {
  const next = new Set(folded.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  folded.value = next
}

function clearFilters() {
  Object.assign(filters, defaultItemHistoryFilters())
  runSearch()
}

function pairLabel(group: ItemConversionGroup): string {
  return `${group.source_item_name ?? `#${group.source_item_id}`} → ${group.target_item_name ?? `#${group.target_item_id}`}`
}

const columns: DataTableColumn<ItemConversionRow>[] = [
  { key: 'added_at', label: 'Date' },
  { key: 'user_name', label: 'User' },
  { key: 'source_grams', label: 'Source Grams', align: 'right' },
  { key: 'source_touch', label: 'Source Touch', align: 'right' },
  { key: 'source_purity', label: 'Source Purity', align: 'right' },
  { key: 'target_touch', label: 'Target Touch', align: 'right' },
  { key: 'converted_grams', label: 'Converted Grams', align: 'right' },
  { key: 'converted_purity', label: 'Converted Purity', align: 'right' },
  { key: 'alloys', label: 'Alloys' },
  { key: 'remarks', label: 'Remarks' },
  { key: 'bill_id', label: 'Bill No' },
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
      title="Item Conversion Group History"
      :description="
        heads
          ? 'Item conversions made from the Stocks page, grouped by item pair, for every head or the one you pick.'
          : 'Item conversions made from the Stocks page, grouped by item pair: yours and your employees\'.'
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
          id="icg_item"
          v-model="filters.item_id"
          label="Item"
          size="sm"
          placeholder="Any item…"
          :options="itemOptions"
          :reload="reloadItems"
        />
        <BaseInput id="icg_from_date" v-model="filters.from_date" label="From Date" type="date" size="sm" clearable />
        <BaseInput id="icg_to_date" v-model="filters.to_date" label="To Date" type="date" size="sm" clearable />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Leave To Date empty for a single day. Item matches the source or the target item.
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

    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
      <span class="rounded bg-inflow-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-inflow-700">
        Groups: {{ result ? result.total_count : '…' }}
      </span>
      <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-600">
        Conversions: {{ result ? result.total_conversions : '…' }}
      </span>
    </div>

    <div
      v-if="!isLoading && result && groups.length === 0"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      No item conversions for these filters.
    </div>
    <div
      v-else-if="isLoading && groups.length === 0"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading…
    </div>

    <section
      v-for="group in groups"
      :key="group.key"
      class="mb-3 overflow-hidden rounded-lg border border-slate-200 bg-white"
      :aria-label="pairLabel(group)"
    >
      <button
        type="button"
        class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-left hover:bg-slate-50"
        :aria-expanded="!folded.has(group.key)"
        @click="toggle(group.key)"
      >
        <ChevronDown
          class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
          :class="{ '-rotate-90': folded.has(group.key) }"
        />
        <h2 class="text-sm font-semibold text-slate-900">{{ pairLabel(group) }}</h2>
        <span class="rounded bg-inflow-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-inflow-700">
          {{ group.count }} {{ group.count === 1 ? 'conversion' : 'conversions' }}
        </span>
        <span class="ml-auto text-xs tabular-nums text-slate-500">
          Source {{ formatWeight(group.totals.source_grams) }} g · Converted
          {{ formatWeight(group.totals.converted_grams) }} g · Purity {{ formatWeight(group.totals.source_purity) }} →
          {{ formatWeight(group.totals.converted_purity) }}
          <template v-if="Number(group.totals.alloy_grams) > 0">
            · Alloy {{ formatWeight(group.totals.alloy_grams) }} g
          </template>
        </span>
      </button>
      <div v-show="!folded.has(group.key)" class="border-t border-slate-200 p-3">
        <DataTable :columns="columns" :rows="group.records" empty-message="No conversions.">
          <template #added_at="{ row }">
            <span class="whitespace-nowrap">{{ formatRowTime(row.added_at) }}</span>
          </template>
          <template #user_name="{ row }">
            <span class="whitespace-nowrap">{{ row.user_name ?? '—' }}</span>
          </template>
          <template #source_grams="{ row }">
            <span class="font-medium tabular-nums">{{ formatWeight(row.source_grams) }}</span>
          </template>
          <template #source_touch="{ row }">
            <span class="tabular-nums">{{ formatWeight(row.source_touch) }}</span>
          </template>
          <template #source_purity="{ row }">
            <span class="tabular-nums text-slate-600">{{ formatWeight(row.source_purity) }}</span>
          </template>
          <template #target_touch="{ row }">
            <span class="tabular-nums">{{ formatWeight(row.target_touch) }}</span>
          </template>
          <template #converted_grams="{ row }">
            <span class="font-medium tabular-nums">{{ formatWeight(row.converted_grams) }}</span>
          </template>
          <template #converted_purity="{ row }">
            <span class="tabular-nums">{{ formatWeight(row.converted_purity) }}</span>
          </template>
          <template #alloys="{ row }">
            <span class="text-xs text-slate-600">{{ alloySummary(row) }}</span>
          </template>
          <template #remarks="{ row }">
            <span class="text-slate-500">{{ [row.remarks, row.item_remarks].filter(Boolean).join(' · ') || '—' }}</span>
          </template>
          <template #bill_id="{ row }">
            <span class="tabular-nums text-slate-600">{{ row.bill_id ?? '—' }}</span>
          </template>
          <template #out_stock_id="{ row }">
            <StockIdLinks :out-stock-id="row.out_stock_id" :in-stock-id="row.in_stock_id" :id-wise-path="idWisePath" />
          </template>
        </DataTable>
      </div>
    </section>

    <div v-if="hasMore" class="mt-3 flex items-center justify-center gap-3">
      <span class="text-xs text-slate-500">{{ groups.length }} of {{ result?.total_count }} groups</span>
      <BaseButton variant="secondary" type="button" :disabled="isLoadingMore" @click="loadMore">
        {{ isLoadingMore ? 'Loading…' : 'Load More' }}
      </BaseButton>
    </div>
  </div>
</template>
