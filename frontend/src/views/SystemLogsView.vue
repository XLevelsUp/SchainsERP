<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Eye, RefreshCw, Search, TriangleAlert } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import SystemLogDetailModal from '@/components/system-log/SystemLogDetailModal.vue'
import { systemLogsApi } from '@/lib/systemLogsApi'
import {
  systemLogActionLabel,
  systemLogDescription,
  systemLogModuleClass,
  systemLogModuleLabel,
  systemLogPartyLabel,
} from '@/lib/systemLogLabels'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import type { SystemLog, SystemLogFilters, SystemLogSortOrder } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| System Logs — GET /system-logs (commit 3d048b3c)
|--------------------------------------------------------------------------
| Audit trail of stock and cash transactions, newest first. Read-only:
| rows are written server-side by SystemLogService as each transaction is
| created.
|
| Filters apply as soon as they change; search is debounced like the Phone
| Book's. Dropdown options come from GET /system-logs/filters, which also
| supplies the user and customer lists used to turn the ids the logs store
| ("User #12") into names — the logs themselves record no names.
|--------------------------------------------------------------------------
*/

const PER_PAGE = 50
const SEARCH_DEBOUNCE_MS = 350

const sortOptions: { value: SystemLogSortOrder; label: string }[] = [
  { value: 'desc', label: 'Newest first' },
  { value: 'asc', label: 'Oldest first' },
]

const filters = reactive({
  search: '',
  module: null as string | null,
  action: null as string | null,
  user_id: null as number | null,
  customer_id: null as number | null,
  from_date: '',
  to_date: '',
  sort_order: 'desc' as SystemLogSortOrder,
})

const filterOptions = ref<SystemLogFilters | null>(null)

const moduleOptions = computed(() =>
  (filterOptions.value?.modules ?? []).map((m) => ({ value: m, label: systemLogModuleLabel(m) })),
)

// Narrowed to the chosen module's actions once a module is picked.
const actionOptions = computed(() => {
  const options = filterOptions.value
  if (!options) return []
  const actions = filters.module
    ? (options.actions_by_module[filters.module] ?? [])
    : options.all_actions
  return actions.map((a) => ({ value: a, label: systemLogActionLabel(a) }))
})

const userOptions = computed(() =>
  (filterOptions.value?.users ?? []).map((u) => ({ value: u.user_id, label: u.label })),
)
const customerOptions = computed(() =>
  (filterOptions.value?.customers ?? []).map((c) => ({ value: c.customer_id, label: c.label })),
)

const namesById = computed(() => {
  const names = new Map<number, string>()
  for (const c of filterOptions.value?.customers ?? []) names.set(c.customer_id, c.name)
  for (const u of filterOptions.value?.users ?? []) names.set(u.user_id, u.name)
  return names
})

function nameOf(id: number) {
  return namesById.value.get(id)
}

const logs = ref<SystemLog[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const rangeFrom = ref<number | null>(null)
const rangeTo = ref<number | null>(null)
const isLoading = ref(false)
const loadError = ref('')
const selectedLog = ref<SystemLog | null>(null)

const dateRangeError = computed(() =>
  filters.from_date && filters.to_date && filters.to_date < filters.from_date
    ? 'To date is before From date.'
    : '',
)

const hasActiveFilters = computed(
  () =>
    filters.search.trim() !== '' ||
    filters.module !== null ||
    filters.action !== null ||
    filters.user_id !== null ||
    filters.customer_id !== null ||
    filters.from_date !== '' ||
    filters.to_date !== '',
)

const columns: DataTableColumn<SystemLog>[] = [
  { key: 'created_at', label: 'Time' },
  { key: 'module', label: 'Module' },
  { key: 'action', label: 'Action' },
  { key: 'user', label: 'Performed by' },
  { key: 'customer_id', label: 'Party' },
  { key: 'description', label: 'Description' },
  { key: 'reference_id', label: 'Reference' },
  { key: 'id', label: '' },
]

// Each request takes a ticket; only the newest one may write results, so a
// slow response for an old filter set cannot overwrite a newer one.
let latestRequest = 0

async function load(targetPage = page.value) {
  if (dateRangeError.value) return
  const ticket = ++latestRequest
  isLoading.value = true
  loadError.value = ''
  try {
    const { rows, pagination } = await systemLogsApi.list({
      page: targetPage,
      per_page: PER_PAGE,
      search: filters.search.trim() || undefined,
      module: filters.module ?? undefined,
      action: filters.action ?? undefined,
      user_id: filters.user_id ?? undefined,
      customer_id: filters.customer_id ?? undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      sort_by: 'created_at',
      sort_order: filters.sort_order,
    })
    if (ticket !== latestRequest) return
    logs.value = rows
    page.value = pagination.current_page
    lastPage.value = pagination.last_page
    total.value = pagination.total
    rangeFrom.value = pagination.from
    rangeTo.value = pagination.to
  } catch (err) {
    if (ticket !== latestRequest) return
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load system logs.'
  } finally {
    if (ticket === latestRequest) isLoading.value = false
  }
}

async function loadFilterOptions() {
  try {
    filterOptions.value = await systemLogsApi.filters()
  } catch {
    // Non-fatal — the dropdowns just have no options and parties show as ids.
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => filters.search,
  () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => load(1), SEARCH_DEBOUNCE_MS)
  },
)

// Sync so the action is already cleared when the watcher below runs — one
// request per module change, not two.
watch(
  () => filters.module,
  () => {
    if (filters.action && !actionOptions.value.some((o) => o.value === filters.action)) {
      filters.action = null
    }
  },
  { flush: 'sync' },
)

watch(
  [
    () => filters.module,
    () => filters.action,
    () => filters.user_id,
    () => filters.customer_id,
    () => filters.from_date,
    () => filters.to_date,
    () => filters.sort_order,
  ],
  () => load(1),
)

function prevPage() {
  if (page.value > 1) load(page.value - 1)
}
function nextPage() {
  if (page.value < lastPage.value) load(page.value + 1)
}

function clearFilters() {
  if (searchTimer) clearTimeout(searchTimer)
  filters.search = ''
  filters.module = null
  filters.action = null
  filters.user_id = null
  filters.customer_id = null
  filters.from_date = ''
  filters.to_date = ''
  filters.sort_order = 'desc'
}

function refresh() {
  loadFilterOptions()
  load()
}

function performedBy(row: SystemLog): string {
  if (row.user) return row.user.name
  return systemLogPartyLabel(row.user_id, nameOf)
}

function reference(row: SystemLog): string {
  if (!row.reference_table && row.reference_id === null) return '—'
  return `${row.reference_table ?? '—'} #${row.reference_id ?? '—'}`
}

onMounted(() => {
  loadFilterOptions()
  load(1)
})
</script>

<template>
  <div>
    <PageHeader
      title="System Logs"
      description="Audit trail of stock and cash transactions: who entered what, and when."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="refresh">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <div
      class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
      <p>
        <strong>Partial coverage (backend).</strong> Only newly created stock and cash transactions
        are logged. Edits, deletes, sign-ins, and changes to users, roles and master data are not
        recorded yet. Logs store user ids rather than names, so names shown here are looked up from
        the current user list. The logs API does not check permissions yet, so any signed-in user
        can read it.
      </p>
    </div>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput
          v-model="filters.search"
          label="Search"
          placeholder="Description, operator, reference, IP…"
          size="sm"
          :icon="Search"
          clearable
        />
        <BaseSelect
          v-model="filters.module"
          label="Module"
          size="sm"
          placeholder="All modules…"
          :options="moduleOptions"
        />
        <BaseSelect
          v-model="filters.action"
          label="Action"
          size="sm"
          placeholder="All actions…"
          :options="actionOptions"
        />
        <BaseSelect
          v-model="filters.user_id"
          label="Performed by"
          size="sm"
          placeholder="Anyone…"
          :options="userOptions"
        />
        <BaseSelect
          v-model="filters.customer_id"
          label="Customer"
          size="sm"
          placeholder="All customers…"
          :options="customerOptions"
        />
        <BaseInput v-model="filters.from_date" label="From date" type="date" size="sm" clearable />
        <BaseInput
          v-model="filters.to_date"
          label="To date"
          type="date"
          size="sm"
          clearable
          :error="dateRangeError || undefined"
        />
        <BaseSelect v-model="filters.sort_order" label="Sort" size="sm" :options="sortOptions" />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        "Performed by" is the operator who keyed the entry. "Customer" matches the party a
        transaction was for.
      </p>

      <div class="mt-4 flex justify-end">
        <BaseButton
          variant="secondary"
          type="button"
          :disabled="isLoading || !hasActiveFilters"
          @click="clearFilters"
        >
          Clear
        </BaseButton>
      </div>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <DataTable
      :columns="columns"
      :rows="logs"
      size="sm"
      :empty-message="
        isLoading
          ? 'Loading…'
          : hasActiveFilters
            ? 'No log entries match these filters.'
            : 'Nothing has been logged yet.'
      "
    >
      <template #created_at="{ row }">
        <span class="whitespace-nowrap tabular-nums" :title="row.created_at_human ?? undefined">
          {{ formatDateTime(row.created_at) }}
        </span>
      </template>
      <template #module="{ row }">
        <span
          class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold"
          :class="systemLogModuleClass(row.module)"
        >
          {{ systemLogModuleLabel(row.module) }}
        </span>
      </template>
      <template #action="{ row }">
        <span class="font-medium whitespace-nowrap text-slate-900" :title="row.action">
          {{ systemLogActionLabel(row.action) }}
        </span>
      </template>
      <template #user="{ row }">
        <span class="whitespace-nowrap" :title="row.user?.user_name ?? undefined">
          {{ performedBy(row) }}
        </span>
      </template>
      <template #customer_id="{ row }">
        <span class="whitespace-nowrap" :class="row.customer_id === null ? 'text-slate-400' : ''">
          {{ systemLogPartyLabel(row.customer_id, nameOf) }}
        </span>
      </template>
      <template #description="{ row }">
        <span class="line-clamp-2 block min-w-64 text-slate-600">
          {{ systemLogDescription(row.description, nameOf) }}
        </span>
      </template>
      <template #reference_id="{ row }">
        <span class="font-mono text-xs whitespace-nowrap text-slate-500">
          {{ reference(row) }}
        </span>
      </template>
      <template #id="{ row }">
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          :aria-label="`View log #${row.id}`"
          @click="selectedLog = row"
        >
          <Eye class="h-3.5 w-3.5" aria-hidden="true" />
          View
        </button>
      </template>
    </DataTable>

    <div v-if="total > 0" class="mt-3 flex items-center justify-between text-xs text-slate-500">
      <span class="tabular-nums">
        {{ rangeFrom ?? 0 }}–{{ rangeTo ?? 0 }} of {{ total }} entr{{ total === 1 ? 'y' : 'ies' }}
      </span>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="page === 1 || isLoading"
          aria-label="Previous page"
          @click="prevPage"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>
        <span>Page {{ page }} of {{ lastPage }}</span>
        <button
          type="button"
          class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="page === lastPage || isLoading"
          aria-label="Next page"
          @click="nextPage"
        >
          <ChevronRight class="h-4 w-4" />
        </button>
      </div>
    </div>

    <SystemLogDetailModal
      v-if="selectedLog"
      :log="selectedLog"
      :name-of="nameOf"
      @close="selectedLog = null"
    />
  </div>
</template>
