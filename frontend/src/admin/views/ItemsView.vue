<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Check, ChevronLeft, ChevronRight, Download, Eye, Pencil, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import { filterItems, itemsCsv, nextOrderNo, sortByOrder, type YesNoAll } from '@/lib/items'
import { useToastStore } from '@/stores/toast'
import type { DataTableColumn, Item, ItemFormValues } from '@/types'
import { itemsApi } from '../lib/resources'

/*
|--------------------------------------------------------------------------
| Admin console — Items (2026-11-24, the legacy Items page)
|--------------------------------------------------------------------------
| The items every screen picks from. Here only: heads read items in the app
| (GET /items) but no longer change them.
|
| "Orders No" is the order items appear in, in every item dropdown and list
| (GET /items sorts by it, then by name). Unique, 1 or more; a new item
| starts with the next free number.
|
| Columns follow the legacy list: Item Name, Item Touch, Is Need Fitem
| Shown, Is Active, Orders No. Default touch, M-touch and the barcode
| settings are in the view and the form. Is Need Fitem Shown and Is Active
| switch from the list (with a confirm), like the legacy editable ticks.
|
| An item still used by a user or a transaction can't be deleted (the
| backend refuses, since deleting would cascade into balances and history):
| switch it off instead.
|--------------------------------------------------------------------------
*/

const PAGE_SIZE = 20

const toast = useToastStore()

const items = ref<Item[]>([])
const isLoading = ref(false)
const loadError = ref('')

async function loadItems() {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    items.value = sortByOrder(await itemsApi.list())
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load items.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadItems)

// ---- filters & paging --------------------------------------------------------

const filters = reactive({ name: '', fitem: 'all' as YesNoAll, active: 'all' as YesNoAll, orderNo: '' })
const yesNoOptions: { value: YesNoAll; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'yes', label: 'Yes' },
  { value: 'no', label: 'No' },
]

const filtered = computed(() => filterItems(items.value, filters))
const isFiltered = computed(
  () => !!filters.name.trim() || !!filters.orderNo.trim() || filters.fitem !== 'all' || filters.active !== 'all',
)

const page = ref(1)
const showAll = ref(false)
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)))
const offset = computed(() => (showAll.value ? 0 : (page.value - 1) * PAGE_SIZE))
const pageRows = computed(() => (showAll.value ? filtered.value : filtered.value.slice(offset.value, offset.value + PAGE_SIZE)))
const rangeTo = computed(() => (showAll.value ? filtered.value.length : Math.min(page.value * PAGE_SIZE, filtered.value.length)))

watch(filters, () => (page.value = 1), { deep: true })
watch(lastPage, (last) => {
  if (page.value > last) page.value = last
})

const columns: DataTableColumn<Item>[] = [
  { key: 'item_name', label: 'Item Name' },
  { key: 'item_touch', label: 'Item Touch', align: 'right' },
  { key: 'is_need_fitem_shown', label: 'Is Need Fitem Shown' },
  { key: 'is_active', label: 'Is Active' },
  { key: 'order_no', label: 'Orders No', align: 'right' },
  { key: 'item_id', label: 'Actions' },
]

function touch(value: number | null | undefined): string {
  return value === null || value === undefined ? '—' : String(Number(value))
}

// ---- export ------------------------------------------------------------------

function exportCsv() {
  const blob = new Blob(['﻿' + itemsCsv(filtered.value)], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  try {
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `items-${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
  } finally {
    URL.revokeObjectURL(url)
  }
}

// ---- switches from the list ------------------------------------------------------

const togglingKey = ref('')

async function toggleFlag(item: Item, flag: 'is_active' | 'is_need_fitem_shown') {
  if (togglingKey.value) return
  togglingKey.value = `${flag}-${item.item_id}`
  try {
    const saved = await itemsApi.update(item.item_id, { [flag]: !item[flag] })
    replaceItem(saved)
    toast.show(`${saved.item_name} updated.`, 'success')
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to update the item.', 'error')
  } finally {
    togglingKey.value = ''
  }
}

function replaceItem(saved: Item) {
  const index = items.value.findIndex((i) => i.item_id === saved.item_id)
  if (index >= 0) items.value.splice(index, 1, saved)
  items.value = sortByOrder(items.value)
}

// ---- create / edit --------------------------------------------------------------

const formTarget = ref<{ id: number | null } | null>(null)
const form = reactive<ItemFormValues>({
  item_name: '',
  is_active: true,
  default_touch: 92,
  item_touch: 0,
  is_need_fitem_shown: false,
  mtouch: null,
  is_barcode: false,
  is_no_barcode: false,
  order_no: null,
})
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)

// Number inputs as text, so an empty one is "not set" rather than 0.
const touchText = reactive({ default_touch: '92', item_touch: '0', mtouch: '', order_no: '' })

function clearErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''
}

function openCreate() {
  Object.assign(form, {
    item_name: '',
    is_active: true,
    default_touch: 92,
    item_touch: 0,
    is_need_fitem_shown: false,
    mtouch: null,
    is_barcode: false,
    is_no_barcode: false,
    order_no: null,
  })
  Object.assign(touchText, { default_touch: '92', item_touch: '0', mtouch: '', order_no: String(nextOrderNo(items.value)) })
  clearErrors()
  viewing.value = null
  formTarget.value = { id: null }
}

function openEdit(item: Item) {
  Object.assign(form, {
    item_name: item.item_name,
    is_active: item.is_active,
    default_touch: item.default_touch,
    item_touch: item.item_touch,
    is_need_fitem_shown: item.is_need_fitem_shown,
    mtouch: item.mtouch,
    is_barcode: item.is_barcode,
    is_no_barcode: item.is_no_barcode,
    order_no: item.order_no,
  })
  Object.assign(touchText, {
    default_touch: String(item.default_touch ?? ''),
    item_touch: String(item.item_touch ?? ''),
    mtouch: item.mtouch === null ? '' : String(item.mtouch),
    order_no: String(item.order_no ?? ''),
  })
  clearErrors()
  viewing.value = null
  formTarget.value = { id: item.item_id }
}

const numberPattern = /^-?\d+(\.\d+)?$/

function validate(): boolean {
  clearErrors()
  if (!form.item_name.trim()) fieldErrors.item_name = 'Item name is required.'
  else if (form.item_name.trim().length > 255) fieldErrors.item_name = 'Item name must be 255 characters or fewer.'
  for (const key of ['default_touch', 'item_touch'] as const) {
    if (!numberPattern.test(touchText[key].trim())) fieldErrors[key] = 'Enter a number.'
  }
  if (touchText.mtouch.trim() && !numberPattern.test(touchText.mtouch.trim())) fieldErrors.mtouch = 'Enter a number or leave it empty.'
  const order = touchText.order_no.trim()
  if (!order) fieldErrors.order_no = 'Orders No is required.'
  else if (!/^\d+$/.test(order) || Number(order) < 1) fieldErrors.order_no = 'The order number must be a whole number, 1 or more.'
  else if (items.value.some((i) => i.order_no === Number(order) && i.item_id !== formTarget.value?.id)) {
    const taken = items.value.find((i) => i.order_no === Number(order))
    fieldErrors.order_no = `${taken?.item_name ?? 'Another item'} already has this order number.`
  }
  return Object.keys(fieldErrors).length === 0
}

async function handleSave() {
  if (isSaving.value || !formTarget.value || !validate()) return
  isSaving.value = true
  const payload: ItemFormValues = {
    ...form,
    item_name: form.item_name.trim(),
    default_touch: Number(touchText.default_touch),
    item_touch: Number(touchText.item_touch),
    mtouch: touchText.mtouch.trim() ? Number(touchText.mtouch) : null,
    order_no: Number(touchText.order_no),
  }
  try {
    const id = formTarget.value.id
    const saved = id === null ? await itemsApi.create(payload) : await itemsApi.update(id, payload)
    formTarget.value = null
    toast.show(`${saved.item_name} ${id === null ? 'created' : 'updated'}.`, 'success')
    await loadItems()
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      for (const [key, messages] of Object.entries(err.errors ?? {})) fieldErrors[key] = messages[0] ?? ''
    } else {
      formError.value = 'Failed to save the item.'
    }
  } finally {
    isSaving.value = false
  }
}

// ---- view / delete ------------------------------------------------------------

const viewing = ref<Item | null>(null)
const deletingId = ref<number | null>(null)

async function handleDelete(item: Item) {
  if (deletingId.value !== null) return
  deletingId.value = item.item_id
  try {
    await itemsApi.remove(item.item_id)
    items.value = items.value.filter((i) => i.item_id !== item.item_id)
    toast.show(`${item.item_name} deleted.`, 'success')
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to delete the item.', 'error')
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Items"
      description="The items every screen picks from. Orders No sets their order in every item dropdown and list."
    >
      <template #actions>
        <BaseButton variant="secondary" class="shrink-0" :icon="Download" :disabled="!filtered.length" @click="exportCsv">
          Export
        </BaseButton>
        <BaseButton variant="secondary" class="shrink-0" :icon="RefreshCw" :disabled="isLoading" @click="loadItems">
          Refresh
        </BaseButton>
        <BaseButton class="shrink-0 whitespace-nowrap" :icon="Plus" @click="openCreate">New item</BaseButton>
      </template>
    </PageHeader>

    <div class="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <BaseInput v-model="filters.name" type="search" size="sm" placeholder="Item name…" aria-label="Filter by item name" />
      <BaseSelect v-model="filters.fitem" size="sm" aria-label="Filter by Is Need Fitem Shown" :options="yesNoOptions.map((o) => ({ ...o, label: `Fitem shown: ${o.label}` }))" />
      <BaseSelect v-model="filters.active" size="sm" aria-label="Filter by Is Active" :options="yesNoOptions.map((o) => ({ ...o, label: `Active: ${o.label}` }))" />
      <BaseInput v-model="filters.orderNo" type="search" size="sm" inputmode="numeric" placeholder="Orders No…" aria-label="Filter by order number" />
    </div>

    <div
      v-if="loadError"
      class="mb-4 flex items-center justify-between gap-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      <span>{{ loadError }}</span>
      <button type="button" class="font-medium underline" @click="loadItems">Try again</button>
    </div>

    <div
      v-if="isLoading && !items.length"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading items…
    </div>

    <template v-else>
      <DataTable
        :columns="columns"
        :rows="pageRows"
        size="sm"
        :empty-message="isFiltered ? 'No items match these filters.' : 'No items yet. Add the first one.'"
      >
        <template #item_name="{ value }">
          <span class="font-medium text-slate-900">{{ value }}</span>
        </template>

        <template #item_touch="{ row }">
          <span class="block text-right tabular-nums">{{ touch(row.item_touch) }}</span>
        </template>

        <template v-for="flag in (['is_need_fitem_shown', 'is_active'] as const)" :key="flag" #[flag]="{ row }">
          <ConfirmPopover
            :message="`${row[flag] ? 'Turn off' : 'Turn on'} ${flag === 'is_active' ? 'Is Active' : 'Is Need Fitem Shown'} for ${row.item_name}?`"
            :confirm-label="row[flag] ? 'Turn off' : 'Turn on'"
            @confirm="toggleFlag(row, flag)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium disabled:opacity-50"
                :class="row[flag] ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-red-50 text-red-600 hover:bg-red-100'"
                :disabled="togglingKey === `${flag}-${row.item_id}`"
                :aria-label="`${flag === 'is_active' ? 'Is Active' : 'Is Need Fitem Shown'} for ${row.item_name}: ${row[flag] ? 'Yes' : 'No'}`"
                @click="toggle"
              >
                <Check v-if="row[flag]" class="h-3.5 w-3.5" aria-hidden="true" />
                <X v-else class="h-3.5 w-3.5" aria-hidden="true" />
                {{ row[flag] ? 'Yes' : 'No' }}
              </button>
            </template>
          </ConfirmPopover>
        </template>

        <template #order_no="{ value }">
          <span class="block text-right font-semibold text-slate-900 tabular-nums">{{ value }}</span>
        </template>

        <template #item_id="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button
              type="button"
              class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              :aria-label="`View ${row.item_name}`"
              @click="viewing = row"
            >
              <Eye class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              :aria-label="`Edit ${row.item_name}`"
              @click="openEdit(row)"
            >
              <Pencil class="h-4 w-4" />
            </button>
            <ConfirmPopover
              :message="`Delete ${row.item_name}? An item a user or a transaction still uses can't be deleted; switch it off instead.`"
              confirm-label="Delete"
              @confirm="handleDelete(row)"
            >
              <template #default="{ toggle }">
                <button
                  type="button"
                  class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                  :aria-label="`Delete ${row.item_name}`"
                  :disabled="deletingId === row.item_id"
                  @click="toggle"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </template>
            </ConfirmPopover>
          </div>
        </template>
      </DataTable>

      <div v-if="filtered.length" class="mt-3 flex items-center justify-between text-xs text-slate-500">
        <span class="tabular-nums">Showing {{ offset + 1 }}–{{ rangeTo }} of {{ filtered.length }} items</span>
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

    <!-- Create / edit -->
    <BaseModal
      v-if="formTarget"
      :title="formTarget.id === null ? 'New item' : 'Edit item'"
      max-width="max-w-lg"
      @close="formTarget = null"
    >
      <form class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="handleSave">
        <div class="sm:col-span-2">
          <BaseInput id="item_name" v-model="form.item_name" label="Item name" required :error="fieldErrors.item_name" />
        </div>
        <BaseInput id="item_touch" v-model="touchText.item_touch" label="Item touch" inputmode="decimal" required :error="fieldErrors.item_touch" />
        <BaseInput
          id="item_order_no"
          v-model="touchText.order_no"
          label="Orders No"
          inputmode="numeric"
          required
          :error="fieldErrors.order_no"
        />
        <BaseInput id="default_touch" v-model="touchText.default_touch" label="Default touch" inputmode="decimal" required :error="fieldErrors.default_touch" />
        <BaseInput id="mtouch" v-model="touchText.mtouch" label="M-touch" inputmode="decimal" placeholder="Optional" :error="fieldErrors.mtouch" />
        <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row sm:flex-wrap sm:gap-6">
          <BaseCheckbox id="item_active" v-model="form.is_active" label="Is active" />
          <BaseCheckbox id="item_fitem" v-model="form.is_need_fitem_shown" label="Is need fitem shown" />
          <BaseCheckbox v-model="form.is_barcode" label="Has barcode" />
          <BaseCheckbox v-model="form.is_no_barcode" label="No barcode" />
        </div>
        <p class="text-xs text-slate-500 sm:col-span-2">
          Orders No sets where this item appears in every item dropdown and list (1 first). No two items can share one.
        </p>

        <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 sm:col-span-2">{{ formError }}</p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 sm:col-span-2">
          <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="formTarget = null">Close</BaseButton>
          <BaseButton type="submit" :disabled="isSaving">{{ isSaving ? 'Saving…' : 'Save' }}</BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- View -->
    <BaseModal v-if="viewing" :title="viewing.item_name" max-width="max-w-md" @close="viewing = null">
      <dl class="grid grid-cols-[10rem_1fr] gap-x-4 gap-y-3 text-sm">
        <dt class="text-slate-500">Orders No</dt>
        <dd class="font-semibold text-slate-900 tabular-nums">{{ viewing.order_no }}</dd>
        <dt class="text-slate-500">Item touch</dt>
        <dd class="text-slate-900 tabular-nums">{{ touch(viewing.item_touch) }}</dd>
        <dt class="text-slate-500">Default touch</dt>
        <dd class="text-slate-900 tabular-nums">{{ touch(viewing.default_touch) }}</dd>
        <dt class="text-slate-500">M-touch</dt>
        <dd class="text-slate-900 tabular-nums">{{ touch(viewing.mtouch) }}</dd>
        <dt class="text-slate-500">Is need fitem shown</dt>
        <dd class="text-slate-900">{{ viewing.is_need_fitem_shown ? 'Yes' : 'No' }}</dd>
        <dt class="text-slate-500">Is active</dt>
        <dd class="text-slate-900">{{ viewing.is_active ? 'Yes' : 'No' }}</dd>
        <dt class="text-slate-500">Barcode</dt>
        <dd class="text-slate-900">{{ viewing.is_barcode ? 'Has barcode' : viewing.is_no_barcode ? 'No barcode' : '—' }}</dd>
        <dt class="text-slate-500">Added</dt>
        <dd class="text-slate-900">{{ formatDateTime(viewing.added_at) }}</dd>
      </dl>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" @click="viewing = null">Close</BaseButton>
        <BaseButton :icon="Pencil" @click="openEdit(viewing)">Edit</BaseButton>
      </div>
    </BaseModal>
  </div>
</template>
