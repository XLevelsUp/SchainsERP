<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import { rolesApi } from '@/lib/rolesApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { DataTableColumn, Role, RoleFormValues } from '@/types'

const roles = ref<Role[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<Role>[] = [
  { key: 'role', label: 'Role' },
  { key: 'touch', label: 'Touch' },
  { key: 'added_at', label: 'Added' },
  { key: 'id', label: '' },
]

async function loadRoles() {
  isLoading.value = true
  loadError.value = ''
  try {
    roles.value = await rolesApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load roles.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadRoles)

const filteredRoles = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return roles.value
  return roles.value.filter((role) => role.role.toLowerCase().includes(query))
})

const emptyForm: RoleFormValues = {
  role: '',
  touch: null,
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<RoleFormValues>({ ...emptyForm })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  editingId.value = null
  Object.assign(form, emptyForm)
  formError.value = ''
  isFormOpen.value = true
}

function openEditForm(role: Role) {
  editingId.value = role.id
  Object.assign(form, { role: role.role, touch: role.touch })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleSubmit() {
  if (!form.role.trim()) {
    formError.value = 'Role name is required.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    if (editingId.value !== null) {
      await rolesApi.update(editingId.value, { ...form })
    } else {
      await rolesApi.create({ ...form })
    }
    isFormOpen.value = false
    await loadRoles()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save role.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(role: Role) {
  deletingId.value = role.id
  try {
    await rolesApi.remove(role.id)
    await loadRoles()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete role.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Roles" description="Manage user roles and their default touch.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadRoles">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New role</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit role' : 'New role' }}
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

      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="handleSubmit">
        <BaseInput
          id="role"
          v-model="form.role"
          label="Role name"
          placeholder="Admin"
          required
          :error="formError"
        />
        <BaseInput
          id="touch"
          :model-value="form.touch === null ? '' : String(form.touch)"
          label="Touch"
          type="number"
          placeholder="Optional"
          @update:model-value="(v) => (form.touch = v === '' ? null : Number(v))"
        />

        <div class="flex items-center gap-3 sm:col-span-2">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create role' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="closeForm">Cancel</BaseButton>
        </div>
      </form>
    </BaseCard>

    <div class="mb-4 flex items-center gap-2">
      <BaseInput
        v-model="searchQuery"
        type="search"
        :icon="Search"
        placeholder="Search roles…"
        aria-label="Search roles"
        class="w-full max-w-xs"
      />
    </div>

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
      Loading roles…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredRoles"
      empty-message="No roles yet. Add your first role to get started."
    >
      <template #touch="{ value }">
        {{ value === null ? '—' : value }}
      </template>

      <template #added_at="{ value }">{{ formatDateTime(value as string) }}</template>

      <template #id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit role"
            @click="openEditForm(row as Role)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete ${(row as Role).role}?`"
            @confirm="handleDelete(row as Role)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete role"
                :disabled="deletingId === (row as Role).id"
                @click="toggle"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </ConfirmPopover>
        </div>
      </template>
    </DataTable>
  </div>
</template>