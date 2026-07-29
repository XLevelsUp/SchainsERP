<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { clientsApi } from '@/lib/clientsApi'
import { ApiError } from '@/lib/api'
import type { Client, ClientFormValues, DataTableColumn } from '@/types'

const clients = ref<Client[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<Client>[] = [
  { key: 'name', label: 'Name' },
  { key: 'user_name', label: 'Username' },
  { key: 'phone_no', label: 'Phone' },
  { key: 'category_name', label: 'Category' },
  { key: 'is_active', label: 'Status' },
  { key: 'added_at', label: 'Created' },
  { key: 'user_id', label: '' },
]

async function loadClients() {
  isLoading.value = true
  loadError.value = ''
  try {
    clients.value = await clientsApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load clients.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadClients)

const filteredClients = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return clients.value
  return clients.value.filter(
    (client) =>
      client.name.toLowerCase().includes(query) ||
      client.user_name.toLowerCase().includes(query),
  )
})

const emptyForm: ClientFormValues = {
  name: '',
  user_name: '',
  password: '',
  address: '',
  signature: '',
  code: '',
  phone_no: '',
  remarks: '',
  proff: '',
  role_id: '',
  mailing_name: '',
  category_name: 'BOTH',
  system_id: '',
  is_active: true,
}

const isFormOpen = ref(false)
const form = reactive<ClientFormValues>({ ...emptyForm })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  Object.assign(form, emptyForm)
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleSubmit() {
  isSaving.value = true
  formError.value = ''
  try {
    await clientsApi.create({ ...form })
    isFormOpen.value = false
    await loadClients()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save client.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(client: Client) {
  if (!window.confirm(`Delete "${client.name}"?`)) return
  deletingId.value = client.user_id
  try {
    await clientsApi.remove(client.user_id)
    await loadClients()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete client.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Clients" description="Manage the organizations you work with.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadClients">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New client</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">New client</h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close form"
          @click="closeForm"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div
        v-if="formError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ formError }}
      </div>

      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="handleSubmit">
        <BaseInput id="name" v-model="form.name" label="Name" required />
        <BaseInput id="user_name" v-model="form.user_name" label="Username" required />
        <BaseInput
          id="password"
          v-model="form.password"
          label="Password"
          type="password"
          required
        />
        <BaseInput id="phone_no" v-model="form.phone_no" label="Phone" required />
        <BaseInput id="address" v-model="form.address" label="Address" required />
        <BaseInput id="mailing_name" v-model="form.mailing_name" label="Mailing name" required />
        <BaseInput id="signature" v-model="form.signature" label="Signature" required />
        <BaseInput id="code" v-model="form.code" label="Code" required />
        <BaseInput id="proff" v-model="form.proff" label="Profession" required />
        <BaseInput id="role_id" v-model="form.role_id" label="Role ID" required />
        <BaseInput id="system_id" v-model="form.system_id" label="System ID" required />

        <div class="flex flex-col gap-1.5">
          <label for="category_name" class="text-sm font-medium text-slate-700">
            Category <span class="text-brand-600">*</span>
          </label>
          <select
            id="category_name"
            v-model="form.category_name"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
          >
            <option value="GRAMS">Grams</option>
            <option value="PURITY">Purity</option>
            <option value="BOTH">Both</option>
          </select>
        </div>

        <BaseInput
          id="remarks"
          v-model="form.remarks"
          label="Remarks"
          placeholder="Optional"
          class="sm:col-span-2"
        />

        <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
          <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
          Active
        </label>

        <div class="flex items-center gap-3 sm:col-span-2">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : 'Create client' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="closeForm">Cancel</BaseButton>
        </div>
      </form>
    </BaseCard>

    <div class="mb-4 flex items-center gap-2">
      <div class="relative w-full max-w-xs">
        <Search
          class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
        />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search clients…"
          aria-label="Search clients"
          class="w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
        />
      </div>
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
      Loading clients…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredClients"
      empty-message="No clients yet. Add your first client to get started."
    >
      <template #is_active="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #user_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
            aria-label="Delete client"
            :disabled="deletingId === (row as Client).user_id"
            @click="handleDelete(row as Client)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>
