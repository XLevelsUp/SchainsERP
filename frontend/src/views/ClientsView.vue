<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import { clientsApi } from '@/lib/clientsApi'
import { rolesApi } from '@/lib/rolesApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { Client, ClientFormValues, DataTableColumn, Role } from '@/types'

const clients = ref<Client[]>([])
const roles = ref<Role[]>([])
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
    const [clientsData, rolesData] = await Promise.all([clientsApi.list(), rolesApi.list()])
    clients.value = clientsData
    roles.value = rolesData
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

      <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="handleSubmit">
        <BaseInput id="name" v-model="form.name" label="Name" required size="sm" />
        <BaseInput id="user_name" v-model="form.user_name" label="Username" required size="sm" />
        <BaseInput
          id="password"
          v-model="form.password"
          label="Password"
          type="password"
          required
          size="sm"
        />
        <BaseInput id="phone_no" v-model="form.phone_no" label="Phone" required size="sm" />
        <BaseInput id="address" v-model="form.address" label="Address" required size="sm" />
        <BaseInput
          id="mailing_name"
          v-model="form.mailing_name"
          label="Mailing name"
          required
          size="sm"
        />
        <BaseInput id="signature" v-model="form.signature" label="Signature" required size="sm" />
        <BaseInput id="code" v-model="form.code" label="Code" required size="sm" />
        <BaseInput id="proff" v-model="form.proff" label="Profession" required size="sm" />
        <BaseSelect id="role_id" v-model="form.role_id" label="Role" required size="sm">
          <option value="" disabled>Select a role…</option>
          <option v-for="role in roles" :key="role.id" :value="String(role.id)">
            {{ role.role }}
          </option>
        </BaseSelect>
        <BaseInput id="system_id" v-model="form.system_id" label="System ID" required size="sm" />

        <BaseSelect
          id="category_name"
          v-model="form.category_name"
          label="Category"
          required
          size="sm"
          :options="[
            { value: 'GRAMS', label: 'Grams' },
            { value: 'PURITY', label: 'Purity' },
            { value: 'BOTH', label: 'Both' },
          ]"
        />

        <BaseInput
          id="remarks"
          v-model="form.remarks"
          label="Remarks"
          placeholder="Optional"
          size="sm"
          class="sm:col-span-3"
        />

        <BaseCheckbox v-model="form.is_active" label="Active" class="sm:col-span-3" />

        <div class="flex items-center gap-3 sm:col-span-3">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : 'Create client' }}
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
        placeholder="Search clients…"
        aria-label="Search clients"
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

      <template #added_at="{ value }">{{ formatDateTime(value as string) }}</template>

      <template #user_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <ConfirmPopover
            :message="`Delete ${(row as Client).name}?`"
            @confirm="handleDelete(row as Client)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete client"
                :disabled="deletingId === (row as Client).user_id"
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
