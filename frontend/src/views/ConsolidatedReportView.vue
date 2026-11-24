<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConsolidatedResults from '@/components/reports/ConsolidatedResults.vue'
import { stockReportsApi } from '@/lib/stockReportsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { userOptionLabel } from '@/lib/userLabel'
import type { ConsolidatedSource, ReportHead } from '@/lib/reportSources'
import type { ConsolidatedQuery, Item, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Consolidated Report — GET /stock/reports/consolidated (PR #31)
|--------------------------------------------------------------------------
| Outward and inward history for one employee or retailer, side by side,
| with totals aggregated in PostgreSQL over the whole filtered set.
|
|  - App: the head is the signed-in user.
|  - Admin console (2026-11-24): `heads` is passed and the report runs once
|    per head into its own section ("grouped by head"), each with its own
|    paging and export; the Head filter narrows it to one head.
|
| Party role picks WHICH KEY is sent (user_id vs retailer_id) rather than
| prefixing a value the way items-obcb does.
|
| The panes, paging and export live in ConsolidatedResults.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  source?: ConsolidatedSource
  heads?: ReportHead[]
}>()

const source = computed<ConsolidatedSource>(
  () =>
    props.source ?? {
      fetch: stockReportsApi.getConsolidated,
      exportCsv: stockReportsApi.exportConsolidated,
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

const filters = reactive({
  head_id: null as number | null,
  party_role: 'user' as PartyRole,
  party_id: null as number | null,
  item_id: null as number | null,
  from_date: '',
  from_time: '',
  to_date: '',
  to_time: '',
})

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])

const userOptions = computed(() => users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })))
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))
const headOptions = computed(() => (props.heads ?? []).map((h) => ({ value: h.id, label: h.name })))

// Sends exactly one of user_id / retailer_id — the key is what tells the
// backend which party a bare id refers to. Filters only, no paging.
function filterQuery(): Omit<ConsolidatedQuery, 'page_size' | 'page_no_out' | 'page_no_in'> {
  const query: ConsolidatedQuery = {
    item_id: filters.item_id ?? undefined,
    from_date: filters.from_date || undefined,
    // A time is only applied server-side when its own date is present.
    from_time: filters.from_date ? filters.from_time || undefined : undefined,
    to_date: filters.to_date || undefined,
    to_time: filters.to_date ? filters.to_time || undefined : undefined,
  }
  if (filters.party_id !== null) {
    if (filters.party_role === 'retailer') query.retailer_id = filters.party_id
    else query.user_id = filters.party_id
  }
  return query
}

const appliedQuery = ref(filterQuery())
const appliedHeadId = ref<number | null>(null)
const runKey = ref(0)
const loadingCount = ref(0)
const isLoading = computed(() => loadingCount.value > 0)

function onSectionLoading(loading: boolean) {
  loadingCount.value = Math.max(0, loadingCount.value + (loading ? 1 : -1))
}

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

const results = ref<InstanceType<typeof ConsolidatedResults>[]>([])
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
  runSearch()
}

onMounted(loadLookups)
</script>

<template>
  <div>
    <PageHeader
      title="Consolidated Report"
      :description="
        grouped
          ? 'Outward and inward stock history for each head, with totals across the full filtered range.'
          : 'Outward and inward stock history for one party, with totals across the full filtered range.'
      "
    />

    <BaseCard :padded="false" class="mb-3 p-3">
      <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
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
        <div v-if="!grouped" class="hidden lg:block"></div>
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

      <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-slate-500">
          Times narrow their own date — set a date first. Without a time, the range runs from 00:00:00 to 23:59:59.
        </p>
        <div class="flex items-center gap-2">
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
      </div>
    </BaseCard>

    <p v-if="grouped && !sections.length" class="rounded-lg border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
      No heads to report on.
    </p>

    <div class="flex flex-col gap-4">
      <ConsolidatedResults
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
