<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Check, Eye, Plus, RefreshCw, Search, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import { isRoleActive } from '@/lib/rolesApi'
import { useToastStore } from '@/stores/toast'
import type { DataTableColumn, Role } from '@/types'
import { rolesApi } from '../lib/resources'

/*
|--------------------------------------------------------------------------
| Admin console — Roles (agreed 2026-11-20)
|--------------------------------------------------------------------------
| The roles users can be given. Admins create and view them: a name and an
| active status. No edit or delete.
|
| Inactive roles drop out of the Role pickers for new assignments; users
| already on one keep it. Names are saved in capitals and must be unique
| (RoleController), like the existing HEAD / EMPLOYEE / CUSTOMER.
|
| The list is small and comes back whole, so search and the status filter
| run here. The status filter starts on Active, as the old screen did.
|--------------------------------------------------------------------------
*/

type StatusFilter = 'all' | 'active' | 'inactive'

const statusOptions: { value: StatusFilter; label: string }[] = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'all', label: 'All' },
]

// Table row: the role plus its position in the filtered list (the # column).
interface RoleRow extends Role {
  position: number
}

const columns: DataTableColumn<RoleRow>[] = [
  { key: 'position', label: '#' },
  { key: 'role', label: 'Role' },
  { key: 'is_active', label: 'Status' },
  { key: 'id', label: '' },
]

const toast = useToastStore()

const roles = ref<Role[]>([])
const isLoading = ref(false)
const loadError = ref('')
const search = ref('')
const statusFilter = ref<StatusFilter>('active')

async function loadRoles() {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    // The API lists newest first; the screen reads oldest first, like the
    // old one (HEAD, EMPLOYEE, CUSTOMER, ...).
    roles.value = [...(await rolesApi.list())].sort((a, b) => a.id - b.id)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load roles.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadRoles)

const rows = computed<RoleRow[]>(() => {
  const query = search.value.trim().toLowerCase()
  return roles.value
    .filter((role) => !query || role.role.toLowerCase().includes(query))
    .filter((role) => {
      if (statusFilter.value === 'all') return true
      return isRoleActive(role) === (statusFilter.value === 'active')
    })
    .map((role, index) => ({ ...role, position: index + 1 }))
})

const summary = computed(() => {
  if (!roles.value.length) return ''
  return `Showing ${rows.value.length} of ${roles.value.length} roles`
})

const isFiltered = computed(() => search.value.trim() !== '' || statusFilter.value !== 'all')

// ---- create ---------------------------------------------------------------
const isFormOpen = ref(false)
const form = reactive({ role: '', is_active: true })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  Object.assign(form, { role: '', is_active: true })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleCreate() {
  if (isSaving.value) return
  formError.value = ''
  if (!form.role.trim()) {
    formError.value = 'Role name is required.'
    return
  }

  isSaving.value = true
  try {
    const saved = await rolesApi.create({ role: form.role.trim(), touch: null, is_active: form.is_active })
    isFormOpen.value = false
    toast.show(`Role ${saved.role} created.`, 'success')
    // Show the new role even when the status filter would hide it.
    if (statusFilter.value !== 'all' && isRoleActive(saved) !== (statusFilter.value === 'active')) {
      statusFilter.value = 'all'
    }
    search.value = ''
    await loadRoles()
  } catch (err) {
    formError.value =
      err instanceof ApiError
        ? (err.errors?.role?.[0] ?? err.errors?.is_active?.[0] ?? err.message)
        : 'Failed to create the role.'
  } finally {
    isSaving.value = false
  }
}

// ---- view -----------------------------------------------------------------
const viewing = ref<Role | null>(null)
</script>

<template>
  <div>
    <PageHeader
      title="Roles"
      description="The roles users can be given. Inactive roles can't be picked for new users; users already on one keep it."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="loadRoles">
          Refresh
        </BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New role</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">New role</h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close form"
          @click="closeForm"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <form class="flex flex-col gap-4" @submit.prevent="handleCreate">
        <div class="max-w-sm">
          <BaseInput
            id="role_name"
            v-model="form.role"
            label="Role name"
            placeholder="JOB WORK"
            required
            :error="formError"
          />
          <p class="mt-1 text-xs text-slate-500">Saved in capitals. Must not match an existing role.</p>
        </div>
        <BaseCheckbox v-model="form.is_active" label="Active" />

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Creating…' : 'Create role' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="closeForm">
            Cancel
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <BaseInput
        v-model="search"
        type="search"
        :icon="Search"
        placeholder="Search roles…"
        aria-label="Search roles"
        class="w-full max-w-xs"
      />
      <BaseSelect
        v-model="statusFilter"
        aria-label="Filter by status"
        size="sm"
        class="w-40"
        :options="statusOptions"
      />
      <span v-if="summary" class="ml-auto text-sm text-slate-500 tabular-nums">{{ summary }}</span>
    </div>

    <div
      v-if="loadError"
      class="mb-4 flex items-center justify-between gap-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      <span>{{ loadError }}</span>
      <button type="button" class="font-medium underline" @click="loadRoles">Try again</button>
    </div>

    <div
      v-if="isLoading && !roles.length"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading roles…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="rows"
      :empty-message="isFiltered ? 'No roles match these filters.' : 'No roles yet. Create the first one.'"
    >
      <template #position="{ value }">
        <span class="text-slate-500 tabular-nums">{{ value }}</span>
      </template>

      <template #role="{ value }">
        <span class="font-medium text-slate-900">{{ value }}</span>
      </template>

      <template #is_active="{ row }">
        <span
          v-if="isRoleActive(row)"
          class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700"
        >
          <Check class="h-3.5 w-3.5" aria-hidden="true" /> Active
        </span>
        <span
          v-else
          class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600"
        >
          <X class="h-3.5 w-3.5" aria-hidden="true" /> Inactive
        </span>
      </template>

      <template #id="{ row }">
        <div class="flex justify-end">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            :aria-label="`View ${row.role}`"
            @click="viewing = row"
          >
            <Eye class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>

    <BaseModal v-if="viewing" :title="viewing.role" max-width="max-w-md" @close="viewing = null">
      <dl class="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-3 text-sm">
        <dt class="text-slate-500">Role</dt>
        <dd class="font-medium text-slate-900">{{ viewing.role }}</dd>
        <dt class="text-slate-500">Status</dt>
        <dd class="text-slate-900">{{ isRoleActive(viewing) ? 'Active' : 'Inactive' }}</dd>
        <dt class="text-slate-500">Added</dt>
        <dd class="text-slate-900">{{ formatDateTime(viewing.added_at) }}</dd>
        <dt class="text-slate-500">ID</dt>
        <dd class="text-slate-900 tabular-nums">{{ viewing.id }}</dd>
      </dl>
      <div class="flex justify-end border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" @click="viewing = null">Close</BaseButton>
      </div>
    </BaseModal>
  </div>
</template>
