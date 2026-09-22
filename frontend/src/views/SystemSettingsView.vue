<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { systemSettingsApi } from '@/lib/systemSettingsApi'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { DataTableColumn, SystemSetting } from '@/types'

/*
|--------------------------------------------------------------------------
| System Settings — /settings (PR #43–#45)
|--------------------------------------------------------------------------
| CRUD over the `system_settings` table: a key/value store the backend reads
| through SystemSetting::get($key, $default).
|
| Rows are addressed by `setting_key`, NOT by id. The route is registered
| ->parameters(['settings' => 'key']) and every single-row method does
| where('setting_key', $key)->firstOrFail(). The numeric id comes back on
| reads but is never used to address a row.
|
| `setting_value` is a json column validated `required|array` on create, so
| **a bare string or number is rejected with a 422**. The editor therefore
| takes JSON text and parses it before submitting — catching the mistake
| here with a useful message beats round-tripping to a raw validation error.
| A top-level scalar is valid JSON but not a valid value, so that is checked
| separately.
|
| setting_key is not updatable: update() validates only setting_value and
| description. The field is shown read-only when editing; renaming a key
| means delete + recreate, which the UI says out loud.
|--------------------------------------------------------------------------
*/

const toast = useToastStore()

const settings = ref<SystemSetting[]>([])
const isLoading = ref(false)
const loadError = ref('')
const search = ref('')

const columns: DataTableColumn<SystemSetting>[] = [
  { key: 'setting_key', label: 'Key' },
  { key: 'setting_value', label: 'Value' },
  { key: 'description', label: 'Description' },
  { key: 'updated_at', label: 'Updated' },
  { key: 'id', label: '' },
]

const visibleSettings = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return settings.value
  return settings.value.filter(
    (s) =>
      s.setting_key.toLowerCase().includes(term) ||
      (s.description ?? '').toLowerCase().includes(term),
  )
})

// Values are arbitrary JSON, so the table shows a compact one-line preview
// and the full document lives in the editor.
function previewValue(value: unknown): string {
  try {
    const json = JSON.stringify(value)
    if (json === undefined) return '—'
    return json.length > 80 ? `${json.slice(0, 80)}…` : json
  } catch {
    return '—'
  }
}

async function loadSettings() {
  isLoading.value = true
  loadError.value = ''
  try {
    settings.value = await systemSettingsApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load system settings.'
  } finally {
    isLoading.value = false
  }
}

// ---------------------------------------------------------------------------
// Create / edit form
// ---------------------------------------------------------------------------

const formMode = ref<'create' | 'edit' | null>(null)
const editingKey = ref<string | null>(null)
const form = reactive({
  setting_key: '',
  setting_value: '',
  description: '',
})
const formError = ref('')
const isSaving = ref(false)

function openCreate() {
  formMode.value = 'create'
  editingKey.value = null
  form.setting_key = ''
  // Seed with an empty object so the shape the backend demands is visible
  // rather than something the operator has to know.
  form.setting_value = '{}'
  form.description = ''
  formError.value = ''
}

function openEdit(row: SystemSetting) {
  formMode.value = 'edit'
  editingKey.value = row.setting_key
  form.setting_key = row.setting_key
  form.setting_value = JSON.stringify(row.setting_value, null, 2)
  form.description = row.description ?? ''
  formError.value = ''
}

function closeForm() {
  formMode.value = null
  editingKey.value = null
  formError.value = ''
}

// Returns the parsed value, or sets formError and returns undefined. The
// two failure modes are worth separating: invalid JSON is a typo, valid
// JSON that is a scalar is a misunderstanding of what the column accepts.
function parseValue(): unknown | undefined {
  const text = form.setting_value.trim()
  if (!text) {
    formError.value = 'Value is required. Use {} for an empty object.'
    return undefined
  }

  let parsed: unknown
  try {
    parsed = JSON.parse(text)
  } catch {
    formError.value = 'Value is not valid JSON. Check for a trailing comma or an unquoted key.'
    return undefined
  }

  if (parsed === null || typeof parsed !== 'object') {
    formError.value =
      'Value must be a JSON object or array — the backend rejects a plain string or number. Wrap it, e.g. {"value": 5}.'
    return undefined
  }
  return parsed
}

async function saveForm() {
  if (isSaving.value) return

  if (formMode.value === 'create' && !form.setting_key.trim()) {
    formError.value = 'Key is required.'
    return
  }

  const value = parseValue()
  if (value === undefined) return

  isSaving.value = true
  formError.value = ''
  const description = form.description.trim() || null
  try {
    if (formMode.value === 'create') {
      const created = await systemSettingsApi.create({
        setting_key: form.setting_key.trim(),
        setting_value: value,
        description,
      })
      settings.value = [created, ...settings.value]
      toast.show(`Setting "${created.setting_key}" created.`, 'success')
    } else if (editingKey.value) {
      const updated = await systemSettingsApi.update(editingKey.value, {
        setting_value: value,
        description,
      })
      const index = settings.value.findIndex((s) => s.setting_key === editingKey.value)
      if (index !== -1) settings.value[index] = updated
      toast.show(`Setting "${updated.setting_key}" saved.`, 'success')
    }
    closeForm()
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.errors
        ? (Object.values(err.errors)[0]?.[0] ?? err.message)
        : err.message
    } else {
      formError.value = 'Failed to save the setting.'
    }
  } finally {
    isSaving.value = false
  }
}

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

const deletingKey = ref<string | null>(null)

async function handleDelete(row: SystemSetting) {
  if (deletingKey.value !== null) return
  deletingKey.value = row.setting_key
  try {
    await systemSettingsApi.remove(row.setting_key)
    settings.value = settings.value.filter((s) => s.setting_key !== row.setting_key)
    if (editingKey.value === row.setting_key) closeForm()
    toast.show(`Setting "${row.setting_key}" deleted.`, 'success')
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to delete the setting.', 'error')
  } finally {
    deletingKey.value = null
  }
}

onMounted(loadSettings)
</script>

<template>
  <div>
    <PageHeader
      title="System Settings"
      description="Key/value configuration the backend reads at runtime."
    >
      <template #actions>
        <BaseButton
          variant="secondary"
          :icon="RefreshCw"
          :disabled="isLoading"
          @click="loadSettings"
        >
          Refresh
        </BaseButton>
        <BaseButton :icon="Plus" @click="openCreate">New setting</BaseButton>
      </template>
    </PageHeader>

    <BaseCard class="mb-4">
      <BaseInput
        v-model="search"
        label="Search"
        size="sm"
        placeholder="Filter by key or description…"
      />
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
          {{ formMode === 'create' ? 'New setting' : `Edit "${editingKey}"` }}
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
        <div class="grid gap-3 sm:grid-cols-2">
          <BaseInput
            v-model="form.setting_key"
            label="Key"
            required
            :disabled="formMode === 'edit'"
            placeholder="e.g. default_touch"
          />
          <BaseInput
            v-model="form.description"
            label="Description"
            placeholder="What this setting controls (optional)"
          />
        </div>

        <BaseTextarea
          v-model="form.setting_value"
          label="Value (JSON)"
          required
          :rows="8"
          placeholder='{ "enabled": true }'
        />
        <p class="-mt-2 text-xs text-slate-500">
          Must be a JSON <strong>object or array</strong>. A plain string or number is rejected by
          the backend — wrap it, e.g. <code>{ "value": 5 }</code>.
        </p>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

        <p v-if="formMode === 'edit'" class="text-xs text-slate-500">
          The key cannot be changed — the API updates the value and description only. To rename a
          setting, create the new key and delete this one.
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
          <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="closeForm">
            Cancel
          </BaseButton>
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : formMode === 'create' ? 'Create setting' : 'Save setting' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <p class="mb-2 text-sm text-slate-500">
      Showing <span class="font-medium text-slate-700">{{ visibleSettings.length }}</span> of
      <span class="font-medium text-slate-700">{{ settings.length }}</span> settings
    </p>

    <DataTable
      :columns="columns"
      :rows="visibleSettings"
      :empty-message="
        isLoading
          ? 'Loading…'
          : settings.length === 0
            ? 'No settings defined yet. Use “New setting” to add the first one.'
            : 'No settings match this search.'
      "
    >
      <template #setting_key="{ row }">
        <code class="font-medium text-slate-900">{{ row.setting_key }}</code>
      </template>
      <template #setting_value="{ row }">
        <code class="text-xs text-slate-600">{{ previewValue(row.setting_value) }}</code>
      </template>
      <template #description="{ row }">
        <span class="text-slate-600">{{ row.description || '—' }}</span>
      </template>
      <template #updated_at="{ row }">
        <span class="whitespace-nowrap text-slate-500">{{ formatDateTime(row.updated_at) }}</span>
      </template>
      <template #id="{ row }">
        <div class="flex justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit setting"
            @click="openEdit(row)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete the setting “${row.setting_key}”? Anything reading it falls back to its default.`"
            @confirm="handleDelete(row)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete setting"
                :disabled="deletingKey === row.setting_key"
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
