<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
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
| Full CRUD as of PR #43–#45, which added store() and destroy(). This screen
| was read + edit only before that, purely because the routes did not exist.
|
| One inline card serves both create and edit rather than a separate modal,
| matching the rest of this screen and keeping the operator on one surface.
| `formMode` decides which; `editingId` is null in create mode.
|
| Relations come back from index() AND store(), but NOT from update(). So a
| create can push the returned row straight into the table with its names
| intact, while an edit merges the returned scalars into the row already
| held — otherwise the user/touch names would blank out until the next
| refresh.
|
| Delete is a hard delete server-side. The inline "Active" checkbox is the
| reversible option and stays the one to reach for; ConfirmPopover guards
| the destructive one, same as ItemsView and the other CRUD screens.
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
// Create / edit form — one card, two modes
// ---------------------------------------------------------------------------

const formMode = ref<'create' | 'edit' | null>(null)
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

function openCreate() {
  formMode.value = 'create'
  editingId.value = null
  // Pre-fill from the user filter when one is set — the operator has
  // already said which user they are working on.
  editForm.user_id = filters.user_id
  editForm.customer_touch_id = null
  editForm.is_active = true
  editError.value = ''
}

function openEdit(row: CustomerTouchUserMapping) {
  formMode.value = 'edit'
  editingId.value = row.id
  editForm.user_id = row.user_id
  editForm.customer_touch_id = row.customer_touch_id
  editForm.is_active = row.is_active
  editError.value = ''
}

function closeEdit() {
  formMode.value = null
  editingId.value = null
  editError.value = ''
}

// Both modes need the same two selections; returns null when valid.
function validateForm(): string | null {
  if (editForm.user_id === null) return 'Select a user.'
  if (editForm.customer_touch_id === null) return 'Select a customer touch.'
  return null
}

// store() and update() report a duplicate (user_id, customer_touch_id) pair
// differently — update() surfaces a 422 bag, store() catches \Exception and
// returns a 500 carrying the raw driver text. Normalise both so the operator
// gets the same sentence either way.
function formErrorFrom(err: unknown, fallback: string): string {
  if (!(err instanceof ApiError)) return fallback
  const detail = err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message
  if (/unique|duplicate/i.test(detail)) {
    return 'That user is already mapped to this customer touch.'
  }
  return detail
}

async function saveEdit() {
  if (isSavingEdit.value) return

  const invalid = validateForm()
  if (invalid) {
    editError.value = invalid
    return
  }

  isSavingEdit.value = true
  editError.value = ''
  try {
    if (formMode.value === 'create') {
      const created = await customerTouchMappingsApi.create({
        user_id: editForm.user_id!,
        customer_touch_id: editForm.customer_touch_id!,
        is_active: editForm.is_active,
      })
      // store() eager-loads the relations, so this row renders its names
      // without a refetch. Prepend — the table is newest-first by id.
      mappings.value = [created, ...mappings.value]
      closeEdit()
      toast.show('Mapping created.', 'success')
    } else {
      const row = editingRow.value
      if (!row) return
      const result = await customerTouchMappingsApi.update(row.id, {
        user_id: editForm.user_id!,
        customer_touch_id: editForm.customer_touch_id!,
        is_active: editForm.is_active,
      })
      applyUpdate(row, result)
      // The row's cached relations now describe the OLD user/touch, so drop
      // them and let the name lookups take over until the next refresh.
      row.user = null
      row.customer_touch = null
      closeEdit()
      toast.show('Mapping updated.', 'success')
    }
  } catch (err) {
    editError.value = formErrorFrom(
      err,
      formMode.value === 'create'
        ? 'Failed to create the mapping.'
        : 'Failed to update the mapping.',
    )
  } finally {
    isSavingEdit.value = false
  }
}

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

const deletingId = ref<number | null>(null)

async function handleDelete(row: CustomerTouchUserMapping) {
  if (deletingId.value !== null) return
  deletingId.value = row.id
  const label = userLabelFor(row)
  try {
    await customerTouchMappingsApi.remove(row.id)
    mappings.value = mappings.value.filter((m) => m.id !== row.id)
    // Editing the row that just vanished would post to a dead id.
    if (editingId.value === row.id) closeEdit()
    toast.show(`Mapping for ${label} deleted.`, 'success')
  } catch (err) {
    toast.show(
      err instanceof ApiError ? err.message : 'Failed to delete the mapping.',
      'error',
    )
  } finally {
    deletingId.value = null
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
        <BaseButton :icon="Plus" @click="openCreate">New mapping</BaseButton>
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

    <BaseCard v-if="formMode" class="mb-4">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ formMode === 'create' ? 'New mapping' : `Edit mapping #${editingRow?.id}` }}
        </h2>
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
            {{
              isSavingEdit
                ? 'Saving…'
                : formMode === 'create'
                  ? 'Create mapping'
                  : 'Save mapping'
            }}
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
        <div class="flex justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit mapping"
            @click="openEdit(row)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete the mapping for ${userLabelFor(row)}? To keep it but switch it off, use the Active checkbox instead.`"
            @confirm="handleDelete(row)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete mapping"
                :disabled="deletingId === row.id"
                @click="toggle"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </ConfirmPopover>
        </div>
      </template>
    </DataTable>

    <p class="mt-3 text-xs text-slate-500">
      Delete removes the mapping permanently. To suspend a mapping without losing it, clear its
      Active checkbox instead.
    </p>
  </div>
</template>
