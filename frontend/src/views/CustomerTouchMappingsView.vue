<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, Pencil, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { customerTouchMappingsApi } from '@/lib/customerTouchMappingsApi'
import { customerTouchApi } from '@/lib/customerTouchApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type {
  CustomerTouch,
  CustomerTouchUserMapping,
  DataTableColumn,
  UserDetailListItem,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Customer Touch Mappings — /customer-touch-user-mappings (PR #32)
|--------------------------------------------------------------------------
| Which customer touches each user is authorised for. Replaces the legacy
| Yii2 customer-touch-user-mappings index/update screens.
|
| Read + edit only, by design of the API rather than by choice here:
| CustomerTouchUserMappingController implements index() and update() and
| nothing else, and routes/api.php registers only GET and PUT/PATCH. There
| is no store and no destroy, so this screen offers no "New mapping" or
| delete action — flagged to backend in PENDING_WORK.md. Mappings have to
| be created directly in the database until those routes exist.
|
| Relations only come back on the list endpoint. update() returns the bare
| model, so a save merges the returned scalars into the row already held
| rather than replacing it — otherwise the user/touch names would blank out
| until the next refresh.
|
| The user filter is server-side (?user_id=), matching the endpoint. The
| touch filter is client-side: the endpoint has no customer_touch_id param.
|--------------------------------------------------------------------------
*/

const toast = useToastStore()

const mappings = ref<CustomerTouchUserMapping[]>([])
const users = ref<UserDetailListItem[]>([])
const touches = ref<CustomerTouch[]>([])

const isLoading = ref(false)
const loadError = ref('')

const filters = reactive({
  user_id: null as number | null,
  customer_touch_id: null as number | null,
  active_only: false,
})

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const touchOptions = computed(() =>
  touches.value.map((t) => ({ value: t.item_id, label: t.item_name })),
)

// Names for rows whose relations are missing — update() strips them, and a
// mapping can outlive a lookup the list didn't return.
const userNameById = computed(() => {
  const map = new Map<number, string>()
  for (const u of users.value) map.set(u.id, u.full_name || u.name)
  return map
})
const touchNameById = computed(() => {
  const map = new Map<number, string>()
  for (const t of touches.value) map.set(t.item_id, t.item_name)
  return map
})

function userLabelFor(row: CustomerTouchUserMapping): string {
  return row.user?.name ?? userNameById.value.get(row.user_id) ?? `#${row.user_id}`
}
function touchLabelFor(row: CustomerTouchUserMapping): string {
  return (
    row.customer_touch?.item_name ??
    touchNameById.value.get(row.customer_touch_id) ??
    `#${row.customer_touch_id}`
  )
}

const visibleMappings = computed(() =>
  mappings.value.filter((m) => {
    if (filters.customer_touch_id !== null && m.customer_touch_id !== filters.customer_touch_id) {
      return false
    }
    if (filters.active_only && !m.is_active) return false
    return true
  }),
)

const columns: DataTableColumn<CustomerTouchUserMapping>[] = [
  { key: 'user_id', label: 'User' },
  { key: 'customer_touch_id', label: 'Customer Touch' },
  { key: 'is_active', label: 'Status' },
  { key: 'updated_at', label: 'Updated' },
  { key: 'id', label: '' },
]

async function loadMappings() {
  isLoading.value = true
  loadError.value = ''
  try {
    mappings.value = await customerTouchMappingsApi.list(filters.user_id ?? undefined)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load mappings.'
  } finally {
    isLoading.value = false
  }
}

async function loadLookups() {
  try {
    const [usersData, touchesData] = await Promise.all([
      userDetailsApi.list(undefined, 'stock'),
      customerTouchApi.list(),
    ])
    users.value = usersData
    touches.value = touchesData
  } catch {
    // Non-fatal — filters offer fewer options and the table falls back to
    // ids for any name it can't resolve.
  }
}

// ---------------------------------------------------------------------------
// Inline active toggle
// ---------------------------------------------------------------------------

// Ids currently being written, so each row can disable just its own control
// instead of locking the whole table.
const savingIds = ref<number[]>([])
const isSavingRow = (id: number) => savingIds.value.includes(id)

// Merges only the scalars the API echoes back; relations are left intact
// because update() does not return them.
function applyUpdate(target: CustomerTouchUserMapping, result: CustomerTouchUserMapping) {
  target.user_id = result.user_id
  target.customer_touch_id = result.customer_touch_id
  target.is_active = result.is_active
  target.updated_at = result.updated_at
}

async function toggleActive(row: CustomerTouchUserMapping) {
  if (isSavingRow(row.id)) return
  const next = !row.is_active

  savingIds.value = [...savingIds.value, row.id]
  // Optimistic: the checkbox reflects the intent immediately and reverts if
  // the write fails.
  row.is_active = next
  try {
    const result = await customerTouchMappingsApi.update(row.id, { is_active: next })
    applyUpdate(row, result)
    toast.show(`${userLabelFor(row)} — ${next ? 'enabled' : 'disabled'}.`, 'success')
  } catch (err) {
    row.is_active = !next
    toast.show(
      err instanceof ApiError ? err.message : 'Failed to update the mapping.',
      'error',
    )
  } finally {
    savingIds.value = savingIds.value.filter((id) => id !== row.id)
  }
}

// ---------------------------------------------------------------------------
// Edit form (reassign the user or the touch)
// ---------------------------------------------------------------------------

const editingId = ref<number | null>(null)
const editForm = reactive({
  user_id: null as number | null,
  customer_touch_id: null as number | null,
  is_active: true,
})
const editError = ref('')
const isSavingEdit = ref(false)

const editingRow = computed(() =>
  editingId.value === null ? null : (mappings.value.find((m) => m.id === editingId.value) ?? null),
)

function openEdit(row: CustomerTouchUserMapping) {
  editingId.value = row.id
  editForm.user_id = row.user_id
  editForm.customer_touch_id = row.customer_touch_id
  editForm.is_active = row.is_active
  editError.value = ''
}

function closeEdit() {
  editingId.value = null
  editError.value = ''
}

async function saveEdit() {
  const row = editingRow.value
  if (!row || isSavingEdit.value) return

  if (editForm.user_id === null) {
    editError.value = 'Select a user.'
    return
  }
  if (editForm.customer_touch_id === null) {
    editError.value = 'Select a customer touch.'
    return
  }

  isSavingEdit.value = true
  editError.value = ''
  try {
    const result = await customerTouchMappingsApi.update(row.id, {
      user_id: editForm.user_id,
      customer_touch_id: editForm.customer_touch_id,
      is_active: editForm.is_active,
    })
    applyUpdate(row, result)
    // The row's cached relations now describe the OLD user/touch, so drop
    // them and let the name lookups take over until the next refresh.
    row.user = null
    row.customer_touch = null
    closeEdit()
    toast.show('Mapping updated.', 'success')
  } catch (err) {
    if (err instanceof ApiError) {
      editError.value = err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message
    } else {
      editError.value = 'Failed to update the mapping.'
    }
  } finally {
    isSavingEdit.value = false
  }
}

function clearFilters() {
  filters.user_id = null
  filters.customer_touch_id = null
  filters.active_only = false
  loadMappings()
}

onMounted(async () => {
  await loadLookups()
  await loadMappings()
})
</script>

<template>
  <div>
    <PageHeader
      title="Customer Touch Mappings"
      description="Which customer touches each user is authorised for."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="loadMappings">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <BaseSelect
          v-model="filters.user_id"
          label="User"
          size="sm"
          placeholder="All users…"
          :options="userOptions"
          @update:model-value="loadMappings"
        />
        <BaseSelect
          v-model="filters.customer_touch_id"
          label="Customer Touch"
          size="sm"
          placeholder="All touches…"
          :options="touchOptions"
        />
        <div class="flex items-end justify-between gap-3">
          <BaseCheckbox v-model="filters.active_only" label="Active only" />
          <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">
            Clear
          </BaseButton>
        </div>
      </div>
      <p class="mt-2 text-xs text-slate-500">
        The user filter runs server-side; touch and status filter the loaded rows.
      </p>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <BaseCard v-if="editingRow" class="mb-4">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">Edit mapping #{{ editingRow.id }}</h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close form"
          @click="closeEdit"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <form class="flex flex-col gap-4" @submit.prevent="saveEdit">
        <div class="grid gap-3 sm:grid-cols-2">
          <BaseSelect
            v-model="editForm.user_id"
            label="User"
            required
            placeholder="Select a user…"
            :options="userOptions"
          />
          <BaseSelect
            v-model="editForm.customer_touch_id"
            label="Customer Touch"
            required
            placeholder="Select a customer touch…"
            :options="touchOptions"
          />
        </div>
        <BaseCheckbox v-model="editForm.is_active" label="Active" />

        <p v-if="editError" class="text-sm text-red-600">{{ editError }}</p>

        <p class="text-xs text-slate-500">
          A user can only be mapped to a given touch once — the table has a unique constraint on
          the pair, so reassigning onto an existing combination is rejected.
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
          <BaseButton variant="secondary" type="button" :disabled="isSavingEdit" @click="closeEdit">
            Cancel
          </BaseButton>
          <BaseButton type="submit" :disabled="isSavingEdit">
            {{ isSavingEdit ? 'Saving…' : 'Save mapping' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <p class="mb-2 text-sm text-slate-500">
      Showing <span class="font-medium text-slate-700">{{ visibleMappings.length }}</span> of
      <span class="font-medium text-slate-700">{{ mappings.length }}</span> mappings
    </p>

    <DataTable
      :columns="columns"
      :rows="visibleMappings"
      :empty-message="isLoading ? 'Loading…' : 'No mappings match these filters.'"
    >
      <template #user_id="{ row }">
        <span class="font-medium text-slate-900">{{ userLabelFor(row) }}</span>
      </template>
      <template #customer_touch_id="{ row }">
        {{ touchLabelFor(row) }}
      </template>
      <template #is_active="{ row }">
        <BaseCheckbox
          :model-value="row.is_active"
          :disabled="isSavingRow(row.id)"
          :label="row.is_active ? 'Active' : 'Inactive'"
          @update:model-value="toggleActive(row)"
        />
      </template>
      <template #updated_at="{ row }">
        <span class="whitespace-nowrap text-slate-500">{{ formatDateTime(row.updated_at) }}</span>
      </template>
      <template #id="{ row }">
        <div class="flex justify-end">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit mapping"
            @click="openEdit(row)"
          >
            <Pencil class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>

    <p class="mt-3 text-xs text-slate-500">
      Mappings can be listed and edited here, but not created or deleted — the backend exposes
      only index and update for this resource. See <code>PENDING_WORK.md</code>.
    </p>
  </div>
</template>
