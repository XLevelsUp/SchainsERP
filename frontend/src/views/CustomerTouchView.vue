<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import { customerTouchApi } from '@/lib/customerTouchApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { CustomerTouch, CustomerTouchFormValues, DataTableColumn } from '@/types'

const touches = ref<CustomerTouch[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<CustomerTouch>[] = [
  { key: 'item_name', label: 'Name' },
  { key: 'is_active', label: 'Status' },
  { key: 'added_at', label: 'Added' },
  { key: 'item_id', label: '' },
]

async function loadTouches() {
  isLoading.value = true
  loadError.value = ''
  try {
    touches.value = await customerTouchApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load customer touch items.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadTouches)

const filteredTouches = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return touches.value
  return touches.value.filter((t) => t.item_name.toLowerCase().includes(query))
})

const emptyForm: CustomerTouchFormValues = {
  item_name: '',
  is_active: true,
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<CustomerTouchFormValues>({ ...emptyForm })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  editingId.value = null
  Object.assign(form, emptyForm)
  formError.value = ''
  isFormOpen.value = true
}

function openEditForm(touch: CustomerTouch) {
  editingId.value = touch.item_id
  Object.assign(form, { item_name: touch.item_name, is_active: touch.is_active })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleSubmit() {
  if (!form.item_name.trim()) {
    formError.value = 'Name is required.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    if (editingId.value !== null) {
      await customerTouchApi.update(editingId.value, { ...form })
    } else {
      await customerTouchApi.create({ ...form })
    }
    isFormOpen.value = false
    await loadTouches()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save customer touch item.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(touch: CustomerTouch) {
  deletingId.value = touch.item_id
  try {
    await customerTouchApi.remove(touch.item_id)
    await loadTouches()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete customer touch item.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Customer Touch" description="Manage customer touch options.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadTouches">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New touch</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit customer touch' : 'New customer touch' }}
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

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <BaseInput
          id="item_name"
          v-model="form.item_name"
          label="Name"
          placeholder="Touch"
          required
          maxlength="150"
          :error="formError"
          class="max-w-sm"
        />

        <BaseCheckbox v-model="form.is_active" label="Active" />

        <div class="flex items-center gap-3">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create touch' }}
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
        placeholder="Search customer touch…"
        aria-label="Search customer touch"
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
      Loading customer touch items…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredTouches"
      empty-message="No customer touch items yet. Add your first one to get started."
    >
      <template #is_active="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #added_at="{ value }">{{ value ? formatDateTime(value as string) : '—' }}</template>

      <template #item_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit customer touch"
            @click="openEditForm(row as CustomerTouch)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete ${(row as CustomerTouch).item_name}?`"
            @confirm="handleDelete(row as CustomerTouch)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete customer touch"
                :disabled="deletingId === (row as CustomerTouch).item_id"
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
