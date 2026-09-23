<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { reportApi } from '@/lib/reportApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { isMetalItem } from '@/lib/metalItem'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import type { LiveMetalBalanceRecord, LiveMetalBalanceSummary, UserDetailListItem } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Live Metal Balance — GET /report/live-metal-balance (PR #35)
|--------------------------------------------------------------------------
| Was shipped ahead of the backend fixing three known bugs (PENDING_WORK.md
| #17-19); all three are now closed by PR #46 (2026-09-22) — see
| types/liveMetalBalance.ts for what changed in each. Both the "as of"
| date+time filter and the "view as" admin override are expected to work
| normally now, so this screen no longer carries a warning banner for them.
|
|   #17 Item default — moot here regardless of the backend's default,
|       because this screen always resolves the real Metal item by name
|       (the same rule getAvailableMetals applies) and sends item_id.
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

// Resolved by name, not hardcoded: the ids differ between environments and
// the backend's own default (2) is wrong here. Null means the items list has
// no "Metal" row at all, which makes the report meaningless rather than
// merely wrong — so the screen says so instead of querying the default.
const metalItemId = ref<number | null>(null)

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

// Fatal to this screen, unlike loadUsers: without the id the report would
// silently fall back to the backend's wrong default. runSearch refuses to
// run until this resolves.
async function loadMetalItem() {
  try {
    const items = await itemsApi.list()
    metalItemId.value = items.find(isMetalItem)?.item_id ?? null
  } catch {
    metalItemId.value = null
  }
}

async function runSearch(targetPage = 1) {
  if (isLoading.value) return
  if (metalItemId.value === null) {
    loadError.value =
      'No item named "Metal" exists in the items list, so there is nothing to report on. Create one on the Items screen.'
    return
  }
  isLoading.value = true
  loadError.value = ''
  try {
    const result = await reportApi.getLiveMetalBalance({
      item_id: metalItemId.value,
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
      loadError.value = err.message
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

onMounted(async () => {
  loadUsers()
  // Awaited — runSearch needs the resolved item id, not the default.
  await loadMetalItem()
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
      <p v-if="isAsOfMode" class="mt-2 text-xs text-slate-500">
        Both date and time are set — reconstructing the balance as of that point in time.
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
