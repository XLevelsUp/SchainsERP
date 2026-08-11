<script setup lang="ts">
import { onMounted, reactive, ref, computed } from 'vue'
import { Plus, RefreshCw, Trash, PackagePlus } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { stockApi } from '@/lib/stockApi'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDateTime } from '@/lib/date'
import type {
  DataTableColumn,
  GmsInFormValues,
  GmsInItemInput,
  GmsInResultRow,
  Item,
  UserDetailListItem,
} from '@/types'

const auth = useAuthStore()
const toastStore = useToastStore()

const items = ref<Item[]>([])
const users = ref<UserDetailListItem[]>([])
const isLoading = ref(false)
const loadError = ref('')

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))

function itemName(id: number) {
  return items.value.find((i) => i.item_id === id)?.item_name ?? `#${id}`
}

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [itemsData, usersData] = await Promise.all([itemsApi.list(), userDetailsApi.list()])
    items.value = itemsData
    users.value = usersData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load items/users.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

/*
|--------------------------------------------------------------------------
| Form state
|--------------------------------------------------------------------------
*/

function makeEmptyItem(): GmsInItemInput {
  return {
    item_id: null,
    grams: null,
    stone: 0,
    thread: 0,
    wastage: 0,
    hall_mark: null,
    mtouch: 0,
    mtouch_wastage: 0,
    remarks: '',
    item_remarks: '',
  }
}

function makeEmptyForm(): GmsInFormValues {
  return {
    given_by: null,
    given_to: null,
    added_at: '',
    items: [makeEmptyItem()],
  }
}

const form = reactive<GmsInFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})
const lastResult = ref<GmsInResultRow[] | null>(null)

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function addItemRow() {
  form.items.push(makeEmptyItem())
}
function removeItemRow(index: number) {
  form.items.splice(index, 1)
}

// Mirrors StockInService::createGmsIn()'s fallback formula exactly —
// display-only preview; the actual values are computed server-side.
function netGramsFor(row: GmsInItemInput): number {
  return (row.grams ?? 0) - (row.stone ?? 0) - (row.thread ?? 0)
}
function wastageGramsFor(row: GmsInItemInput): number {
  return (netGramsFor(row) * (row.wastage ?? 0)) / 100
}
function purityFor(row: GmsInItemInput): number {
  return ((netGramsFor(row) + wastageGramsFor(row)) * (row.hall_mark ?? 0)) / 100
}

const resultColumns: DataTableColumn<GmsInResultRow>[] = [
  { key: 'item_id', label: 'Item' },
  { key: 'grams', label: 'Grams' },
  { key: 'stone', label: 'Stone' },
  { key: 'thread', label: 'Thread' },
  { key: 'wastage', label: 'Wastage %' },
  { key: 'hall_mark', label: 'Hall mark' },
  { key: 'total', label: 'Total (purity)' },
]

/*
|--------------------------------------------------------------------------
| Validation — mirrors GmsInRequest exactly
|--------------------------------------------------------------------------
*/

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.given_by === null) fieldErrors.given_by = 'Given by is required.'
  if (form.given_to === null) fieldErrors.given_to = 'Given to is required.'
  if (form.given_by !== null && form.given_by === form.given_to) {
    fieldErrors.given_to = 'Given to must be different from given by.'
  }

  if (form.items.length === 0) {
    fieldErrors.items = 'Add at least one item.'
  }

  form.items.forEach((row, index) => {
    if (row.item_id === null) {
      fieldErrors[`items.${index}.item_id`] = `Row ${index + 1}: select an item.`
    }
    if (row.grams === null || row.grams <= 0) {
      fieldErrors[`items.${index}.grams`] = `Row ${index + 1}: grams must be greater than 0.`
    }
    if (row.hall_mark === null || row.hall_mark < 0 || row.hall_mark > 100) {
      fieldErrors[`items.${index}.hall_mark`] = `Row ${index + 1}: hall mark must be between 0 and 100.`
    }
  })

  const firstError = Object.values(fieldErrors)[0]
  if (firstError) {
    formError.value = firstError
    toastStore.show(firstError, 'error')
    return false
  }
  return true
}

async function handleSubmit() {
  if (!validate()) return
  if (!auth.user) {
    formError.value = 'You must be signed in to record a GMS in.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    const result = await stockApi.postGmsIn(
      { ...form, items: form.items.map((i) => ({ ...i })) },
      auth.user.user_id,
    )
    lastResult.value = result
    toastStore.show(`GMS in recorded — ${result.length} item(s).`, 'success')
    Object.assign(form, makeEmptyForm())
    clearFieldErrors()
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      if (err.errors) {
        for (const [key, messages] of Object.entries(err.errors)) {
          fieldErrors[key] = messages[0]
        }
        toastStore.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toastStore.show(err.message, 'error')
      }
    } else {
      formError.value = 'Failed to record GMS in.'
      toastStore.show('Failed to record GMS in.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="GMS In"
      description="Record a goldsmith's gold return, with stone/thread deductions and hallmark purity."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
      </template>
    </PageHeader>

    <p class="mb-4 text-xs text-slate-500">
      Create-only — the backend doesn't yet expose a way to list or edit past GMS movements, so
      each submission's result is shown below the form.
    </p>

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

    <BaseCard v-else class="mb-6">
      <p
        v-if="formError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ formError }}
      </p>

      <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <section class="grid gap-3 sm:grid-cols-3">
          <BaseSelect
            id="given_by"
            :model-value="form.given_by"
            label="Given by (goldsmith)"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.given_by"
            @update:model-value="(v) => (form.given_by = v as number | null)"
          />
          <BaseSelect
            id="given_to"
            :model-value="form.given_to"
            label="Given to (head/admin)"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.given_to"
            @update:model-value="(v) => (form.given_to = v as number | null)"
          />
          <BaseInput
            id="added_at"
            v-model="form.added_at"
            label="Date (optional, defaults to now)"
            type="date"
            size="sm"
          />
        </section>

        <section class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Items</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addItemRow">
              Add item
            </BaseButton>
          </div>
          <p v-if="fieldErrors.items" class="mb-2 text-sm text-red-600">{{ fieldErrors.items }}</p>

          <div
            v-for="(row, index) in form.items"
            :key="index"
            class="mb-3 rounded-lg border border-slate-200 p-3"
          >
            <div class="grid items-end gap-3 sm:grid-cols-3">
              <BaseSelect
                :id="`item_${index}`"
                :model-value="row.item_id"
                label="Item"
                size="sm"
                placeholder="Select an item…"
                :options="itemOptions"
                :error="fieldErrors[`items.${index}.item_id`]"
                @update:model-value="(v) => (row.item_id = v as number | null)"
              />
              <BaseInput
                :id="`grams_${index}`"
                :model-value="row.grams === null ? '' : String(row.grams)"
                label="Grams"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`items.${index}.grams`]"
                @update:model-value="(v) => (row.grams = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`hall_mark_${index}`"
                :model-value="row.hall_mark === null ? '' : String(row.hall_mark)"
                label="Hall mark"
                type="number"
                step="0.01"
                size="sm"
                :error="fieldErrors[`items.${index}.hall_mark`]"
                @update:model-value="(v) => (row.hall_mark = v === '' ? null : Number(v))"
              />
            </div>

            <div class="mt-2 grid items-end gap-3 sm:grid-cols-[repeat(5,1fr)_auto]">
              <BaseInput
                :id="`stone_${index}`"
                :model-value="row.stone === null ? '' : String(row.stone)"
                label="Stone"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.stone = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`thread_${index}`"
                :model-value="row.thread === null ? '' : String(row.thread)"
                label="Thread"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.thread = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`wastage_${index}`"
                :model-value="row.wastage === null ? '' : String(row.wastage)"
                label="Wastage %"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.wastage = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`mtouch_${index}`"
                :model-value="row.mtouch === null ? '' : String(row.mtouch)"
                label="M.Touch"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.mtouch = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`mtouch_wastage_${index}`"
                :model-value="row.mtouch_wastage === null ? '' : String(row.mtouch_wastage)"
                label="M.Touch wastage"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.mtouch_wastage = v === '' ? null : Number(v))"
              />
              <button
                type="button"
                class="mb-1 justify-self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Remove item"
                :disabled="form.items.length === 1"
                @click="removeItemRow(index)"
              >
                <Trash class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-2 grid gap-3 sm:grid-cols-2">
              <BaseInput
                :id="`remarks_${index}`"
                :model-value="row.remarks"
                label="Remarks (optional)"
                size="sm"
                @update:model-value="(v) => (row.remarks = v)"
              />
              <BaseInput
                :id="`item_remarks_${index}`"
                :model-value="row.item_remarks"
                label="Item remarks (optional)"
                size="sm"
                @update:model-value="(v) => (row.item_remarks = v)"
              />
            </div>

            <p class="mt-2 text-xs text-slate-500">
              Net grams: <span class="font-medium text-slate-700">{{ netGramsFor(row).toFixed(4) }}</span>
              · Wastage grams: <span class="font-medium text-slate-700">{{ wastageGramsFor(row).toFixed(4) }}</span>
              · Computed purity: <span class="font-medium text-slate-700">{{ purityFor(row).toFixed(4) }}</span>
              <span class="italic"> (server calculates the final values; this is a preview)</span>
            </p>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :icon="PackagePlus" :disabled="isSaving">
            {{ isSaving ? 'Recording…' : 'Record GMS in' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <BaseCard v-if="lastResult">
      <h2 class="mb-1 text-sm font-semibold text-slate-900">Last recorded batch</h2>
      <p class="mb-4 text-xs text-slate-500">Recorded {{ formatDateTime(lastResult[0]?.added_at) }}</p>
      <DataTable :columns="resultColumns" :rows="lastResult">
        <template #item_id="{ value }">{{ itemName(value as number) }}</template>
        <template #grams="{ value }">{{ Number(value).toFixed(3) }}</template>
        <template #stone="{ value }">{{ Number(value).toFixed(3) }}</template>
        <template #thread="{ value }">{{ Number(value).toFixed(3) }}</template>
        <template #wastage="{ value }">{{ Number(value).toFixed(3) }}</template>
        <template #hall_mark="{ value }">{{ Number(value).toFixed(2) }}</template>
        <template #total="{ value }">{{ Number(value).toFixed(4) }}</template>
      </DataTable>
    </BaseCard>
  </div>
</template>
