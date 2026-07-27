<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { itemsApi } from '@/lib/itemsApi'
import { ApiError } from '@/lib/api'
import type { DataTableColumn, Item, ItemFormValues } from '@/types'

const items = ref<Item[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<Item>[] = [
  { key: 'item_name', label: 'Name' },
  { key: 'default_touch', label: 'Default Touch' },
  { key: 'item_touch', label: 'Item Touch' },
  { key: 'is_active', label: 'Status' },
  { key: 'added_at', label: 'Added' },
  { key: 'item_id', label: '' },
]

async function loadItems() {
  isLoading.value = true
  loadError.value = ''
  try {
    items.value = await itemsApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load items.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadItems)

const filteredItems = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return items.value
  return items.value.filter((item) => item.item_name.toLowerCase().includes(query))
})

const emptyForm: ItemFormValues = {
  item_name: '',
  is_active: true,
  default_touch: 92,
  item_touch: 0,
  is_need_fitem_shown: false,
  mtouch: null,
  is_barcode: false,
  is_no_barcode: false,
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<ItemFormValues>({ ...emptyForm })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  editingId.value = null
  Object.assign(form, emptyForm)
  formError.value = ''
  isFormOpen.value = true
}

function openEditForm(item: Item) {
  editingId.value = item.item_id
  Object.assign(form, {
    item_name: item.item_name,
    is_active: item.is_active,
    default_touch: item.default_touch,
    item_touch: item.item_touch,
    is_need_fitem_shown: item.is_need_fitem_shown,
    mtouch: item.mtouch,
    is_barcode: item.is_barcode,
    is_no_barcode: item.is_no_barcode,
  })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleSubmit() {
  if (!form.item_name.trim()) {
    formError.value = 'Item name is required.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    if (editingId.value !== null) {
      await itemsApi.update(editingId.value, { ...form })
    } else {
      await itemsApi.create({ ...form })
    }
    isFormOpen.value = false
    await loadItems()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save item.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(item: Item) {
  if (!window.confirm(`Delete "${item.item_name}"?`)) return
  deletingId.value = item.item_id
  try {
    await itemsApi.remove(item.item_id)
    await loadItems()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete item.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Items" description="Manage gold items and their touch/purity details.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadItems">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New item</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit item' : 'New item' }}
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
          id="item_name"
          v-model="form.item_name"
          label="Item name"
          placeholder="Gold Ring"
          required
          :error="formError"
        />
        <BaseInput
          id="default_touch"
          :model-value="String(form.default_touch)"
          label="Default touch"
          type="number"
          @update:model-value="(v) => (form.default_touch = Number(v))"
        />
        <BaseInput
          id="item_touch"
          :model-value="String(form.item_touch)"
          label="Item touch"
          type="number"
          @update:model-value="(v) => (form.item_touch = Number(v))"
        />
        <BaseInput
          id="mtouch"
          :model-value="form.mtouch === null ? '' : String(form.mtouch)"
          label="M-touch"
          type="number"
          placeholder="Optional"
          @update:model-value="(v) => (form.mtouch = v === '' ? null : Number(v))"
        />

        <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row sm:flex-wrap sm:gap-6">
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
            Active
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="form.is_barcode" type="checkbox" class="rounded border-slate-300" />
            Has barcode
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="form.is_no_barcode" type="checkbox" class="rounded border-slate-300" />
            No barcode
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input
              v-model="form.is_need_fitem_shown"
              type="checkbox"
              class="rounded border-slate-300"
            />
            Show f-item
          </label>
        </div>

        <div class="flex items-center gap-3 sm:col-span-2">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create item' }}
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
          placeholder="Search items…"
          aria-label="Search items"
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
      Loading items…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredItems"
      empty-message="No items yet. Add your first item to get started."
    >
      <template #is_active="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #item_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit item"
            @click="openEditForm(row as Item)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
            aria-label="Delete item"
            :disabled="deletingId === (row as Item).item_id"
            @click="handleDelete(row as Item)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>