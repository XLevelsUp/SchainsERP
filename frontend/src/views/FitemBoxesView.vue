<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { fitemBoxesApi } from '@/lib/fitemBoxesApi'
import { itemsApi } from '@/lib/itemsApi'
import { ApiError } from '@/lib/api'
import type { DataTableColumn, FitemBox, FitemBoxFormValues, Item } from '@/types'

// Until real auth wiring exists, attribute changes to the seeded user (id 1).
const CURRENT_USER_ID = 1

const boxes = ref<FitemBox[]>([])
const items = ref<Item[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<FitemBox>[] = [
  { key: 'box_name', label: 'Box Name' },
  { key: 'item_id', label: 'Item' },
  { key: 'is_active', label: 'Status' },
  { key: 'added_at', label: 'Added' },
  { key: 'box_id', label: '' },
]

const itemNameById = computed(() => {
  const map = new Map<number, string>()
  for (const item of items.value) map.set(item.item_id, item.item_name)
  return map
})

function itemLabel(box: FitemBox): string {
  return box.item?.item_name ?? itemNameById.value.get(box.item_id) ?? `#${box.item_id}`
}

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [boxesData, itemsData] = await Promise.all([fitemBoxesApi.list(), itemsApi.list()])
    boxes.value = boxesData
    items.value = itemsData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load fitem boxes.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

const filteredBoxes = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return boxes.value
  return boxes.value.filter(
    (box) =>
      box.box_name.toLowerCase().includes(query) || itemLabel(box).toLowerCase().includes(query),
  )
})

const emptyForm: FitemBoxFormValues = {
  box_name: '',
  item_id: null,
  is_active: true,
  added_by: CURRENT_USER_ID,
  updated_by: null,
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<FitemBoxFormValues>({ ...emptyForm })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  editingId.value = null
  Object.assign(form, emptyForm)
  formError.value = ''
  isFormOpen.value = true
}

function openEditForm(box: FitemBox) {
  editingId.value = box.box_id
  Object.assign(form, {
    box_name: box.box_name,
    item_id: box.item_id,
    is_active: box.is_active,
    added_by: box.added_by,
    updated_by: box.updated_by,
  })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleSubmit() {
  if (!form.box_name.trim()) {
    formError.value = 'Box name is required.'
    return
  }
  if (form.item_id === null) {
    formError.value = 'Please select an item.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    if (editingId.value !== null) {
      await fitemBoxesApi.update(editingId.value, {
        box_name: form.box_name,
        item_id: form.item_id,
        is_active: form.is_active,
        updated_by: CURRENT_USER_ID,
      })
    } else {
      await fitemBoxesApi.create({
        box_name: form.box_name,
        item_id: form.item_id,
        is_active: form.is_active,
        added_by: CURRENT_USER_ID,
        updated_by: null,
      })
    }
    isFormOpen.value = false
    await loadData()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save fitem box.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(box: FitemBox) {
  if (!window.confirm(`Delete "${box.box_name}"?`)) return
  deletingId.value = box.box_id
  try {
    await fitemBoxesApi.remove(box.box_id)
    await loadData()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete fitem box.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Fitem Boxes" description="Manage storage boxes and the gold item each holds.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New box</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit box' : 'New box' }}
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
          id="box_name"
          v-model="form.box_name"
          label="Box name"
          placeholder="Gold Box"
          required
          :error="formError"
        />

        <BaseSelect id="item_id" v-model="form.item_id" label="Item" required>
          <option :value="null" disabled>Select an item…</option>
          <option v-for="item in items" :key="item.item_id" :value="item.item_id">
            {{ item.item_name }}
          </option>
        </BaseSelect>

        <BaseCheckbox v-model="form.is_active" label="Active" class="sm:col-span-2" />

        <div class="flex items-center gap-3 sm:col-span-2">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create box' }}
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
        placeholder="Search boxes…"
        aria-label="Search boxes"
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
      Loading fitem boxes…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredBoxes"
      empty-message="No boxes yet. Add your first box to get started."
    >
      <template #item_id="{ row }">
        {{ itemLabel(row as FitemBox) }}
      </template>

      <template #is_active="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #box_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit box"
            @click="openEditForm(row as FitemBox)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
            aria-label="Delete box"
            :disabled="deletingId === (row as FitemBox).box_id"
            @click="handleDelete(row as FitemBox)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>