<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw, Trash } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { rolesApi } from '@/lib/rolesApi'
import { ApiError } from '@/lib/api'
import type {
  DataTableColumn,
  UserDetail,
  UserDetailFormValues,
  CategoryName,
  Item,
  Role,
} from '@/types'

const users = ref<UserDetail[]>([])
const items = ref<Item[]>([])
const roles = ref<Role[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<UserDetail>[] = [
  { key: 'name', label: 'Name' },
  { key: 'user_name', label: 'Username' },
  { key: 'phone_no', label: 'Phone' },
  { key: 'category_name', label: 'Category' },
  { key: 'is_active', label: 'Status' },
  { key: 'user_id', label: '' },
]

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [usersData, itemsData, rolesData] = await Promise.all([
      userDetailsApi.list(),
      itemsApi.list(),
      rolesApi.list(),
    ])
    users.value = usersData
    items.value = itemsData
    roles.value = rolesData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load users.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return users.value
  return users.value.filter(
    (u) =>
      u.name.toLowerCase().includes(query) || u.user_name.toLowerCase().includes(query),
  )
})

const categories: CategoryName[] = ['GRAMS', 'PURITY', 'BOTH']

function makeEmptyForm(): UserDetailFormValues {
  return {
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
    system_id: '',
    mailing_name: '',
    customer_commants: '',
    category_name: 'GRAMS',
    is_active: true,
    is_delete: false,
    is_billable: false,
    item_mappings: [],
    head_mappings: [],
    cash_head_mappings: [],
  }
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<UserDetailFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)

function resetForm(values: UserDetailFormValues) {
  Object.assign(form, values)
}

function openCreateForm() {
  editingId.value = null
  resetForm(makeEmptyForm())
  formError.value = ''
  isFormOpen.value = true
}

function openEditForm(user: UserDetail) {
  editingId.value = user.user_id
  resetForm({
    ...makeEmptyForm(),
    name: user.name,
    user_name: user.user_name,
    address: user.address,
    signature: user.signature,
    code: user.code,
    phone_no: user.phone_no,
    remarks: user.remarks ?? '',
    proff: user.proff,
    role_id: user.role_id,
    system_id: user.system_id,
    mailing_name: user.mailing_name,
    customer_commants: user.customer_commants ?? '',
    category_name: user.category_name,
    is_active: user.is_active,
    is_delete: user.is_delete,
    is_billable: user.is_billable,
    // Note: backend show() does not return mappings, so they start empty on edit.
  })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

// ----- mapping row helpers -----
function addItemMapping() {
  form.item_mappings.push({
    item_id: null,
    item_grams_total: '0',
    item_purity_total: '0',
    is_primary: 0,
  })
}
function removeItemMapping(index: number) {
  form.item_mappings.splice(index, 1)
}
function addHeadMapping() {
  form.head_mappings.push({ head_id: null })
}
function removeHeadMapping(index: number) {
  form.head_mappings.splice(index, 1)
}
function addCashHeadMapping() {
  form.cash_head_mappings.push({ head_id: null })
}
function removeCashHeadMapping(index: number) {
  form.cash_head_mappings.splice(index, 1)
}

const requiredFields: { key: keyof UserDetailFormValues; label: string }[] = [
  { key: 'name', label: 'Name' },
  { key: 'user_name', label: 'Username' },
  { key: 'address', label: 'Address' },
  { key: 'signature', label: 'Signature' },
  { key: 'code', label: 'Code' },
  { key: 'phone_no', label: 'Phone number' },
  { key: 'proff', label: 'Proof' },
  { key: 'role_id', label: 'Role' },
  { key: 'system_id', label: 'System ID' },
  { key: 'mailing_name', label: 'Mailing name' },
]

function validate(): boolean {
  for (const f of requiredFields) {
    if (!String(form[f.key] ?? '').trim()) {
      formError.value = `${f.label} is required.`
      return false
    }
  }
  if (editingId.value === null && form.password.trim().length < 6) {
    formError.value = 'Password is required (min 6 characters).'
    return false
  }
  return true
}

async function handleSubmit() {
  formError.value = ''
  if (!validate()) return

  isSaving.value = true
  try {
    if (editingId.value !== null) {
      await userDetailsApi.update(editingId.value, { ...form })
    } else {
      await userDetailsApi.create({ ...form })
    }
    isFormOpen.value = false
    await loadData()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save user.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(user: UserDetail) {
  if (!window.confirm(`Delete "${user.name}"? This also removes their mappings.`)) return
  deletingId.value = user.user_id
  try {
    await userDetailsApi.remove(user.user_id)
    await loadData()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete user.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Users" description="Manage users and their item / head assignments.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New user</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit user' : 'New user' }}
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

      <p
        v-if="formError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ formError }}
      </p>

      <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <!-- Core details -->
        <section class="grid gap-3 sm:grid-cols-3">
          <BaseInput id="name" v-model="form.name" label="Name" required size="sm" />
          <BaseInput id="user_name" v-model="form.user_name" label="Username" required size="sm" />
          <BaseInput
            id="password"
            v-model="form.password"
            :label="editingId !== null ? 'Password (leave blank to keep)' : 'Password'"
            type="password"
            :required="editingId === null"
            size="sm"
          />
          <BaseInput id="phone_no" v-model="form.phone_no" label="Phone number" required size="sm" />
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
          <BaseInput id="proff" v-model="form.proff" label="Proof" required size="sm" />
          <BaseInput id="system_id" v-model="form.system_id" label="System ID" required size="sm" />

          <BaseSelect id="role_id" v-model="form.role_id" label="Role" required size="sm">
            <option value="" disabled>Select a role…</option>
            <option v-for="role in roles" :key="role.id" :value="String(role.id)">
              {{ role.role }}
            </option>
          </BaseSelect>

          <BaseSelect
            id="category_name"
            v-model="form.category_name"
            label="Category"
            required
            size="sm"
            :options="categories.map((cat) => ({ value: cat, label: cat }))"
          />

          <BaseInput id="remarks" v-model="form.remarks" label="Remarks (optional)" size="sm" />
          <BaseInput
            id="customer_commants"
            v-model="form.customer_commants"
            label="Customer comments (optional)"
            size="sm"
          />
        </section>

        <section class="flex flex-wrap gap-6">
          <BaseCheckbox v-model="form.is_active" label="Active" />
          <BaseCheckbox v-model="form.is_billable" label="Billable" />
          <BaseCheckbox v-model="form.is_delete" label="Marked deleted" />
        </section>

        <!-- Item mappings -->
        <section class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Assigned items</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addItemMapping">
              Add item
            </BaseButton>
          </div>
          <p v-if="form.item_mappings.length === 0" class="text-sm text-slate-500">
            No items assigned.
          </p>
          <div
            v-for="(mapping, index) in form.item_mappings"
            :key="index"
            class="mb-3 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto_auto]"
          >
            <BaseSelect v-model="mapping.item_id" label="Item" size="sm">
              <option :value="null" disabled>Select…</option>
              <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                {{ item.item_name }}
              </option>
            </BaseSelect>
            <BaseInput
              v-model="mapping.item_grams_total"
              label="Grams total"
              type="number"
              size="sm"
            />
            <BaseInput
              v-model="mapping.item_purity_total"
              label="Purity total"
              type="number"
              size="sm"
            />
            <BaseCheckbox
              class="pb-2"
              label="Primary"
              :model-value="mapping.is_primary === 1"
              @update:model-value="(v) => (mapping.is_primary = v ? 1 : 0)"
            />
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove item"
              @click="removeItemMapping(index)"
            >
              <Trash class="h-4 w-4" />
            </button>
          </div>
        </section>

        <!-- Head mappings -->
        <section class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Reports to (heads)</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addHeadMapping">
              Add head
            </BaseButton>
          </div>
          <p v-if="form.head_mappings.length === 0" class="text-sm text-slate-500">
            No heads assigned.
          </p>
          <div
            v-for="(mapping, index) in form.head_mappings"
            :key="index"
            class="mb-3 flex items-end gap-3"
          >
            <BaseSelect v-model="mapping.head_id" label="Head (user)" size="sm" class="flex-1">
              <option :value="null" disabled>Select a user…</option>
              <option v-for="u in users" :key="u.user_id" :value="u.user_id">
                {{ u.name }} ({{ u.user_name }})
              </option>
            </BaseSelect>
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove head"
              @click="removeHeadMapping(index)"
            >
              <Trash class="h-4 w-4" />
            </button>
          </div>
        </section>

        <!-- Cash head mappings -->
        <section class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Cash heads</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addCashHeadMapping">
              Add cash head
            </BaseButton>
          </div>
          <p v-if="form.cash_head_mappings.length === 0" class="text-sm text-slate-500">
            No cash heads assigned.
          </p>
          <div
            v-for="(mapping, index) in form.cash_head_mappings"
            :key="index"
            class="mb-3 flex items-end gap-3"
          >
            <BaseSelect v-model="mapping.head_id" label="Cash head (user)" size="sm" class="flex-1">
              <option :value="null" disabled>Select a user…</option>
              <option v-for="u in users" :key="u.user_id" :value="u.user_id">
                {{ u.name }} ({{ u.user_name }})
              </option>
            </BaseSelect>
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove cash head"
              @click="removeCashHeadMapping(index)"
            >
              <Trash class="h-4 w-4" />
            </button>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create user' }}
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
        placeholder="Search users…"
        aria-label="Search users"
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
      Loading users…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredUsers"
      empty-message="No users yet. Add your first user to get started."
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
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit user"
            @click="openEditForm(row as UserDetail)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
            aria-label="Delete user"
            :disabled="deletingId === (row as UserDetail).user_id"
            @click="handleDelete(row as UserDetail)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>