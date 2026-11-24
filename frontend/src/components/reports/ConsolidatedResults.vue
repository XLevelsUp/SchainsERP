<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { ConsolidatedQuery, ConsolidatedResult, ConsolidatedRow, ConsolidatedTotals } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
| One Consolidated result: outward and inward panes with their totals,
| "load more" on each side and the CSV export, for one query (2026-11-24,
| moved out of ConsolidatedReportView). The app shows one (the signed-in
| head); the admin console shows one per head, titled with its name.
|
| Paging quirk: one response carries both sections, but page_no_out and
| page_no_in are separate params. "Load more" on one side consumes only
| that side, so the rows already loaded on the other aren't replaced.
|
| page_size defaults to 1000 server-side; a smaller page keeps the first
| paint fast. Runs again whenever `runKey` changes (the screen's Search).
*/

const PAGE_SIZE = 50

type FilterQuery = Omit<ConsolidatedQuery, 'page_size' | 'page_no_out' | 'page_no_in'>

const props = defineProps<{
  fetch: (query: ConsolidatedQuery) => Promise<ConsolidatedResult>
  exportCsv: (query: FilterQuery) => Promise<void>
  query: FilterQuery
  runKey: number
  title?: string
}>()

const emit = defineEmits<{ loading: [isLoading: boolean] }>()

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
const isExporting = ref(false)
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

// Detail rows arrive as decimal:4 strings; totals as numbers; waste_value
// can be null.
function formatNumber(value: number | string | null) {
  if (value === null) return '—'
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 4 }) : String(value)
}

function pageQuery(pageOut: number, pageIn: number): ConsolidatedQuery {
  return { ...props.query, page_size: PAGE_SIZE, page_no_out: pageOut, page_no_in: pageIn }
}

async function runSearch() {
  isLoading.value = true
  emit('loading', true)
  loadError.value = ''
  outPage.value = 1
  inPage.value = 1
  try {
    const result = await props.fetch(pageQuery(1, 1))
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
    emit('loading', false)
  }
}

async function loadMoreOut() {
  if (!hasMoreOut.value || isLoadingMoreOut.value) return
  isLoadingMoreOut.value = true
  loadError.value = ''
  try {
    const next = outPage.value + 1
    // in_details rides along; ignored so the inward rows aren't replaced.
    const result = await props.fetch(pageQuery(next, 1))
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
    const result = await props.fetch(pageQuery(1, next))
    inRows.value = [...inRows.value, ...result.in_details.records]
    inTotalCount.value = result.in_details.total_count
    inPage.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more inward rows.'
  } finally {
    isLoadingMoreIn.value = false
  }
}

// One CSV containing both sections.
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

const panes = computed(() => [
  {
    key: 'out',
    tag: 'OUT',
    tagClass: 'bg-red-50 text-red-700',
    heading: 'Outward movements',
    rows: outRows.value,
    total: outTotalCount.value,
    summary: summaryOut.value,
    hasMore: hasMoreOut.value,
    isLoadingMore: isLoadingMoreOut.value,
    loadMore: loadMoreOut,
    moreLabel: 'Load more outward',
    empty: 'No outward movements match these filters.',
  },
  {
    key: 'in',
    tag: 'IN',
    tagClass: 'bg-emerald-50 text-emerald-700',
    heading: 'Inward movements',
    rows: inRows.value,
    total: inTotalCount.value,
    summary: summaryIn.value,
    hasMore: hasMoreIn.value,
    isLoadingMore: isLoadingMoreIn.value,
    loadMore: loadMoreIn,
    moreLabel: 'Load more inward',
    empty: 'No inward movements match these filters.',
  },
])
</script>

<template>
  <section :class="title ? 'rounded-lg border border-slate-200 bg-slate-50/50 p-3' : ''">
    <header v-if="title" class="mb-2 flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold text-slate-900">{{ title }}</h2>
      <BaseButton variant="secondary" type="button" :disabled="isExporting || isLoading" @click="exportCsv">
        {{ isExporting ? 'Exporting…' : 'Export CSV' }}
      </BaseButton>
    </header>

    <p v-if="loadError" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ loadError }}
    </p>

    <!--
      Outward left, inward right — the two directions get read against each
      other. Each pane owns its totals, scroll area and "load more".
    -->
    <div class="grid items-start gap-3 xl:grid-cols-2">
      <section
        v-for="pane in panes"
        :key="pane.key"
        class="flex min-w-0 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white"
      >
        <header class="flex items-center gap-2 border-b border-slate-200 px-3 py-2">
          <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold" :class="pane.tagClass">{{ pane.tag }}</span>
          <h3 class="text-sm font-semibold text-slate-900">{{ pane.heading }}</h3>
          <span v-if="hasSearched" class="ml-auto text-xs text-slate-500">
            Showing <span class="font-medium text-slate-700">{{ pane.rows.length }}</span> of
            <span class="font-medium text-slate-700">{{ pane.total }}</span>
          </span>
        </header>

        <dl class="grid grid-cols-3 divide-x divide-slate-200 border-b border-slate-200 bg-slate-50">
          <div v-for="figure in (['grams', 'purity', 'wastage'] as const)" :key="figure" class="px-3 py-1.5">
            <dt class="text-[11px] tracking-wide text-slate-500 uppercase">{{ figure }}</dt>
            <dd class="text-sm font-semibold tabular-nums text-slate-900">
              {{ hasSearched ? formatNumber(pane.summary[figure]) : '—' }}
            </dd>
          </div>
        </dl>

        <DataTable
          :columns="columns"
          :rows="pane.rows"
          size="sm"
          flush
          max-height="30rem"
          :empty-message="isLoading ? 'Loading…' : pane.empty"
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
            <span class="whitespace-nowrap">{{ row.given_by_name ?? '—' }} → {{ row.given_to_name ?? '—' }}</span>
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
            <span class="block text-right tabular-nums text-slate-500">{{ formatNumber(row.waste_value) }}</span>
          </template>
          <template #remarks="{ row }">
            <span class="text-slate-500">{{ row.remarks || '—' }}</span>
          </template>
        </DataTable>

        <div v-if="pane.hasMore" class="flex justify-center border-t border-slate-200 px-3 py-2">
          <BaseButton variant="secondary" type="button" :disabled="pane.isLoadingMore" @click="pane.loadMore">
            {{ pane.isLoadingMore ? 'Loading…' : pane.moreLabel }}
          </BaseButton>
        </div>
      </section>
    </div>
  </section>
</template>
