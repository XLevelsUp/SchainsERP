<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Phone, RefreshCw, Search, TriangleAlert } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { phoneBookApi } from '@/lib/phoneBookApi'
import { rolesApi } from '@/lib/rolesApi'
import { ApiError } from '@/lib/api'
import type { PhoneBookContact, PhoneBookSort, Role } from '@/types'
import type { DataTableColumn } from '@/types/table'

/*
|--------------------------------------------------------------------------
| Phone Book — GET /phone-book (PR #37, doc sections 48-52)
|--------------------------------------------------------------------------
| A directory over `user_details`: server-side search on name and phone,
| role and active filters, sorting and pagination. Read-only by design —
| lib/phoneBookApi.ts explains why the three write routes are not wrapped
| (they mint logins with a shared password and soft-delete shared rows).
| Contacts are created and edited on the Users screen.
|
| This is a fourth view over the same table (Users, Clients and the stock
| user picker are the others), so it earns its place by being the one that
| is actually a phone book: the number is the primary column and a tel:
| link, and search hits the server rather than filtering a page.
|
| Search is debounced rather than tied to a button. Every keystroke would
| otherwise be a request, and the endpoint pages server-side so there is no
| local list to filter instead.
|
| The Role column is present but reads "—" for every row today: the
| resource asks for `role_name` and the roles table column is `role`
| (PENDING_WORK.md #26). Kept in place rather than removed so it starts
| working the moment the backend fixes the field.
|--------------------------------------------------------------------------
*/

const PER_PAGE = 50
const SEARCH_DEBOUNCE_MS = 350

const sortOptions: { value: PhoneBookSort; label: string }[] = [
  { value: 'name', label: 'Name (A-Z)' },
  { value: '-name', label: 'Name (Z-A)' },
  { value: 'phone_no', label: 'Phone (ascending)' },
  { value: '-phone_no', label: 'Phone (descending)' },
  { value: '-is_active', label: 'Active first' },
  { value: 'is_active', label: 'Inactive first' },
]

const statusOptions: { value: 'active' | 'inactive'; label: string }[] = [
  { value: 'active', label: 'Active only' },
  { value: 'inactive', label: 'Inactive only' },
]

const filters = reactive({
  search: '',
  role_id: null as number | null,
  status: null as 'active' | 'inactive' | null,
  sort: 'name' as PhoneBookSort,
})

const roles = ref<Role[]>([])
const roleOptions = computed(() => roles.value.map((r) => ({ value: r.id, label: r.role })))

const contacts = ref<PhoneBookContact[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(false)
const loadError = ref('')

const columns: DataTableColumn<PhoneBookContact>[] = [
  { key: 'name', label: 'Name' },
  { key: 'phone_no', label: 'Phone' },
  { key: 'role', label: 'Role' },
  { key: 'is_active', label: 'Status' },
]

async function load(targetPage = page.value) {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    const result = await phoneBookApi.list({
      page: targetPage,
      per_page: PER_PAGE,
      search: filters.search.trim() || undefined,
      role_id: filters.role_id ?? undefined,
      // Only sent when a status is chosen: the controller keys off
      // `has('is_active')`, so sending it at all narrows the list.
      is_active: filters.status === null ? undefined : filters.status === 'active',
      sort: filters.sort,
    })
    contacts.value = result.data
    page.value = result.current_page
    lastPage.value = result.last_page
    total.value = result.total
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the phone book.'
  } finally {
    isLoading.value = false
  }
}

async function loadRoles() {
  try {
    roles.value = await rolesApi.list()
  } catch {
    // Non-fatal — the role filter just has no options.
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => filters.search,
  () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => load(1), SEARCH_DEBOUNCE_MS)
  },
)

// The other three apply immediately — they are single clicks, not typing.
watch([() => filters.role_id, () => filters.status, () => filters.sort], () => load(1))

function prevPage() {
  if (page.value > 1) load(page.value - 1)
}
function nextPage() {
  if (page.value < lastPage.value) load(page.value + 1)
}

function clearFilters() {
  filters.search = ''
  filters.role_id = null
  filters.status = null
  filters.sort = 'name'
  load(1)
}

onMounted(() => {
  loadRoles()
  load(1)
})
</script>

<template>
  <div>
    <PageHeader title="Phone Book" description="Contact directory for heads, staff and customers.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" :disabled="isLoading" @click="load()">
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <div
      class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
      <p>
        <strong>Read-only (PENDING_WORK.md #26-27).</strong> Add and edit contacts on the
        <RouterLink to="/users" class="font-semibold underline">Users</RouterLink> screen. The
        phone book's own create route issues a working ERP login with a shared hardcoded password,
        and its delete route deactivates the shared user record, so neither is wired up here. The
        Role column stays empty until the backend reads the right column.
      </p>
    </div>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput
          v-model="filters.search"
          label="Search"
          placeholder="Name or phone number…"
          size="sm"
          :icon="Search"
          clearable
        />
        <BaseSelect
          v-model="filters.role_id"
          label="Role"
          size="sm"
          placeholder="All roles…"
          :options="roleOptions"
        />
        <BaseSelect
          v-model="filters.status"
          label="Status"
          size="sm"
          placeholder="Active and inactive…"
          :options="statusOptions"
        />
        <BaseSelect v-model="filters.sort" label="Sort by" size="sm" :options="sortOptions" />
      </div>

      <div class="mt-4 flex justify-end">
        <BaseButton variant="secondary" type="button" :disabled="isLoading" @click="clearFilters">
          Clear
        </BaseButton>
      </div>
    </BaseCard>

    <p
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </p>

    <DataTable
      :columns="columns"
      :rows="contacts"
      :empty-message="isLoading ? 'Loading…' : 'No contacts match these filters.'"
    >
      <template #name="{ row }">
        <span class="font-medium text-slate-900">{{ row.name || '—' }}</span>
      </template>
      <template #phone_no="{ row }">
        <a
          v-if="row.phone_no"
          :href="`tel:${row.phone_no}`"
          class="inline-flex items-center gap-1.5 tabular-nums text-slate-700 hover:text-brand-700 hover:underline"
        >
          <Phone class="h-3.5 w-3.5 text-slate-400" aria-hidden="true" />
          {{ row.phone_no }}
        </a>
        <span v-else class="text-slate-400">—</span>
      </template>
      <template #role="{ row }">
        <span class="text-slate-500">{{ row.role?.role_name || '—' }}</span>
      </template>
      <template #is_active="{ row }">
        <span
          class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold"
          :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
        >
          {{ row.is_active ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </DataTable>

    <div v-if="total > 0" class="mt-3 flex items-center justify-between text-xs text-slate-500">
      <span>{{ total }} contact{{ total === 1 ? '' : 's' }} total</span>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="page === 1 || isLoading"
          aria-label="Previous page"
          @click="prevPage"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>
        <span>Page {{ page }} of {{ lastPage }}</span>
        <button
          type="button"
          class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="page === lastPage || isLoading"
          aria-label="Next page"
          @click="nextPage"
        >
          <ChevronRight class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>
