<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ItemsObcbResults from '@/components/reports/ItemsObcbResults.vue'
import { stockReportsApi } from '@/lib/stockReportsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { userOptionLabel } from '@/lib/userLabel'
import type { ItemsObcbSource, ReportHead } from '@/lib/reportSources'
import type { Item, ItemsObcbQuery, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Items OB & CB — GET /stock/reports/items-obcb (PR #31)
|--------------------------------------------------------------------------
| Running opening/closing balance ledger per stock movement, from one
| head's side.
|
|  - App: the head is the signed-in user (no head picker).
|  - Admin console (2026-11-24): `heads` is passed, and the report runs once
|    per head into its own section ("grouped by head"), each with its own
|    paging and CSV export; the Head filter narrows it to one head. Same
|    filters, same figures as the head sees.
|
| Party role (Employee/Retailer) is a real toggle, not cosmetic. The
| endpoint takes a single `employee_id` string and parses a bare number as
| a USER id, so targeting a retailer requires the "retailer_" prefix.
|
| Load-more paging and the server-side CSV export live in ItemsObcbResults.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  // Admin console: its own data source and the heads to report on.
  source?: ItemsObcbSource
  heads?: ReportHead[]
}>()

const source = computed<ItemsObcbSource>(
  () =>
    props.source ?? {
      fetch: stockReportsApi.getItemsObcb,
      exportCsv: stockReportsApi.exportItemsObcb,
      loadUsers: () => userDetailsApi.list(undefined, 'stock'),
      loadItems: () => itemsApi.list(),
    },
)
const grouped = computed(() => props.heads !== undefined)

type PartyRole = 'user' | 'retailer'

const roleOptions: { value: PartyRole; label: string }[] = [
  { value: 'user', label: 'Employee' },
  { value: 'retailer', label: 'Retailer' },
]

const flowOptions: { value: 'IN' | 'OUT'; label: string }[] = [
  { value: 'IN', label: 'IN' },
  { value: 'OUT', label: 'OUT' },
]

const filters = reactive({
  head_id: null as number | null,
  party_role: 'user' as PartyRole,
  party_id: null as number | null,
  item_id: null as number | null,
  from_date: '',
  from_time: '',
  to_date: '',
  to_time: '',
  type: null as 'IN' | 'OUT' | null,
})

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])

const userOptions = computed(() => users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })))
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))
const headOptions = computed(() => (props.heads ?? []).map((h) => ({ value: h.id, label: h.name })))

// employee_id is only sent once a party is picked; omitting it reports from
// the head's own perspective, which is the endpoint's default branch.
// Filters only, no paging — shared by the paged search and the CSV export.
function filterQuery(): Omit<ItemsObcbQuery, 'page_no' | 'page_size'> {
  return {
    employee_id: filters.party_id !== null ? `${filters.party_role}_${filters.party_id}` : undefined,
    item_id: filters.item_id ?? undefined,
    from_date: filters.from_date || undefined,
    // The service only applies a time when its own date is present
    // ("$fromDate $fromTime"), so a lone time would silently do nothing.
    from_time: filters.from_date ? filters.from_time || undefined : undefined,
    to_date: filters.to_date || undefined,
    to_time: filters.to_date ? filters.to_time || undefined : undefined,
    type: filters.type ?? undefined,
  }
}

// What the last Search applied, and a counter the result sections watch.
const appliedQuery = ref(filterQuery())
const appliedHeadId = ref<number | null>(null)
const runKey = ref(0)
const loadingCount = ref(0)
const isLoading = computed(() => loadingCount.value > 0)

function onSectionLoading(loading: boolean) {
  loadingCount.value = Math.max(0, loadingCount.value + (loading ? 1 : -1))
}

// One section in the app; in the console one per head (or the one picked).
const sections = computed(() => {
  if (!grouped.value) return [{ key: 'self', title: undefined, headId: undefined as number | undefined }]
  return (props.heads ?? [])
    .filter((h) => appliedHeadId.value === null || h.id === appliedHeadId.value)
    .map((h) => ({ key: `head-${h.id}`, title: h.name, headId: h.id as number | undefined }))
})

function runSearch() {
  if (isLoading.value) return
  appliedQuery.value = filterQuery()
  appliedHeadId.value = filters.head_id
  runKey.value++
}

// The app's Export button (the console exports per head section).
const results = ref<InstanceType<typeof ItemsObcbResults>[]>([])
const isExporting = computed(() => results.value.some((r) => r?.isExporting))
function exportCsv() {
  void results.value[0]?.exportCsv()
}

async function loadLookups() {
  try {
    const [usersData, itemsData] = await Promise.all([source.value.loadUsers(), source.value.loadItems()])
    users.value = usersData
    items.value = itemsData
  } catch {
    // Non-fatal — the filters just offer fewer options.
  }
}

async function reloadUsers() {
  users.value = await source.value.loadUsers()
}
async function reloadItems() {
  items.value = await source.value.loadItems()
}

function clearFilters() {
  filters.head_id = null
  filters.party_role = 'user'
  filters.party_id = null
  filters.item_id = null
  filters.from_date = ''
  filters.from_time = ''
  filters.to_date = ''
  filters.to_time = ''
  filters.type = null
  runSearch()
}

onMounted(loadLookups)
</script>

<template>
  <div>
    <PageHeader
      title="Items OB &amp; CB"
      :description="
        grouped
          ? 'Running opening and closing balances per stock movement, for each head.'
          : 'Running opening and closing balances per stock movement, for the signed-in head.'
      "
    />

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseSelect
          v-if="grouped"
          id="report_head"
          v-model="filters.head_id"
          label="Head"
          size="sm"
          placeholder="All heads…"
          :options="headOptions"
        />
        <BaseSelect v-model="filters.party_role" label="Party role" size="sm" :options="roleOptions" />
        <BaseSelect
          v-model="filters.party_id"
          label="Party"
          size="sm"
          placeholder="All (head view)…"
          :options="userOptions"
          :reload="reloadUsers"
        />
        <BaseSelect
          v-model="filters.item_id"
          label="Item"
          size="sm"
          placeholder="All items…"
          :options="itemOptions"
          :reload="reloadItems"
        />
        <BaseSelect v-model="filters.type" label="Flow" size="sm" placeholder="IN and OUT…" :options="flowOptions" />
        <BaseInput v-model="filters.from_date" label="From date" type="date" size="sm" clearable />
        <BaseInput
          v-model="filters.from_time"
          label="From time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.from_date"
          clearable
        />
        <BaseInput v-model="filters.to_date" label="To date" type="date" size="sm" clearable />
        <BaseInput
          v-model="filters.to_time"
          label="To time"
          type="time"
          step="1"
          size="sm"
          :disabled="!filters.to_date"
          clearable
        />
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Times narrow their own date — set a date first. Without a time, the range runs from 00:00:00 to 23:59:59.
      </p>

      <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
        <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">Clear</BaseButton>
        <BaseButton
          v-if="!grouped"
          variant="secondary"
          type="button"
          :disabled="isExporting || isLoading"
          @click="exportCsv"
        >
          {{ isExporting ? 'Exporting…' : 'Export CSV' }}
        </BaseButton>
        <BaseButton type="button" :disabled="isLoading" @click="runSearch">
          {{ isLoading ? 'Loading…' : 'Search' }}
        </BaseButton>
      </div>
    </BaseCard>

    <p v-if="grouped && !sections.length" class="rounded-lg border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
      No heads to report on.
    </p>

    <div class="flex flex-col gap-4">
      <ItemsObcbResults
        v-for="section in sections"
        :key="section.key"
        ref="results"
        :title="section.title"
        :fetch="source.fetch"
        :export-csv="source.exportCsv"
        :query="{ ...appliedQuery, head_id: section.headId }"
        :run-key="runKey"
        @loading="onSectionLoading"
      />
    </div>
  </div>
</template>
