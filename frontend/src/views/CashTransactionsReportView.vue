<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Download, Printer } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ThermalPrintModal from '@/components/cash-txn/ThermalPrintModal.vue'
import { reportApi } from '@/lib/reportApi'
import { cashCategoriesApi } from '@/lib/cashCategoriesApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import type { BankDetail, CashCategory, CashTransactionReportRow, CashTransactionReportType } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Cash Transactions Report — GET /report/cash-transactions-obcb (PR #21)
|--------------------------------------------------------------------------
| Legacy-UI replication of the old software's "Cash Transactions" OB/CB
| report screen. Two of the legacy filters are NOT wired to the backend
| deliberately:
|
|  - "Roles" / "Customer Name" — the new report endpoint has no head_id/
|    customer_id/sender/recipient filter at all, only category_id, type,
|    bank_id, and date range. There's nothing to send those values to.
|  - "Cash Txn ID" IS included below, but only as a client-side filter over
|    rows already loaded via Load More — the endpoint has no txn_id query
|    param, so it can't narrow the server-side result set.
|
| "Type" is a single BaseSelect rather than the legacy multi-select box —
| BaseSelect doesn't support multi-value selection, and this screen isn't
| the place to extend that shared component. The backend accepts a comma-
| separated `type` list, so this can grow into a real multi-select later
| without an API change.
|
| Export is a client-side CSV of whatever's currently loaded (no backend
| export endpoint exists yet).
|--------------------------------------------------------------------------
*/

const PAGE_SIZE = 25

const typeOptions: { value: CashTransactionReportType; label: string }[] = [
  { value: 'EXPENSE', label: 'Expense' },
  { value: 'INCOME', label: 'Income' },
  { value: 'AUTO_ENTRY', label: 'Auto Entry' },
  { value: 'PURCHASE_GOLD', label: 'Purchase Gold' },
  { value: 'SALE_GOLD', label: 'Sale Gold' },
  { value: 'CASH_TO_GOLD', label: 'Cash To Gold' },
  { value: 'GOLD_TO_CASH', label: 'Gold To Cash' },
  { value: 'OUT_CASH_CONVERTER', label: 'Out Cash Converter' },
  { value: 'IN_CASH_CONVERTER', label: 'In Cash Converter' },
]

const filters = reactive({
  category_id: null as number | null,
  type: null as CashTransactionReportType | null,
  bank_id: null as number | null,
  from_date: '',
  to_date: '',
  txn_id_search: '',
})

const categories = ref<CashCategory[]>([])
const banks = ref<BankDetail[]>([])
const categoryOptions = computed(() =>
  categories.value.map((c) => ({ value: c.category_id, label: c.category_name })),
)
const bankOptions = computed(() =>
  banks.value.map((b) => ({ value: b.bank_id, label: b.account_name || `#${b.bank_id}` })),
)

const rows = ref<CashTransactionReportRow[]>([])
const totalCount = ref(0)
const page = ref(1)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const loadError = ref('')

const hasMore = computed(() => rows.value.length < totalCount.value)

// Filters the client already loaded — see the top-of-file note on why this
// can't be a server-side query param.
const visibleRows = computed(() => {
  const query = filters.txn_id_search.trim()
  if (!query) return rows.value
  return rows.value.filter((r) => String(r.id).includes(query))
})

const columns: DataTableColumn<CashTransactionReportRow>[] = [
  { key: 'id', label: 'Txn ID' },
  { key: 'name', label: 'Given By → Given To' },
  { key: 'amount', label: 'Amount' },
  { key: 'type_label', label: 'Type' },
  { key: 'opening_balance', label: 'OB' },
  { key: 'closing_balance', label: 'CB' },
  { key: 'date', label: 'Added At' },
  { key: 'remarks', label: 'Remarks' },
]

// Mirrors the palette CashTxnHistoryTable already uses for the same
// underlying type values, so this reads as one system with the rest of
// Cash Management rather than inventing new colors.
function typeBadgeClass(label: string): string {
  if (label.includes('INCOME')) return 'bg-emerald-50 text-emerald-700'
  if (label.includes('EXPENSE')) return 'bg-red-50 text-red-700'
  if (label.includes('AUTO_ENTRY')) return 'bg-amber-50 text-amber-700'
  if (label.includes('SALE GOLD') || label === 'SALE_GOLD') return 'bg-purple-50 text-purple-700'
  if (label.includes('PURCHASE GOLD') || label === 'PURCHASE_GOLD') return 'bg-amber-50 text-amber-700'
  if (label.includes('CASH_TO_GOLD')) return 'bg-red-50 text-red-700'
  if (label.includes('GOLD_TO_CASH')) return 'bg-blue-50 text-blue-700'
  if (label.includes('OUT_CASH_CONVERTER')) return 'bg-amber-50 text-amber-800'
  if (label.includes('IN_CASH_CONVERTER')) return 'bg-purple-50 text-purple-800'
  return 'bg-slate-100 text-slate-600'
}

function formatMoney(value: number): string {
  return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadLookups() {
  try {
    const [categoriesData, banksData] = await Promise.all([cashCategoriesApi.list(), bankDetailsApi.list()])
    categories.value = categoriesData
    banks.value = banksData
  } catch {
    // Non-fatal — filters just show fewer options if this fails.
  }
}

async function runSearch() {
  isLoading.value = true
  loadError.value = ''
  page.value = 1
  try {
    const res = await reportApi.getCashTransactionsObcb({
      category_id: filters.category_id ?? undefined,
      type: filters.type ?? undefined,
      bank_id: filters.bank_id ?? undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      page_size: PAGE_SIZE,
      page: page.value,
    })
    rows.value = res.parameters.content
    totalCount.value = res.parameters.count
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
    const nextPage = page.value + 1
    const res = await reportApi.getCashTransactionsObcb({
      category_id: filters.category_id ?? undefined,
      type: filters.type ?? undefined,
      bank_id: filters.bank_id ?? undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      page_size: PAGE_SIZE,
      page: nextPage,
    })
    rows.value = [...rows.value, ...res.parameters.content]
    totalCount.value = res.parameters.count
    page.value = nextPage
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more rows.'
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  filters.category_id = null
  filters.type = null
  filters.bank_id = null
  filters.from_date = ''
  filters.to_date = ''
  filters.txn_id_search = ''
  runSearch()
}

function exportCsv() {
  const header = ['Txn ID', 'Given By -> Given To', 'Category', 'Amount', 'Type', 'Source', 'OB', 'CB', 'Added At', 'Remarks']
  const escape = (value: unknown) => `"${String(value ?? '').replace(/"/g, '""')}"`
  const lines = [
    header.join(','),
    ...visibleRows.value.map((r) =>
      [
        r.id,
        r.name,
        r.category_name,
        r.amount,
        r.type_label,
        r.source_type,
        r.opening_balance,
        r.closing_balance,
        r.date,
        r.remarks ?? '',
      ]
        .map(escape)
        .join(','),
    ),
  ]
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `cash-transactions-report-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

const printTxnId = ref<number | null>(null)

onMounted(async () => {
  await loadLookups()
  await runSearch()
})
</script>

<template>
  <div>
    <PageHeader
      title="Cash Transactions Report"
      description="Opening/closing balance report across all cash, gold, and conversion transactions."
    />

    <BaseCard class="mb-6">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseSelect
          id="report-category"
          :model-value="filters.category_id"
          label="Category"
          size="sm"
          placeholder="All categories"
          :options="categoryOptions"
          @update:model-value="(v) => (filters.category_id = v as number | null)"
        />
        <BaseSelect
          id="report-type"
          :model-value="filters.type"
          label="Type"
          size="sm"
          placeholder="All types"
          :options="typeOptions"
          @update:model-value="(v) => (filters.type = v as CashTransactionReportType | null)"
        />
        <BaseSelect
          id="report-bank"
          :model-value="filters.bank_id"
          label="Bank"
          size="sm"
          placeholder="All banks"
          :options="bankOptions"
          @update:model-value="(v) => (filters.bank_id = v as number | null)"
        />
        <BaseInput id="report-txn-id" v-model="filters.txn_id_search" label="Cash Txn ID" size="sm" placeholder="Filter loaded rows…" />
        <BaseInput id="report-from-date" v-model="filters.from_date" label="From date" type="date" size="sm" />
        <BaseInput id="report-to-date" v-model="filters.to_date" label="To date" type="date" size="sm" />
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <BaseButton type="button" :disabled="isLoading" @click="runSearch">
          {{ isLoading ? 'Searching…' : 'Search' }}
        </BaseButton>
        <BaseButton variant="secondary" type="button" @click="clearFilters">Clear filters</BaseButton>
        <BaseButton variant="secondary" type="button" :icon="Download" :disabled="rows.length === 0" @click="exportCsv">
          Export CSV
        </BaseButton>
        <span class="ml-auto text-xs text-slate-500">{{ visibleRows.length }} of {{ totalCount }} loaded</span>
      </div>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <div v-if="isLoading" class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">
      Loading…
    </div>

    <template v-else>
      <DataTable :columns="columns" :rows="visibleRows" empty-message="No transactions match these filters.">
        <template #name="{ value }">
          <span class="whitespace-nowrap">{{ value }}</span>
        </template>
        <template #amount="{ value }">
          <span class="font-semibold tabular-nums text-slate-900">{{ formatMoney(value as number) }}</span>
        </template>
        <template #type_label="{ value }">
          <span
            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
            :class="typeBadgeClass(value as string)"
          >
            {{ value }}
          </span>
        </template>
        <template #opening_balance="{ value }">
          <span class="tabular-nums">{{ formatMoney(value as number) }}</span>
        </template>
        <template #closing_balance="{ value }">
          <span class="tabular-nums">{{ formatMoney(value as number) }}</span>
        </template>
        <template #date="{ value }">
          <span class="whitespace-nowrap">{{ value }}</span>
        </template>
        <template #remarks="{ value }">{{ value || '—' }}</template>
        <template #id="{ row }">
          <div class="flex items-center gap-2">
            <span class="tabular-nums text-slate-700">{{ row.id }}</span>
            <button
              type="button"
              class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              aria-label="Print receipt"
              @click="printTxnId = row.id"
            >
              <Printer class="h-4 w-4" />
            </button>
          </div>
        </template>
      </DataTable>

      <div v-if="hasMore" class="mt-4 flex justify-center">
        <BaseButton variant="secondary" type="button" :disabled="isLoadingMore" @click="loadMore">
          {{ isLoadingMore ? 'Loading…' : 'Load more' }}
        </BaseButton>
      </div>
    </template>

    <ThermalPrintModal v-if="printTxnId !== null" :txn-id="printTxnId" @close="printTxnId = null" />
  </div>
</template>
