<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { ItemsObcbQuery, ItemsObcbResult, ItemsObcbRow } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
| One Items OB & CB result: the table, "load more" paging and the CSV
| export, for one query (2026-11-24, moved out of ItemsObcbReportView).
| The app shows one (the signed-in head); the admin console shows one per
| head, titled with the head's name, all driven by the same filters.
|
| Runs again whenever `runKey` changes (the screen's Search).
*/

const PAGE_SIZE = 50

type FilterQuery = Omit<ItemsObcbQuery, 'page_no' | 'page_size'>

const props = defineProps<{
  fetch: (query: ItemsObcbQuery) => Promise<ItemsObcbResult>
  exportCsv: (query: FilterQuery) => Promise<void>
  // The filters applied at the last Search (head_id included in the console).
  query: FilterQuery
  runKey: number
  // The head's name, in the console; shows a heading with its own Export.
  title?: string
}>()

const emit = defineEmits<{ loading: [isLoading: boolean] }>()

const rows = ref<ItemsObcbRow[]>([])
const totalCount = ref(0)
const pageNo = ref(1)
const hasSearched = ref(false)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const isExporting = ref(false)
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
  return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 4 }) : String(value)
}

// Same IN/OUT palette the stock history and cash tables already use.
function flowClass(type: string): string {
  if (type === 'IN') return 'bg-emerald-50 text-emerald-700'
  if (type === 'OUT') return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-600'
}

async function runSearch() {
  isLoading.value = true
  emit('loading', true)
  loadError.value = ''
  pageNo.value = 1
  try {
    const result = await props.fetch({ ...props.query, page_size: PAGE_SIZE, page_no: 1 })
    rows.value = result.records
    totalCount.value = result.total_count
    hasSearched.value = true
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    isLoading.value = false
    emit('loading', false)
  }
}

async function loadMore() {
  if (!hasMore.value || isLoadingMore.value) return
  isLoadingMore.value = true
  loadError.value = ''
  try {
    const next = pageNo.value + 1
    const result = await props.fetch({ ...props.query, page_size: PAGE_SIZE, page_no: next })
    rows.value = [...rows.value, ...result.records]
    totalCount.value = result.total_count
    pageNo.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more rows.'
  } finally {
    isLoadingMore.value = false
  }
}

// The CSV covers every matching row, not just the pages loaded so far.
async function exportCsv() {
  if (isExporting.value) return
  isExporting.value = true
  loadError.value = ''
  try {
    await props.exportCsv(props.query)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to export the report.'
  } finally {
    isExporting.value = false
  }
}

watch(() => props.runKey, runSearch, { immediate: true })

defineExpose({ exportCsv, isExporting })
</script>

<template>
  <section :class="title ? 'rounded-lg border border-slate-200 bg-white p-3' : ''">
    <header v-if="title" class="mb-2 flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold text-slate-900">{{ title }}</h2>
      <BaseButton variant="secondary" type="button" :disabled="isExporting || isLoading" @click="exportCsv">
        {{ isExporting ? 'Exporting…' : 'Export CSV' }}
      </BaseButton>
    </header>

    <p v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
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
        <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold" :class="flowClass(row.stock_type)">
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
        <span class="block text-right tabular-nums text-slate-500">{{ formatNumber(row.ob_grams) }}</span>
      </template>
      <template #cb_grams="{ row }">
        <span class="block text-right font-medium tabular-nums">{{ formatNumber(row.cb_grams) }}</span>
      </template>
      <template #ob_purity="{ row }">
        <span class="block text-right tabular-nums text-slate-500">{{ formatNumber(row.ob_purity) }}</span>
      </template>
      <template #cb_purity="{ row }">
        <span class="block text-right font-medium tabular-nums">{{ formatNumber(row.cb_purity) }}</span>
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
  </section>
</template>
