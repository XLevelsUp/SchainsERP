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
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDateTime } from '@/lib/date'
import type {
  DataTableColumn,
  Item,
  StockInFormValues,
  StockInItemInput,
  StockInResultRow,
  UserDetailListItem,
} from '@/types'

const auth = useAuthStore()
const toastStore = useToastStore()

const items = ref<Item[]>([])
const users = ref<UserDetailListItem[]>([])
const isLoading = ref(false)
const loadError = ref('')

// GET /user-details no longer returns user_name in the list shape (PR #15)
// — id + name is the only reliable disambiguator now.
function userLabel(user: UserDetailListItem) {
  return `${user.name} (#${user.id})`
}
const userOptions = computed(() => users.value.map((u) => ({ value: u.id, label: userLabel(u) })))
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))

function itemName(id: number) {
  return items.value.find((i) => i.item_id === id)?.item_name ?? `#${id}`
}
function userName(id: number) {
  return users.value.find((u) => u.id === id)?.name ?? `#${id}`
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

function makeEmptyItem(): StockInItemInput {
  return {
    item_id: null,
    grams: null,
    touch: null,
    waste_total: 0,
    remarks: '',
    item_remarks: '',
  }
}

function makeEmptyForm(): StockInFormValues {
  return {
    given_by: null,
    given_to: null,
    added_at: '',
    items: [makeEmptyItem()],
  }
}

const form = reactive<StockInFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})
const lastResult = ref<StockInResultRow[] | null>(null)

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function addItemRow() {
  form.items.push(makeEmptyItem())
}
function removeItemRow(index: number) {
  form.items.splice(index, 1)
}
function onItemSelect(row: StockInItemInput, itemId: number | null) {
  row.item_id = itemId
  const item = items.value.find((i) => i.item_id === itemId)
  if (item && row.touch === null) row.touch = item.default_touch
}

// Mirrors StockInService::createStockIn()'s fallback formula exactly —
// display-only preview; the actual values are computed server-side.
function wasteValueFor(row: StockInItemInput): number {
  const grams = row.grams ?? 0
  const wasteTotal = row.waste_total ?? 0
  return (grams * wasteTotal) / 100
}
function purityFor(row: StockInItemInput): number {
  const grams = row.grams ?? 0
  const touch = row.touch ?? 0
  return (grams * touch) / 100 + wasteValueFor(row)
}

const resultColumns: DataTableColumn<StockInResultRow>[] = [
  { key: 'item_id', label: 'Item' },
  { key: 'given_by', label: 'Given by' },
  { key: 'given_to', label: 'Given to' },
  { key: 'grams', label: 'Grams' },
  { key: 'touch', label: 'Touch' },
  { key: 'waste_total', label: 'Waste %' },
  { key: 'purity', label: 'Purity' },
  { key: 'balance', label: 'Balance' },
]

/*
|--------------------------------------------------------------------------
| Validation — mirrors StockInRequest exactly
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
    if (row.touch === null || row.touch < 0 || row.touch > 100) {
      fieldErrors[`items.${index}.touch`] = `Row ${index + 1}: touch must be between 0 and 100.`
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
    formError.value = 'You must be signed in to record a stock in.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    const result = await stockApi.postStockIn(
      { ...form, items: form.items.map((i) => ({ ...i })) },
      auth.user.user_id,
    )
    lastResult.value = result
    toastStore.show(`Stock in recorded — ${result.length} item(s).`, 'success')
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
      formError.value = 'Failed to record stock in.'
      toastStore.show('Failed to record stock in.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Stock In"
      description="Move item stock back from a worker to the head/admin (New In flow)."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
      </template>
    </PageHeader>

    <p class="mb-4 text-xs text-slate-500">
      Create-only — the backend doesn't yet expose a way to list or edit past stock movements, so
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
            label="Given by (worker)"
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
            <div class="grid items-end gap-3 sm:grid-cols-[1.3fr_1fr_1fr_1fr_auto]">
              <BaseSelect
                :id="`item_${index}`"
                :model-value="row.item_id"
                label="Item"
                size="sm"
                placeholder="Select an item…"
                :options="itemOptions"
                :error="fieldErrors[`items.${index}.item_id`]"
                @update:model-value="(v) => onItemSelect(row, v as number | null)"
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
                :id="`touch_${index}`"
                :model-value="row.touch === null ? '' : String(row.touch)"
                label="Touch"
                type="number"
                step="0.01"
                size="sm"
                :error="fieldErrors[`items.${index}.touch`]"
                @update:model-value="(v) => (row.touch = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`waste_total_${index}`"
                :model-value="row.waste_total === null ? '' : String(row.waste_total)"
                label="Waste %"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.waste_total = v === '' ? null : Number(v))"
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
              Computed waste value: <span class="font-medium text-slate-700">{{ wasteValueFor(row).toFixed(4) }}</span>
              · Computed purity: <span class="font-medium text-slate-700">{{ purityFor(row).toFixed(4) }}</span>
              <span class="italic"> (server calculates the final values; this is a preview)</span>
            </p>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :icon="PackagePlus" :disabled="isSaving">
            {{ isSaving ? 'Recording…' : 'Record stock in' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <BaseCard v-if="lastResult">
      <h2 class="mb-1 text-sm font-semibold text-slate-900">Last recorded batch</h2>
      <p class="mb-4 text-xs text-slate-500">
        {{ lastResult[0] ? userName(lastResult[0].given_by) : '' }} — recorded
        {{ formatDateTime(lastResult[0]?.added_at) }}
      </p>
      <DataTable :columns="resultColumns" :rows="lastResult">
        <template #item_id="{ value }">{{ itemName(value as number) }}</template>
        <template #given_by="{ value }">{{ userName(value as number) }}</template>
        <template #given_to="{ value }">{{ userName(value as number) }}</template>
        <template #grams="{ value }">{{ Number(value).toFixed(3) }}</template>
        <template #touch="{ value }">{{ Number(value).toFixed(2) }}</template>
        <template #waste_total="{ value }">{{ Number(value ?? 0).toFixed(3) }}</template>
        <template #purity="{ value }">{{ Number(value).toFixed(4) }}</template>
        <template #balance="{ value }">{{ Number(value).toFixed(3) }}</template>
      </DataTable>
    </BaseCard>
  </div>
</template>
