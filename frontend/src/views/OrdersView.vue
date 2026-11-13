<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RefreshCw, Plus, Pencil, Trash2, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ordersApi } from '@/lib/ordersApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import { ORDER_STATUSES } from '@/types'
import type { DataTableColumn, Item, Order, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Orders — apiResource('orders')
|--------------------------------------------------------------------------
| CRUD over `order_details`.
|
| Since 2026-11-12 GET /orders is paginated and filtered server-side
| (status, customer, added date range), newest first. Heads and employees
| get every order; customers and retailers only their own, and the backend
| answers 403 if they name another customer. ORDER_STATUSES is read off the
| backend's validation enum. grams has no server-side bound, so this
| screen's own checks stay in validate().
|
| Rows carry ids only, so customer and item names are resolved from the
| lookups this screen loads itself.
|--------------------------------------------------------------------------
*/

const PER_PAGE = 50

const toast = useToastStore()

const orders = ref<Order[]>([])
const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])

const isLoading = ref(false)
const loadError = ref('')

const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const rangeFrom = ref<number | null>(null)
const rangeTo = ref<number | null>(null)

const filters = reactive({
  status: null as string | null,
  customer_id: null as number | null,
  from_date: '',
  to_date: '',
})

const hasFilters = computed(
  () =>
    filters.status !== null ||
    filters.customer_id !== null ||
    filters.from_date !== '' ||
    filters.to_date !== '',
)

// YYYY-MM-DD strings compare correctly as text. The backend 422s on this too.
const dateRangeError = computed(() =>
  filters.from_date && filters.to_date && filters.from_date > filters.to_date
    ? '“From” date must be on or before the “To” date.'
    : '',
)

const statusOptions = ORDER_STATUSES.map((s) => ({ value: s as string, label: s.replace('_', ' ') }))
const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() =>
  items.value.map((i) => ({ value: i.item_id, label: i.item_name })),
)

const userNameById = computed(() => {
  const map = new Map<number, string>()
  for (const u of users.value) map.set(u.id, u.full_name || u.name)
  return map
})
const itemNameById = computed(() => {
  const map = new Map<number, string>()
  for (const i of items.value) map.set(i.item_id, i.item_name)
  return map
})

function customerLabel(row: Order): string {
  if (row.customer_id === null) return '—'
  return userNameById.value.get(row.customer_id) ?? `#${row.customer_id}`
}
function itemLabel(row: Order): string {
  if (row.item_id === null) return '—'
  return itemNameById.value.get(row.item_id) ?? `#${row.item_id}`
}

// Actions are keyed on the id field with a blank label, and there is no
// standalone ID column — same shape as UsersView/ItemsView. The order number
// still surfaces where it matters: the edit heading, the delete confirmation
// and the toasts.
const columns: DataTableColumn<Order>[] = [
  { key: 'customer_id', label: 'Customer' },
  { key: 'item_id', label: 'Item' },
  { key: 'grams', label: 'Grams' },
  { key: 'status', label: 'Status' },
  { key: 'added_at', label: 'Added' },
  { key: 'order_id', label: '' },
]

// grams is decimal(10,3) and arrives as a string from Eloquent.
function formatGrams(value: number | string): string {
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString(undefined, { minimumFractionDigits: 3 }) : String(value)
}

function statusClass(status: string): string {
  switch (status) {
    case 'COMPLETED':
      return 'bg-emerald-50 text-emerald-700'
    case 'CANCELLED':
      return 'bg-red-50 text-red-700'
    case 'PROCESSING':
      return 'bg-amber-50 text-amber-700'
    case 'PENDING':
      return 'bg-slate-100 text-slate-600'
    default:
      // An unrecognised value is worth showing as unusual rather than
      // styling like a known one — the column accepts any string.
      return 'bg-violet-50 text-violet-700'
  }
}

// Ticket guards against an older, slower response overwriting a newer one
// when filters change quickly.
let latestRequest = 0

async function loadOrders(targetPage = page.value) {
  if (dateRangeError.value) return
  const ticket = ++latestRequest
  isLoading.value = true
  loadError.value = ''
  try {
    const { rows, pagination } = await ordersApi.list({
      page: targetPage,
      per_page: PER_PAGE,
      status: filters.status ?? undefined,
      customer_id: filters.customer_id ?? undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
    })
    if (ticket !== latestRequest) return
    // Deleting the last row on the last page leaves that page empty.
    if (rows.length === 0 && pagination.current_page > 1) {
      await loadOrders(pagination.last_page)
      return
    }
    orders.value = rows
    page.value = pagination.current_page
    lastPage.value = pagination.last_page
    total.value = pagination.total
    rangeFrom.value = pagination.from
    rangeTo.value = pagination.to
  } catch (err) {
    if (ticket !== latestRequest) return
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load orders.'
  } finally {
    if (ticket === latestRequest) isLoading.value = false
  }
}

function prevPage() {
  if (page.value > 1) loadOrders(page.value - 1)
}
function nextPage() {
  if (page.value < lastPage.value) loadOrders(page.value + 1)
}

watch(
  () => [filters.status, filters.customer_id, filters.from_date, filters.to_date],
  () => loadOrders(1),
)

async function loadLookups() {
  try {
    const [usersData, itemsData] = await Promise.all([
      userDetailsApi.list(undefined, 'stock'),
      itemsApi.list(),
    ])
    users.value = usersData
    items.value = itemsData
  } catch {
    // Non-fatal — the table falls back to ids for any name it can't resolve.
  }
}

// Dropdown reload buttons: each re-fetches one lookup with the same call
// loadLookups() makes; the orders table is not reloaded.
async function reloadUsers() {
  users.value = await userDetailsApi.list(undefined, 'stock')
}
async function reloadItems() {
  items.value = await itemsApi.list()
}

// ---------------------------------------------------------------------------
// Create / edit
// ---------------------------------------------------------------------------

const formMode = ref<'create' | 'edit' | null>(null)
const editingId = ref<number | null>(null)
const form = reactive({
  customer_id: null as number | null,
  item_id: null as number | null,
  grams: null as number | null,
  status: 'PENDING' as string,
})
const formError = ref('')
const isSaving = ref(false)

function openCreate() {
  formMode.value = 'create'
  editingId.value = null
  form.customer_id = filters.customer_id
  form.item_id = null
  form.grams = null
  form.status = 'PENDING'
  formError.value = ''
}

function openEdit(row: Order) {
  formMode.value = 'edit'
  editingId.value = row.order_id
  form.customer_id = row.customer_id
  form.item_id = row.item_id
  form.grams = Number(row.grams)
  form.status = row.status
  formError.value = ''
}

function closeForm() {
  formMode.value = null
  editingId.value = null
  formError.value = ''
}

// The backend validates customer_id/item_id/status for real as of PR #48
// (existence checks + a real enum) — these checks now mostly duplicate that
// rather than being the only line of defence. Kept anyway: grams' column
// bound below has no server-side equivalent, and validating client-side
// still beats a round trip for the common mistakes.
function validate(): string | null {
  if (form.customer_id === null) return 'Select a customer.'
  if (form.item_id === null) return 'Select an item.'
  if (form.grams === null || !Number.isFinite(form.grams)) return 'Enter the grams.'
  if (form.grams <= 0) return 'Grams must be greater than 0.'
  // decimal(10,3) — 7 digits before the point, 3 after.
  if (form.grams >= 10_000_000) return 'Grams is too large for the column (max 7 digits).'
  if (!form.status.trim()) return 'Select a status.'
  return null
}

async function saveForm() {
  if (isSaving.value) return

  const invalid = validate()
  if (invalid) {
    formError.value = invalid
    return
  }

  isSaving.value = true
  formError.value = ''
  const payload = {
    customer_id: form.customer_id!,
    item_id: form.item_id!,
    grams: form.grams!,
    status: form.status,
  }
  try {
    // Reload rather than patch the list: the server decides order, paging and
    // whether the row still matches the filters.
    if (formMode.value === 'create') {
      const created = await ordersApi.create(payload)
      toast.show(`Order #${created.order_id} created.`, 'success')
      closeForm()
      await loadOrders(1)
    } else if (editingId.value !== null) {
      const updated = await ordersApi.update(editingId.value, payload)
      toast.show(`Order #${updated.order_id} saved.`, 'success')
      closeForm()
      await loadOrders()
    }
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message
    } else {
      formError.value = 'Failed to save the order.'
    }
  } finally {
    isSaving.value = false
  }
}

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

const deletingId = ref<number | null>(null)

async function handleDelete(row: Order) {
  if (deletingId.value !== null) return
  deletingId.value = row.order_id
  try {
    await ordersApi.remove(row.order_id)
    if (editingId.value === row.order_id) closeForm()
    toast.show(`Order #${row.order_id} deleted.`, 'success')
    await loadOrders()
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to delete the order.', 'error')
  } finally {
    deletingId.value = null
  }
}

function clearFilters() {
  filters.status = null
  filters.customer_id = null
  filters.from_date = ''
  filters.to_date = ''
}

onMounted(async () => {
  await loadLookups()
  await loadOrders(1)
})
</script>

<template>
  <div>
    <PageHeader title="Orders" description="Customer orders and their fulfilment status.">
      <template #actions>
        <BaseButton
          variant="secondary"
          :icon="RefreshCw"
          :disabled="isLoading"
          @click="loadOrders()"
        >
          Refresh
        </BaseButton>
        <BaseButton :icon="Plus" @click="openCreate">New order</BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <BaseSelect
          v-model="filters.status"
          label="Status"
          size="sm"
          placeholder="All statuses…"
          :options="statusOptions"
        />
        <BaseSelect
          v-model="filters.customer_id"
          label="Customer"
          size="sm"
          placeholder="All customers…"
          :options="userOptions"
          :reload="reloadUsers"
        />
        <BaseInput
          v-model="filters.from_date"
          label="Added from"
          type="date"
          size="sm"
          clearable
        />
        <BaseInput
          v-model="filters.to_date"
          label="Added to"
          type="date"
          size="sm"
          clearable
          :error="dateRangeError || undefined"
        />
        <div class="flex items-end">
          <BaseButton
            variant="secondary"
            type="button"
            :disabled="!hasFilters"
            @click="clearFilters"
          >
            Clear
          </BaseButton>
        </div>
      </div>
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
          {{ formMode === 'create' ? 'New order' : `Edit order #${editingId}` }}
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
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <BaseSelect
            v-model="form.customer_id"
            label="Customer"
            required
            placeholder="Select a customer…"
            :options="userOptions"
            :reload="reloadUsers"
          />
          <BaseSelect
            v-model="form.item_id"
            label="Item"
            required
            placeholder="Select an item…"
            :options="itemOptions"
            :reload="reloadItems"
          />
          <BaseInput
            :model-value="form.grams === null ? '' : String(form.grams)"
            label="Grams"
            type="number"
            step="0.001"
            min="0"
            required
            placeholder="0.000"
            @update:model-value="(v) => (form.grams = v === '' ? null : Number(v))"
          />
          <BaseSelect
            v-model="form.status"
            label="Status"
            required
            :options="statusOptions"
          />
        </div>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

        <p class="text-xs text-slate-500">
          The order date is set by the database when the row is created and cannot be changed here.
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
          <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="closeForm">
            Cancel
          </BaseButton>
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : formMode === 'create' ? 'Create order' : 'Save order' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <DataTable
      :columns="columns"
      :rows="orders"
      :empty-message="
        isLoading
          ? 'Loading…'
          : hasFilters
            ? 'No orders match these filters.'
            : 'No orders yet. Use “New order” to create the first one.'
      "
    >
      <template #customer_id="{ row }">
        <span class="font-medium text-slate-900">{{ customerLabel(row) }}</span>
      </template>
      <template #item_id="{ row }">
        {{ itemLabel(row) }}
      </template>
      <template #grams="{ row }">
        <span class="tabular-nums">{{ formatGrams(row.grams) }}</span>
      </template>
      <template #status="{ row }">
        <span
          class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
          :class="statusClass(row.status)"
        >
          {{ row.status }}
        </span>
      </template>
      <template #added_at="{ row }">
        <span class="whitespace-nowrap text-slate-500">{{ formatDateTime(row.added_at) }}</span>
      </template>
      <template #order_id="{ row }">
        <div class="flex justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit order"
            @click="openEdit(row)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete order #${row.order_id}? This cannot be undone.`"
            @confirm="handleDelete(row)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete order"
                :disabled="deletingId === row.order_id"
                @click="toggle"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </ConfirmPopover>
        </div>
      </template>
    </DataTable>

    <div v-if="total > 0" class="mt-3 flex items-center justify-between text-xs text-slate-500">
      <span class="tabular-nums">
        {{ rangeFrom ?? 0 }}–{{ rangeTo ?? 0 }} of {{ total }} order{{ total === 1 ? '' : 's' }}
      </span>
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
