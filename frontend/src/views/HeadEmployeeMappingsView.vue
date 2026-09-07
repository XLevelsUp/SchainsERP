<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Pencil, Trash2, RefreshCw, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { headEmployeeMappingsApi } from '@/lib/headEmployeeMappingsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import type { HeadEmployeeMappingGroup, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Head Employee Mappings — /head-employee-mappings (PR #34)
|--------------------------------------------------------------------------
| Which head(s) each employee reports to. Replaces the legacy "Head User
| Mappings" screen. The backend was rewritten in PR #34 to a bulk, grouped
| shape — there is no per-mapping show/destroy any more:
|   - index()  returns one row per employee, heads[] nested underneath
|   - store()  adds head_ids to an employee's set (ADDS to what exists)
|   - update() REPLACES an employee's whole head-set (delete-not-in-set +
|               insert-missing) — this is how a head gets removed
|
| The employee/head pickers deliberately use the full unfiltered user list
| rather than ?type=EMPLOYEE/HEAD — CashManagementView's picker carries the
| same choice: role_id numbering has proven unreliable against this seed
| data (role_id=1 is CUSTOMER per RoleSeeder, not HEAD), so every screen
| that assigns heads shows every user and lets the operator pick by name.
|
| No pagination — the backend returns the full set in one response, same as
| Customer Touch Mappings.
|--------------------------------------------------------------------------
*/

type ActiveFilter = 'all' | 'active' | 'inactive'

const statusOptions: { value: ActiveFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

function toQueryValue(filter: ActiveFilter): boolean | undefined {
  if (filter === 'active') return true
  if (filter === 'inactive') return false
  return undefined
}

const filters = reactive({
  employee_status: 'all' as ActiveFilter,
  head_status: 'all' as ActiveFilter,
  employee_name: '',
})

const groups = ref<HeadEmployeeMappingGroup[]>([])
const users = ref<UserDetailListItem[]>([])

const isLoading = ref(false)
const loadError = ref('')

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)

const visibleGroups = computed(() => {
  const query = filters.employee_name.trim().toLowerCase()
  if (!query) return groups.value
  return groups.value.filter((g) => (g.employee_name ?? '').toLowerCase().includes(query))
})

async function loadGroups() {
  isLoading.value = true
  loadError.value = ''
  try {
    groups.value = await headEmployeeMappingsApi.list({
      employee_is_active: toQueryValue(filters.employee_status),
      head_is_active: toQueryValue(filters.head_status),
    })
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load mappings.'
  } finally {
    isLoading.value = false
  }
}

async function loadUsers() {
  try {
    users.value = await userDetailsApi.list()
  } catch {
    // Non-fatal — the pickers just offer fewer options.
  }
}

function clearFilters() {
  filters.employee_status = 'all'
  filters.head_status = 'all'
  filters.employee_name = ''
  loadGroups()
}

// ---------------------------------------------------------------------------
// Add / edit form
// ---------------------------------------------------------------------------

const isFormOpen = ref(false)
const editingEmployeeId = ref<number | null>(null)
const form = reactive({
  employee_id: null as number | null,
  head_ids: [] as { head_id: number | null }[],
})
const formError = ref('')
const isSaving = ref(false)

function addHeadRow() {
  form.head_ids.push({ head_id: null })
}
function removeHeadRow(index: number) {
  form.head_ids.splice(index, 1)
}

function openCreate() {
  editingEmployeeId.value = null
  form.employee_id = null
  form.head_ids = [{ head_id: null }]
  formError.value = ''
  isFormOpen.value = true
}

function openEdit(group: HeadEmployeeMappingGroup) {
  editingEmployeeId.value = group.employee_id
  form.employee_id = group.employee_id
  form.head_ids = group.heads.map((h) => ({ head_id: h.head_id }))
  if (form.head_ids.length === 0) form.head_ids.push({ head_id: null })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
  formError.value = ''
}

async function saveForm() {
  if (isSaving.value) return

  const headIds = form.head_ids
    .map((h) => h.head_id)
    .filter((id): id is number => id !== null)

  if (editingEmployeeId.value === null && form.employee_id === null) {
    formError.value = 'Select an employee.'
    return
  }
  if (headIds.length === 0) {
    formError.value = 'Select at least one head.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    if (editingEmployeeId.value !== null) {
      await headEmployeeMappingsApi.update(editingEmployeeId.value, { head_ids: headIds })
    } else {
      await headEmployeeMappingsApi.create({
        employee_id: form.employee_id as number,
        head_ids: headIds,
      })
    }
    closeForm()
    await loadGroups()
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message
    } else {
      formError.value = 'Failed to save the mapping.'
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  await loadUsers()
  await loadGroups()
})
</script>

<template>
  <div>
    <PageHeader
      title="Head Employee Mappings"
      description="Which head(s) each employee reports to."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="loadGroups">
          Refresh
        </BaseButton>
        <BaseButton :icon="Plus" @click="openCreate">New mapping</BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput
          v-model="filters.employee_name"
          label="Employee name"
          size="sm"
          placeholder="Search employees…"
        />
        <BaseSelect
          v-model="filters.employee_status"
          label="Employee status"
          size="sm"
          :options="statusOptions"
          @update:model-value="loadGroups"
        />
        <BaseSelect
          v-model="filters.head_status"
          label="Head status"
          size="sm"
          :options="statusOptions"
          @update:model-value="loadGroups"
        />
        <div class="flex items-end">
          <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">
            Clear
          </BaseButton>
        </div>
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Employee name filters the loaded list; status filters run server-side.
      </p>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <BaseCard v-if="isFormOpen" class="mb-4">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingEmployeeId !== null ? 'Edit mapping' : 'New mapping' }}
        </h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close form"
          @click="closeForm"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <form class="flex flex-col gap-4" @submit.prevent="saveForm">
        <BaseSelect
          v-model="form.employee_id"
          label="Employee"
          required
          placeholder="Select an employee…"
          :options="userOptions"
          :disabled="editingEmployeeId !== null"
        />

        <div>
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Heads</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addHeadRow">
              Add head
            </BaseButton>
          </div>
          <div
            v-for="(row, index) in form.head_ids"
            :key="index"
            class="mb-3 flex items-end gap-3"
          >
            <BaseSelect
              v-model="row.head_id"
              label="Head (user)"
              size="sm"
              class="flex-1"
              placeholder="Select a user…"
              :options="userOptions"
            />
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove head"
              @click="removeHeadRow(index)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
        </div>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

        <p class="text-xs text-slate-500">
          {{
            editingEmployeeId !== null
              ? 'Saving replaces this employee’s whole set of heads — remove a row to unassign that head.'
              : 'This adds the selected heads to the employee’s set; it will not remove any existing ones.'
          }}
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
          <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="closeForm">
            Cancel
          </BaseButton>
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : 'Save mapping' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <p class="mb-2 text-sm text-slate-500">
      Showing <span class="font-medium text-slate-700">{{ visibleGroups.length }}</span> of
      <span class="font-medium text-slate-700">{{ groups.length }}</span> employees
    </p>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <th scope="col" class="px-4 py-2 text-left">#</th>
            <th scope="col" class="px-3 py-2 text-left">Employee</th>
            <th scope="col" class="px-3 py-2 text-left">Heads</th>
            <th scope="col" class="px-4 py-2 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="visibleGroups.length === 0">
            <td colspan="4" class="px-4 py-8 text-center text-slate-500">
              {{ isLoading ? 'Loading…' : 'No mappings match these filters.' }}
            </td>
          </tr>
          <tr v-for="(group, index) in visibleGroups" :key="group.employee_id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500">{{ index + 1 }}</td>
            <td class="px-3 py-3 font-medium text-slate-900">
              {{ group.employee_name ?? `#${group.employee_id}` }}
            </td>
            <td class="px-3 py-3">
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="head in group.heads"
                  :key="head.mapping_id"
                  class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                >
                  {{ head.head_name ?? `#${head.head_id}` }}
                </span>
                <span v-if="group.heads.length === 0" class="text-xs text-slate-400">No heads assigned</span>
              </div>
            </td>
            <td class="px-4 py-3 text-center">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                aria-label="Edit mapping"
                @click="openEdit(group)"
              >
                <Pencil class="h-4 w-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
