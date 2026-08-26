<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FlaskConical } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import MetalPickerModal from '@/components/stock/MetalPickerModal.vue'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import type { DataTableColumn, Item, MetalPickerSelection, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Metal Picker — standalone test harness
|--------------------------------------------------------------------------
| Not linked from any real transaction screen — this exists purely to try
| GET /stock-details/available-metals and the MetalPickerModal it powers
| before that modal gets wired into Stock Out / GMS / Item Change for real.
| See MetalPickerModal.vue's header comment for the full context.
|--------------------------------------------------------------------------
*/

const items = ref<Item[]>([])
const users = ref<UserDetailListItem[]>([])
const isLoading = ref(true)
const loadError = ref('')

const itemId = ref<number | null>(null)
const userId = ref<number | null>(null)
const isPickerOpen = ref(false)
const confirmedRows = ref<MetalPickerSelection[] | null>(null)

const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))
const userOptions = computed(() => users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })))

// Backend 400s unless the chosen item's name is literally "Metal" (case-
// insensitive) — flagging that up front rather than only after a failed
// fetch, since nothing in this seed data is named that by default.
const selectedItemIsMetal = computed(() => {
  const item = items.value.find((i) => i.item_id === itemId.value)
  return item ? item.item_name.trim().toLowerCase() === 'metal' : false
})

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [itemsData, usersData] = await Promise.all([
      itemsApi.list(),
      userDetailsApi.list(undefined, 'stock'),
    ])
    items.value = itemsData
    users.value = usersData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load items/users.'
  } finally {
    isLoading.value = false
  }
}
onMounted(loadData)

const resultColumns: DataTableColumn<MetalPickerSelection>[] = [
  { key: 'id', label: 'Stock ID' },
  { key: 'party_name', label: 'Party' },
  { key: 'grams', label: 'Grams' },
  { key: 'touch', label: 'Touch' },
  { key: 'purity', label: 'Purity' },
  { key: 'taken', label: 'Taken' },
  { key: 'wastage', label: 'Wastage' },
  { key: 'rowTotal', label: 'Row total' },
]

function handleConfirm(rows: MetalPickerSelection[]) {
  confirmedRows.value = rows
  isPickerOpen.value = false
}
</script>

<template>
  <div>
    <PageHeader
      title="Metal Picker (Test)"
      description="Prototype for the Metal Selection API (GET /stock-details/available-metals) — not wired into any real transaction screen yet."
    />

    <p class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      <FlaskConical class="mt-0.5 h-4 w-4 shrink-0" />
      Test feature. Confirming a selection here doesn't submit anything — it just shows you the
      computed payload shape so you can verify it before this gets wired into Stock Out / GMS /
      Item Change for real.
    </p>

    <div
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </div>

    <div
      v-if="isLoading"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading items/users…
    </div>

    <template v-else>
      <BaseCard class="mb-6">
        <div class="grid gap-3 sm:grid-cols-2">
          <BaseSelect
            id="metal-user"
            v-model="userId"
            label="User (who holds the stock)"
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
          />
          <BaseSelect
            id="metal-item"
            v-model="itemId"
            label="Item"
            size="sm"
            placeholder="Select an item…"
            :options="itemOptions"
          />
        </div>
        <p v-if="itemId !== null && !selectedItemIsMetal" class="mt-3 text-xs text-amber-700">
          This item isn't named "Metal" — the backend will reject it with a 400. Create/rename an
          item to "Metal" on the Items page first to test the happy path.
        </p>
        <div class="mt-4">
          <BaseButton type="button" :disabled="itemId === null || userId === null" @click="isPickerOpen = true">
            Open Metal Picker
          </BaseButton>
        </div>
      </BaseCard>

      <BaseCard v-if="confirmedRows">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Last confirmed selection</h2>
        <p class="mb-4 text-xs text-slate-500">
          {{ confirmedRows.length }} row(s) with Taken &gt; 0 — this is the shape a real submission
          would fold into the stock endpoint's payload.
        </p>
        <DataTable :columns="resultColumns" :rows="confirmedRows" empty-message="No rows had a Taken value entered.">
          <template #grams="{ value }">{{ Number(value).toFixed(3) }}</template>
          <template #touch="{ value }">{{ Number(value).toFixed(3) }}</template>
          <template #purity="{ value }">{{ Number(value).toFixed(3) }}</template>
          <template #taken="{ value }">{{ Number(value).toFixed(3) }}</template>
          <template #wastage="{ value }">{{ Number(value).toFixed(3) }}</template>
          <template #rowTotal="{ value }">{{ Number(value).toFixed(3) }}</template>
        </DataTable>
      </BaseCard>
    </template>

    <MetalPickerModal
      v-if="isPickerOpen && itemId !== null && userId !== null"
      :item-id="itemId"
      :user-id="userId"
      @close="isPickerOpen = false"
      @confirm="handleConfirm"
    />
  </div>
</template>
