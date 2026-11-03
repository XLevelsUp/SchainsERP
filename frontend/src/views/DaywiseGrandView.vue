<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { reportApi } from '@/lib/reportApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateOnly } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { DaywiseGrandPartyType, DaywiseGrandRow, UserDetailListItem } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Daywise Grand — GET /report/daywise-grand
|--------------------------------------------------------------------------
| Stock grams and purity in and out, totalled per calendar day for one
| party, newest day first. Every item is summed together, and hidden or
| frozen stock rows are left out — see types/daywiseGrand.ts.
|
| The party picker offers three of the backend's four `type4` values.
| CUSTOMER and RETAILER run the identical query (the stock row's
| retailer_id / to_retailer_id tags), so only "Retailer" is offered: a
| "Customer" option would match retailer tags rather than the customer's
| own given_by/given_to rows, which is what someone picking a customer
| expects — "A user" (EMPLOYEE) is the one that does that, for any user.
|
| A non-head party must be picked before searching: the backend silently
| falls back to the acting head when the id is missing, which would show
| the head's figures under someone else's name.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()

// Days per page. The backend's own default is 15; a page here is a few
// months of trading days, and "Load more" appends the next.
const PAGE_SIZE = 100

type PartyChoice = Exclude<DaywiseGrandPartyType, 'CUSTOMER'>

const partyOptions: { value: PartyChoice; label: string }[] = [
  { value: 'HEAD', label: 'Me (signed-in head)' },
  { value: 'EMPLOYEE', label: 'A user' },
  { value: 'RETAILER', label: 'A retailer' },
]

const filters = reactive({
  from_date: '',
  to_date: '',
  // null (cleared) reads as HEAD, the backend's own default.
  party: 'HEAD' as PartyChoice | null,
  party_user_id: null as number | null,
})

const needsPartyUser = computed(() => filters.party === 'EMPLOYEE' || filters.party === 'RETAILER')

const users = ref<UserDetailListItem[]>([])
const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)

const rows = ref<DaywiseGrandRow[]>([])
const totalDays = ref(0)
const pageNo = ref(1)
const totalPages = ref(1)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const loadError = ref('')
const partyError = ref('')
const hasSearched = ref(false)
// Who the loaded rows belong to — captured at search time so editing the
// filters afterwards doesn't relabel results that haven't been re-run.
const shownFor = ref('')

const hasMore = computed(() => pageNo.value < totalPages.value)

watch(
  () => [filters.party, filters.party_user_id],
  () => {
    partyError.value = ''
  },
)

const columns: DataTableColumn<DaywiseGrandRow>[] = [
  { key: 'date', label: 'Date' },
  { key: 'in_grams', label: 'In grams', align: 'right' },
  { key: 'in_purity', label: 'In purity', align: 'right' },
  { key: 'out_grams', label: 'Out grams', align: 'right' },
  { key: 'out_purity', label: 'Out purity', align: 'right' },
]

function toNumber(value: number | string | null | undefined): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}

// The service rounds to 3 decimals; fixed 3 keeps the columns aligned.
function formatWeight(value: number | string | null | undefined): string {
  return toNumber(value).toLocaleString(undefined, {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  })
}

const totals = computed(() =>
  rows.value.reduce(
    (sum, r) => ({
      in_grams: sum.in_grams + toNumber(r.in_grams),
      in_purity: sum.in_purity + toNumber(r.in_purity),
      out_grams: sum.out_grams + toNumber(r.out_grams),
      out_purity: sum.out_purity + toNumber(r.out_purity),
    }),
    { in_grams: 0, in_purity: 0, out_grams: 0, out_purity: 0 },
  ),
)

function partyLabel(): string {
  if (!needsPartyUser.value) return auth.user?.name ? `${auth.user.name} (you)` : 'you'
  const user = users.value.find((u) => u.id === filters.party_user_id)
  const name = user?.name ?? `User #${filters.party_user_id}`
  return filters.party === 'RETAILER' ? `retailer ${name}` : name
}

function currentQuery(page: number) {
  const party = filters.party ?? 'HEAD'
  const partyUserId = filters.party_user_id ?? undefined
  return {
    from_date: filters.from_date || undefined,
    to_date: filters.to_date || undefined,
    type4: party,
    employee_id: party === 'EMPLOYEE' ? partyUserId : undefined,
    retailer_id: party === 'RETAILER' ? partyUserId : undefined,
    page,
    page_size: PAGE_SIZE,
  }
}

async function loadUsers() {
  try {
    users.value = await userDetailsApi.list(undefined, 'stock')
  } catch {
    // Non-fatal — "Me" still works; the user picker just has no options.
  }
}

// The picker's reload button: same fetch, but a failure surfaces.
async function reloadUsers() {
  users.value = await userDetailsApi.list(undefined, 'stock')
}

async function runSearch() {
  if (isLoading.value) return
  partyError.value = ''
  if (needsPartyUser.value && filters.party_user_id === null) {
    partyError.value = filters.party === 'RETAILER' ? 'Pick a retailer.' : 'Pick a user.'
    return
  }
  isLoading.value = true
  loadError.value = ''
  try {
    const actingUserId = auth.user?.user_id ?? 1
    const result = await reportApi.getDaywiseGrand(currentQuery(1), actingUserId)
    rows.value = result.records
    totalDays.value = result.pagination.total_items
    totalPages.value = result.pagination.total_pages
    pageNo.value = 1
    shownFor.value = partyLabel()
    hasSearched.value = true
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the report.'
  } finally {
    isLoading.value = false
  }
}

async function loadMore() {
  if (!hasMore.value || isLoadingMore.value || isLoading.value) return
  isLoadingMore.value = true
  loadError.value = ''
  try {
    const next = pageNo.value + 1
    const actingUserId = auth.user?.user_id ?? 1
    const result = await reportApi.getDaywiseGrand(currentQuery(next), actingUserId)
    // Days come back newest first and never repeat across pages, so the next
    // page appends directly.
    rows.value = [...rows.value, ...result.records]
    totalDays.value = result.pagination.total_items
    totalPages.value = result.pagination.total_pages
    pageNo.value = next
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load more days.'
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  filters.from_date = ''
  filters.to_date = ''
  filters.party = 'HEAD'
  filters.party_user_id = null
  runSearch()
}

onMounted(() => {
  loadUsers()
  runSearch()
})
</script>

<template>
  <div>
    <PageHeader
      title="Daywise Grand"
      description="Stock grams and purity in and out, totalled per day."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="runSearch">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput v-model="filters.from_date" label="From date" type="date" size="sm" clearable />
        <BaseInput v-model="filters.to_date" label="To date" type="date" size="sm" clearable />
        <BaseSelect
          v-model="filters.party"
          label="Party"
          size="sm"
          placeholder="Me (signed-in head)"
          :options="partyOptions"
        />
        <BaseSelect
          v-if="needsPartyUser"
          v-model="filters.party_user_id"
          :label="filters.party === 'RETAILER' ? 'Retailer' : 'User'"
          required
          size="sm"
          :placeholder="filters.party === 'RETAILER' ? 'Select a retailer…' : 'Select a user…'"
          :options="userOptions"
          :reload="reloadUsers"
          :error="partyError"
        />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        All items are added together. Hidden and frozen entries are left out. "A retailer"
        matches stock entries tagged with that retailer; use "A user" for gold given to or
        received from someone.
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

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Days with movement</dt>
        <dd class="text-lg font-semibold tabular-nums text-slate-900">{{ totalDays }}</dd>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">In (loaded days), grams</dt>
        <dd class="text-lg font-semibold tabular-nums text-emerald-700">
          {{ formatWeight(totals.in_grams) }}
        </dd>
        <dd class="mt-0.5 text-xs tabular-nums text-slate-500">
          Purity {{ formatWeight(totals.in_purity) }}
        </dd>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <dt class="text-xs text-slate-500">Out (loaded days), grams</dt>
        <dd class="text-lg font-semibold tabular-nums text-red-700">
          {{ formatWeight(totals.out_grams) }}
        </dd>
        <dd class="mt-0.5 text-xs tabular-nums text-slate-500">
          Purity {{ formatWeight(totals.out_purity) }}
        </dd>
      </div>
    </div>

    <p v-if="hasSearched" class="mb-2 text-sm text-slate-500">
      Showing <span class="font-medium text-slate-700">{{ rows.length }}</span> of
      <span class="font-medium text-slate-700">{{ totalDays }}</span> days for
      <span class="font-medium text-slate-700">{{ shownFor }}</span>
    </p>

    <DataTable
      :columns="columns"
      :rows="rows"
      size="sm"
      :empty-message="isLoading ? 'Loading…' : 'No stock movement in this range.'"
    >
      <template #date="{ row }">
        <span class="whitespace-nowrap">{{ formatDateOnly(row.date) }}</span>
      </template>
      <template #in_grams="{ row }">
        <span class="tabular-nums">{{ formatWeight(row.in_grams) }}</span>
      </template>
      <template #in_purity="{ row }">
        <span class="tabular-nums text-slate-500">{{ formatWeight(row.in_purity) }}</span>
      </template>
      <template #out_grams="{ row }">
        <span class="tabular-nums">{{ formatWeight(row.out_grams) }}</span>
      </template>
      <template #out_purity="{ row }">
        <span class="tabular-nums text-slate-500">{{ formatWeight(row.out_purity) }}</span>
      </template>
    </DataTable>

    <div v-if="hasMore" class="mt-4 flex justify-center">
      <BaseButton variant="secondary" type="button" :disabled="isLoadingMore" @click="loadMore">
        {{ isLoadingMore ? 'Loading…' : 'Load more' }}
      </BaseButton>
    </div>
  </div>
</template>
