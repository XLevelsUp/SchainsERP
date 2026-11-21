<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Check, ChevronLeft, ChevronRight, Eye, Pencil, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ApiError } from '@/lib/api'
import { createUserDetailsApi } from '@/lib/userDetailsApi'
import { useToastStore } from '@/stores/toast'
import type {
  CustomerTouch,
  DataTableColumn,
  Item,
  Role,
  UserDetailListItem,
  UserFormProfileCatalog,
} from '@/types'
import SpecialPasswordDialog from '../components/SpecialPasswordDialog.vue'
import UserFormCard from '../components/users/UserFormCard.vue'
import UserViewDialog from '../components/users/UserViewDialog.vue'
import { adminApiWithSpecialPassword } from '../lib/adminApi'
import { customerTouchApi, itemsApi, rolesApi, userDetailsApi } from '../lib/resources'
import { userFormProfilesApi } from '../lib/userFormProfiles'

/*
|--------------------------------------------------------------------------
| Admin console — Users (2026-11-21, after the legacy User Details screen)
|--------------------------------------------------------------------------
| The list: user name, phone, role, salary person, per-day salary, status,
| with a filter for each and 20 a page. Status toggles in place (with a
| confirm). View / edit / delete per row; delete needs the special password.
|
| The form (UserFormCard) is driven by the user's role: each role has its
| own fields and permissions (GET /admin/user-form-profiles).
|--------------------------------------------------------------------------
*/

const PAGE_SIZE = 20

const toast = useToastStore()

const users = ref<UserDetailListItem[]>([])
const roles = ref<Role[]>([])
const items = ref<Item[]>([])
const heads = ref<UserDetailListItem[]>([])
const touches = ref<CustomerTouch[]>([])
const catalog = ref<UserFormProfileCatalog | null>(null)
const isLoading = ref(false)
const loadError = ref('')
const profilesError = ref('')

async function loadUsers() {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    users.value = await userDetailsApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load users.'
  } finally {
    isLoading.value = false
  }
}

// Lookups load separately: one failing leaves its picker empty instead of
// taking the list down with it.
async function loadRoles() {
  roles.value = await rolesApi.list().catch(() => [])
}
async function loadItems() {
  items.value = await itemsApi.list().catch(() => [])
}
async function loadHeads() {
  heads.value = await userDetailsApi.list('HEAD').catch(() => [])
}
async function loadTouches() {
  touches.value = await customerTouchApi.list().catch(() => [])
}
async function loadProfiles() {
  profilesError.value = ''
  try {
    catalog.value = await userFormProfilesApi.get()
  } catch (err) {
    catalog.value = null
    profilesError.value =
      err instanceof ApiError ? err.message : 'Could not load the role profiles.'
  }
}

function refresh() {
  void loadUsers()
  void loadRoles()
  void loadHeads()
  void loadProfiles()
}

onMounted(() => {
  refresh()
  void loadItems()
  void loadTouches()
})

// ---- filters & paging ------------------------------------------------------

type YesNoAll = 'all' | 'yes' | 'no'
type StatusFilter = 'active' | 'inactive' | 'all'

const filters = ref({
  userName: '',
  phone: '',
  roleId: '' as string,
  salary: 'all' as YesNoAll,
  status: 'active' as StatusFilter,
})

const roleFilterOptions = computed(() => [
  { value: '', label: 'All roles' },
  ...roles.value.map((r) => ({ value: String(r.id), label: r.role })),
])
const salaryOptions: { value: YesNoAll; label: string }[] = [
  { value: 'all', label: 'Salary: all' },
  { value: 'yes', label: 'Salary person' },
  { value: 'no', label: 'Not salary' },
]
const statusOptions: { value: StatusFilter; label: string }[] = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'all', label: 'All statuses' },
]

const filtered = computed(() => {
  const name = filters.value.userName.trim().toLowerCase()
  const phone = filters.value.phone.trim()
  return users.value.filter((u) => {
    if (name && !`${u.user_name ?? ''} ${u.name}`.toLowerCase().includes(name)) return false
    if (phone && !(u.phone_number ?? '').includes(phone)) return false
    if (filters.value.roleId && String(u.role_id ?? '') !== filters.value.roleId) return false
    if (filters.value.salary !== 'all' && Boolean(u.is_salary_person) !== (filters.value.salary === 'yes')) return false
    if (filters.value.status !== 'all' && u.is_active !== (filters.value.status === 'active')) return false
    return true
  })
})

const page = ref(1)
const showAll = ref(false)
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)))
const pageRows = computed(() =>
  showAll.value ? filtered.value : filtered.value.slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE),
)
const rangeFrom = computed(() => (filtered.value.length ? (showAll.value ? 1 : (page.value - 1) * PAGE_SIZE + 1) : 0))
const rangeTo = computed(() => (showAll.value ? filtered.value.length : Math.min(page.value * PAGE_SIZE, filtered.value.length)))

watch(filters, () => (page.value = 1), { deep: true })
watch(lastPage, (last) => {
  if (page.value > last) page.value = last
})

const isFiltered = computed(
  () =>
    !!filters.value.userName.trim() ||
    !!filters.value.phone.trim() ||
    !!filters.value.roleId ||
    filters.value.salary !== 'all' ||
    filters.value.status !== 'all',
)

const columns: DataTableColumn<UserDetailListItem>[] = [
  { key: 'user_name', label: 'User name' },
  { key: 'phone_number', label: 'Phone no' },
  { key: 'type', label: 'Role' },
  { key: 'is_salary_person', label: 'Salary person' },
  { key: 'per_day_salary', label: 'Per day salary (₹)', align: 'right' },
  { key: 'is_active', label: 'Status' },
  { key: 'id', label: '' },
]

function salary(value: UserDetailListItem['per_day_salary']): string {
  if (value === null || value === undefined || value === '') return '—'
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ---- status ----------------------------------------------------------------

const togglingId = ref<number | null>(null)

async function toggleStatus(user: UserDetailListItem) {
  if (togglingId.value !== null) return
  togglingId.value = user.id
  try {
    const result = await userDetailsApi.setStatus(user.id, !user.is_active)
    user.is_active = result.is_active
    toast.show(`${user.name} ${result.is_active ? 'activated' : 'deactivated'}.`, 'success')
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to change the status.', 'error')
  } finally {
    togglingId.value = null
  }
}

// ---- view / form -----------------------------------------------------------

const viewingId = ref<number | null>(null)
// null = list; { id: null } = new user; { id } = editing.
const formTarget = ref<{ id: number | null } | null>(null)

function openCreate() {
  viewingId.value = null
  formTarget.value = { id: null }
}

function openEdit(id: number) {
  viewingId.value = null
  formTarget.value = { id }
}

function closeForm() {
  formTarget.value = null
}

async function handleSaved(message: string) {
  formTarget.value = null
  toast.show(message, 'success')
  await Promise.all([loadUsers(), loadHeads()])
}

// ---- delete (special password) ---------------------------------------------

const deleteTarget = ref<UserDetailListItem | null>(null)
const deleteError = ref('')
const isDeleting = ref(false)

function askDelete(user: UserDetailListItem) {
  deleteTarget.value = user
  deleteError.value = ''
}

async function handleDelete(specialPassword: string) {
  const user = deleteTarget.value
  if (!user || isDeleting.value) return
  isDeleting.value = true
  deleteError.value = ''
  try {
    await createUserDetailsApi(adminApiWithSpecialPassword(specialPassword)).remove(user.id)
    deleteTarget.value = null
    toast.show(`${user.name} deleted.`, 'success')
    await Promise.all([loadUsers(), loadHeads()])
  } catch (err) {
    if (err instanceof ApiError && (err.errors?.special_password || err.status === 429)) {
      // Wrong special password, or locked out: keep the dialog open.
      deleteError.value = err.errors?.special_password?.[0] ?? err.message
    } else {
      deleteTarget.value = null
      loadError.value = err instanceof ApiError ? err.message : 'Failed to delete the user.'
    }
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div>
    <UserFormCard
      v-if="formTarget"
      :key="formTarget.id ?? 'new'"
      :editing-id="formTarget.id"
      :roles="roles"
      :items="items"
      :heads="heads"
      :users="users"
      :touches="touches"
      :catalog="catalog"
      :reload-items="loadItems"
      :reload-heads="loadHeads"
      :reload-users="loadUsers"
      :reload-touches="loadTouches"
      @saved="handleSaved"
      @close="closeForm"
    />

    <template v-else>
      <PageHeader
        title="Users"
        description="Create users, give them a role, and set what each can do. The fields and permissions follow the role."
      >
        <template #actions>
          <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="refresh">
            Refresh
          </BaseButton>
          <BaseButton :icon="Plus" @click="openCreate">New user</BaseButton>
        </template>
      </PageHeader>

      <div
        v-if="profilesError"
        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        {{ profilesError }} The form will show every field; saving still follows each role's
        profile.
      </div>

      <div class="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <BaseInput v-model="filters.userName" type="search" size="sm" placeholder="User name…" aria-label="Filter by user name" />
        <BaseInput v-model="filters.phone" type="search" size="sm" placeholder="Phone no…" aria-label="Filter by phone" />
        <BaseSelect v-model="filters.roleId" size="sm" aria-label="Filter by role" :options="roleFilterOptions" />
        <BaseSelect v-model="filters.salary" size="sm" aria-label="Filter by salary person" :options="salaryOptions" />
        <BaseSelect v-model="filters.status" size="sm" aria-label="Filter by status" :options="statusOptions" />
      </div>

      <div
        v-if="loadError"
        class="mb-4 flex items-center justify-between gap-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        <span>{{ loadError }}</span>
        <button type="button" class="font-medium underline" @click="loadUsers">Try again</button>
      </div>

      <div
        v-if="isLoading && !users.length"
        class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
      >
        Loading users…
      </div>

      <template v-else>
        <DataTable
          :columns="columns"
          :rows="pageRows"
          size="sm"
          :empty-message="isFiltered ? 'No users match these filters.' : 'No users yet. Create the first one.'"
        >
          <template #user_name="{ row }">
            <div class="font-medium text-slate-900">{{ row.user_name ?? row.name }}</div>
            <div v-if="row.user_name && row.user_name !== row.name" class="text-xs text-slate-500">{{ row.name }}</div>
          </template>

          <template #phone_number="{ value }">
            <span class="tabular-nums">{{ value }}</span>
          </template>

          <template #type="{ value }">
            <span class="text-xs font-semibold tracking-wide text-slate-700 uppercase">{{ value ?? '—' }}</span>
          </template>

          <template #is_salary_person="{ row }">
            <Check v-if="row.is_salary_person" class="h-4 w-4 text-emerald-600" aria-label="Yes" />
            <X v-else class="h-4 w-4 text-slate-300" aria-label="No" />
          </template>

          <template #per_day_salary="{ row }">
            <span class="tabular-nums">{{ row.is_salary_person ? salary(row.per_day_salary) : '—' }}</span>
          </template>

          <template #is_active="{ row }">
            <ConfirmPopover
              :message="row.is_active ? `Deactivate ${row.name}? They won't be able to sign in.` : `Activate ${row.name}?`"
              :confirm-label="row.is_active ? 'Deactivate' : 'Activate'"
              @confirm="toggleStatus(row)"
            >
              <template #default="{ toggle }">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium disabled:opacity-50"
                  :class="row.is_active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                  :disabled="togglingId === row.id"
                  :title="row.is_active ? 'Click to deactivate' : 'Click to activate'"
                  @click="toggle"
                >
                  <Check v-if="row.is_active" class="h-3.5 w-3.5" aria-hidden="true" />
                  <X v-else class="h-3.5 w-3.5" aria-hidden="true" />
                  {{ row.is_active ? 'Active' : 'Inactive' }}
                </button>
              </template>
            </ConfirmPopover>
          </template>

          <template #id="{ row }">
            <div class="flex items-center justify-end gap-1">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                :aria-label="`View ${row.name}`"
                @click="viewingId = row.id"
              >
                <Eye class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                :aria-label="`Edit ${row.name}`"
                @click="openEdit(row.id)"
              >
                <Pencil class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                :aria-label="`Delete ${row.name}`"
                :disabled="isDeleting && deleteTarget?.id === row.id"
                @click="askDelete(row)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </template>
        </DataTable>

        <div v-if="filtered.length" class="mt-3 flex items-center justify-between text-xs text-slate-500">
          <span class="tabular-nums">Showing {{ rangeFrom }}–{{ rangeTo }} of {{ filtered.length }} users</span>
          <div class="flex items-center gap-2">
            <button type="button" class="font-medium text-brand-600 hover:text-brand-700" @click="showAll = !showAll">
              {{ showAll ? 'Show 20 a page' : 'Show all' }}
            </button>
            <template v-if="!showAll && lastPage > 1">
              <button
                type="button"
                class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="page === 1"
                aria-label="Previous page"
                @click="page--"
              >
                <ChevronLeft class="h-4 w-4" />
              </button>
              <span class="tabular-nums">Page {{ page }} of {{ lastPage }}</span>
              <button
                type="button"
                class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="page === lastPage"
                aria-label="Next page"
                @click="page++"
              >
                <ChevronRight class="h-4 w-4" />
              </button>
            </template>
          </div>
        </div>
      </template>
    </template>

    <UserViewDialog
      v-if="viewingId !== null"
      :user-id="viewingId"
      :roles="roles"
      :items="items"
      :heads="heads"
      :users="users"
      :touches="touches"
      :catalog="catalog"
      @close="viewingId = null"
      @edit="openEdit(viewingId)"
    />

    <SpecialPasswordDialog
      v-if="deleteTarget"
      title="Delete user"
      :message="`Delete ${deleteTarget.name}? This permanently removes the user and their head mappings. Enter your special password to confirm.`"
      confirm-label="Delete user"
      destructive
      :submitting="isDeleting"
      :error="deleteError"
      @confirm="handleDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
