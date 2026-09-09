<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, ChevronLeft, ChevronRight, TriangleAlert } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { reportApi } from '@/lib/reportApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import type { LiveMetalBalanceRecord, LiveMetalBalanceSummary, UserDetailListItem } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Live Metal Balance — GET /report/live-metal-balance (PR #35)
|--------------------------------------------------------------------------
| Shipped ahead of the backend fixing three known bugs, per standing
| instruction: build the screen against the intended contract, flag the
| bugs on-screen rather than waiting. Tracked in PENDING_WORK.md #17-20.
|
|   #17 "Metal" is hardcoded to item_id 2 (actually "Gold Chain" in this
|       app's data — item_id 4 is Metal). Every balance shown here is
|       really Gold Chain, mislabeled. Permanent banner below, not a
|       dismissible one — it's true of every result, not an edge case.
|   #18 The "as of" date+time path runs MySQL-only raw SQL against this
|       app's Postgres database and 500s. Not blocked client-side — the
|       real error is shown, annotated as a known bug rather than hidden
|       behind a generic failure message.
|   #19 The "view as" admin override can never activate server-side (the
|       backend's admin check is unreachable against this role schema).
|       Sent regardless — it's harmless, and correct the moment backend
|       fixes it — with an inline note that it currently does nothing.
|--------------------------------------------------------------------------
*/

const filters = reactive({
  as_of_date: '',
  as_of_time: '',
  view_as_user_id: null as number | null,
})

const users = ref<UserDetailListItem[]>([])
const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)

const EMPTY_SUMMARY: LiveMetalBalanceSummary = { total_grams: 0, total_purity: 0 }
const summary = ref<LiveMetalBalanceSummary>({ ...EMPTY_SUMMARY })
const records = ref<LiveMetalBalanceRecord[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

const isLoading = ref(false)
const loadError = ref('')

const columns: DataTableColumn<LiveMetalBalanceRecord>[] = [
  { key: 'stock_id', label: 'Stock ID' },
  { key: 'balance', label: 'Balance (g)' },
  { key: 'touch', label: 'Touch' },
  { key: 'purity', label: 'Purity' },
  { key: 'party_name', label: 'Party' },
  { key: 'added_at', label: 'Added At' },
]

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

const isAsOfMode = computed(() => Boolean(filters.as_of_date && filters.as_of_time))

async function loadUsers() {
  try {
    users.value = await userDetailsApi.list()
  } catch {
    // Non-fatal — the "view as" picker just offers fewer options.
  }
}

async function runSearch(targetPage = 1) {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    const result = await reportApi.getLiveMetalBalance({
      date: filters.as_of_date || undefined,
      time: filters.as_of_time || undefined,
      user_id: filters.view_as_user_id ?? undefined,
      page: targetPage,
    })
    summary.value = result.summary
    records.value = result.records
    page.value = result.pagination.current_page
    lastPage.value = result.pagination.last_page
    total.value = result.pagination.total
  } catch (err) {
    summary.value = { ...EMPTY_SUMMARY }
    records.value = []
    if (err instanceof ApiError) {
      loadError.value = isAsOfMode.value
        ? `${err.message} — this is the known "as of" SQL bug (PENDING_WORK.md #18), not a transient failure.`
        : err.message
    } else {
      loadError.value = 'Failed to load the report.'
    }
  } finally {
    isLoading.value = false
  }
}

function clearFilters() {
  filters.as_of_date = ''
  filters.as_of_time = ''
  filters.view_as_user_id = null
  runSearch(1)
}

function prevPage() {
  if (page.value > 1) runSearch(page.value - 1)
}
function nextPage() {
  if (page.value < lastPage.value) runSearch(page.value + 1)
}

onMounted(() => {
  loadUsers()
  runSearch(1)
})
</script>

<template>
  <div>
    <PageHeader
      title="Metal Live"
      description="Current (or point-in-time) Metal stock balance, by lot."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="() => runSearch(page)">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
      <p>
        <strong>Known backend bug (PENDING_WORK.md #17):</strong> this report is hardcoded to
        item_id 2, which is "Gold Chain" in this app's data, not "Metal" (item_id 4). Every
        balance below is really a Gold Chain balance mislabeled as Metal — do not rely on these
        numbers until the backend corrects the item id. The "View as" override further down also
        currently has no effect server-side (#19).
      </p>
    </div>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput v-model="filters.as_of_date" label="As of date" type="date" size="sm" clearable />
        <BaseInput
          v-model="filters.as_of_time"
          label="As of time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.as_of_date"
          clearable
        />
        <BaseSelect
          v-model="filters.view_as_user_id"
          label="View as (admin override)"
          size="sm"
          placeholder="Yourself…"
          :options="userOptions"
        />
        <div class="flex items-end gap-2">
          <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">
            Clear
          </BaseButton>
          <BaseButton type="button" :disabled="isLoading" @click="() => runSearch(1)">
            {{ isLoading ? 'Loading…' : 'Search' }}
          </BaseButton>
        </div>
      </div>
      <p v-if="isAsOfMode" class="mt-2 text-xs text-amber-700">
        Both date and time are set — this calls the "as of" reconstruction path, which is
        currently expected to fail (PENDING_WORK.md #18: MySQL-only SQL on a Postgres database).
      </p>
      <p v-else class="mt-2 text-xs text-slate-500">
        Leave date/time empty for the live balance (rows with balance &gt; 0 right now).
      </p>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Total grams</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">
          {{ formatNumber(summary.total_grams) }}
        </dd>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Total purity</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">
          {{ formatNumber(summary.total_purity) }}
        </dd>
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="records"
      :empty-message="isLoading ? 'Loading…' : 'No metal balance rows found.'"
    >
      <template #balance="{ row }">
        <span class="block text-right tabular-nums">{{ formatNumber(row.balance) }}</span>
      </template>
      <template #touch="{ row }">
        <span class="block text-right tabular-nums">{{ formatNumber(row.touch) }}</span>
      </template>
      <template #purity="{ row }">
        <span class="block text-right tabular-nums font-semibold text-slate-900">{{
          formatNumber(row.purity)
        }}</span>
      </template>
      <template #party_name="{ row }">
        <span>{{ row.party_name || '—' }}</span>
      </template>
      <template #added_at="{ row }">
        <span class="whitespace-nowrap text-slate-500">{{ formatDateTime(row.added_at) }}</span>
      </template>
    </DataTable>

    <div v-if="total > 0" class="mt-3 flex items-center justify-between text-xs text-slate-500">
      <span>{{ total }} row{{ total === 1 ? '' : 's' }} total</span>
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
  </div>
</template>
