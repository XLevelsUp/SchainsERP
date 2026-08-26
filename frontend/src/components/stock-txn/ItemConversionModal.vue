<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Plus, Trash } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { stockApi } from '@/lib/stockApi'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import { todayDateInputValue } from '@/lib/date'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type {
  Item,
  ItemConversionAlloyInput,
  ItemConversionFormValues,
  ItemConversionItemInput,
  UserDetailListItem,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Item Conversion modal — new screen, mirrors ItemConversionRequest.php.
|--------------------------------------------------------------------------
| stock_in_id is nullable in ItemConversionRequest.php, so it's hidden from
| the UI (always sent as null) rather than shown as a numeric input — no
| backend endpoint lists a user's stock lots to pick from.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; users: UserDetailListItem[] }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toastStore = useToastStore()

const userOptions = computed(() =>
  props.users.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

function makeEmptyAlloy(): ItemConversionAlloyInput {
  return { alloy_item_id: null, alloy_percentage: null, alloy_grams: null }
}
function makeEmptyItem(): ItemConversionItemInput {
  return {
    stock_in_id: null,
    source_item_id: null,
    target_item_id: null,
    source_grams: null,
    source_touch: 100,
    target_touch: 100,
    remarks: '',
    item_remarks: '',
    alloys: [],
  }
}
function makeEmptyForm(): ItemConversionFormValues {
  return { user_id: null, added_at: todayDateInputValue(), items: [makeEmptyItem()] }
}

const form = reactive<ItemConversionFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}
function addItemRow() {
  form.items.push(makeEmptyItem())
}
function removeItemRow(index: number) {
  form.items.splice(index, 1)
}
function addAlloyRow(row: ItemConversionItemInput) {
  row.alloys.push(makeEmptyAlloy())
}
function removeAlloyRow(row: ItemConversionItemInput, alloyIndex: number) {
  row.alloys.splice(alloyIndex, 1)
}

// Mirrors StockOutService::createItemConversion()'s fallback formula —
// display preview only; the server derives the final converted grams.
function convertedGramsFor(row: ItemConversionItemInput): number {
  const targetTouch = row.target_touch ?? 0
  if (targetTouch === 0) return 0
  return ((row.source_grams ?? 0) * (row.source_touch ?? 0)) / targetTouch
}

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.user_id === null) fieldErrors.user_id = 'User is required.'

  if (form.items.length === 0) {
    fieldErrors.items = 'Add at least one item.'
  }

  form.items.forEach((row, index) => {
    if (row.source_item_id === null) {
      fieldErrors[`items.${index}.source_item_id`] = `Row ${index + 1}: select the source item.`
    }
    if (row.target_item_id === null) {
      fieldErrors[`items.${index}.target_item_id`] = `Row ${index + 1}: select the target item.`
    }
    if (row.source_item_id !== null && row.source_item_id === row.target_item_id) {
      fieldErrors[`items.${index}.target_item_id`] = `Row ${index + 1}: target item must differ from source item.`
    }
    if (row.source_grams === null || row.source_grams <= 0) {
      fieldErrors[`items.${index}.source_grams`] = `Row ${index + 1}: source grams must be greater than 0.`
    }
    if (row.source_touch === null || row.source_touch < 0 || row.source_touch > 100) {
      fieldErrors[`items.${index}.source_touch`] = `Row ${index + 1}: source touch must be between 0 and 100.`
    }
    if (row.target_touch === null || row.target_touch < 0 || row.target_touch > 100) {
      fieldErrors[`items.${index}.target_touch`] = `Row ${index + 1}: target touch must be between 0 and 100.`
    }
    row.alloys.forEach((alloy, alloyIndex) => {
      if (alloy.alloy_item_id === null) {
        fieldErrors[`items.${index}.alloys.${alloyIndex}.alloy_item_id`] =
          `Row ${index + 1}, alloy ${alloyIndex + 1}: select an item.`
      }
      if (alloy.alloy_percentage === null || alloy.alloy_percentage < 0 || alloy.alloy_percentage > 100) {
        fieldErrors[`items.${index}.alloys.${alloyIndex}.alloy_percentage`] =
          `Row ${index + 1}, alloy ${alloyIndex + 1}: percentage must be between 0 and 100.`
      }
      if (alloy.alloy_grams === null || alloy.alloy_grams < 0) {
        fieldErrors[`items.${index}.alloys.${alloyIndex}.alloy_grams`] =
          `Row ${index + 1}, alloy ${alloyIndex + 1}: grams must be 0 or more.`
      }
    })
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
    formError.value = 'You must be signed in to record an item conversion.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    const result = await stockApi.postItemConversion(
      {
        ...form,
        items: form.items.map((i) => ({ ...i, alloys: i.alloys.map((a) => ({ ...a })) })),
      },
      auth.user.user_id,
    )
    toastStore.show(`Item conversion recorded — ${result.length} item(s).`, 'success')
    emit('saved')
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
      formError.value = 'Failed to record item conversion.'
      toastStore.show('Failed to record item conversion.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal
    title="Item Conversion"
    badge="OUT"
    badge-class="bg-red-600"
    max-width="max-w-5xl"
    @close="emit('close')"
  >
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
      <section class="grid gap-3 sm:grid-cols-2">
        <BaseSelect
          id="user_id"
          :model-value="form.user_id"
          label="User"
          required
          size="sm"
          placeholder="Select a user…"
          :options="userOptions"
          :error="fieldErrors.user_id"
          @update:model-value="(v) => (form.user_id = v as number | null)"
        />
        <BaseInput id="added_at" v-model="form.added_at" label="Date (optional, defaults to now)" type="date" size="sm" />
      </section>

      <section class="border-t border-slate-200 pt-4">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-900">Items</h3>
          <BaseButton variant="secondary" type="button" :icon="Plus" @click="addItemRow">Add item</BaseButton>
        </div>
        <p v-if="fieldErrors.items" class="mb-2 text-sm text-red-600">{{ fieldErrors.items }}</p>

        <div v-for="(row, index) in form.items" :key="index" class="mb-3 rounded-lg border border-slate-200 p-3">
          <div class="grid items-end gap-3 sm:grid-cols-2">
            <BaseSelect
              :id="`source_item_${index}`"
              :model-value="row.source_item_id"
              label="Source item"
              size="sm"
              placeholder="Select item…"
              :options="itemOptions"
              :error="fieldErrors[`items.${index}.source_item_id`]"
              @update:model-value="(v) => (row.source_item_id = v as number | null)"
            />
            <BaseSelect
              :id="`target_item_${index}`"
              :model-value="row.target_item_id"
              label="Target item"
              size="sm"
              placeholder="Select item…"
              :options="itemOptions"
              :error="fieldErrors[`items.${index}.target_item_id`]"
              @update:model-value="(v) => (row.target_item_id = v as number | null)"
            />
          </div>

          <div class="mt-2 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <BaseInput
              :id="`source_grams_${index}`"
              :model-value="row.source_grams === null ? '' : String(row.source_grams)"
              label="Source grams"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`items.${index}.source_grams`]"
              @update:model-value="(v) => (row.source_grams = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`source_touch_${index}`"
              :model-value="row.source_touch === null ? '' : String(row.source_touch)"
              label="Source touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`items.${index}.source_touch`]"
              @update:model-value="(v) => (row.source_touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`target_touch_${index}`"
              :model-value="row.target_touch === null ? '' : String(row.target_touch)"
              label="Target touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`items.${index}.target_touch`]"
              @update:model-value="(v) => (row.target_touch = v === '' ? null : Number(v))"
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

          <div class="mt-3 rounded-md border border-slate-100 bg-slate-50 p-2">
            <div class="mb-2 flex items-center justify-between">
              <span class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Alloys (optional)</span>
              <BaseButton variant="secondary" type="button" :icon="Plus" @click="addAlloyRow(row)">
                Add alloy
              </BaseButton>
            </div>

            <div
              v-for="(alloy, alloyIndex) in row.alloys"
              :key="alloyIndex"
              class="mb-2 grid items-end gap-2 sm:grid-cols-[1.3fr_1fr_1fr_auto]"
            >
              <BaseSelect
                :id="`alloy_item_${index}_${alloyIndex}`"
                :model-value="alloy.alloy_item_id"
                label="Alloy item"
                size="sm"
                placeholder="Select item…"
                :options="itemOptions"
                :error="fieldErrors[`items.${index}.alloys.${alloyIndex}.alloy_item_id`]"
                @update:model-value="(v) => (alloy.alloy_item_id = v as number | null)"
              />
              <BaseInput
                :id="`alloy_percentage_${index}_${alloyIndex}`"
                :model-value="alloy.alloy_percentage === null ? '' : String(alloy.alloy_percentage)"
                label="Percentage"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`items.${index}.alloys.${alloyIndex}.alloy_percentage`]"
                @update:model-value="(v) => (alloy.alloy_percentage = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`alloy_grams_${index}_${alloyIndex}`"
                :model-value="alloy.alloy_grams === null ? '' : String(alloy.alloy_grams)"
                label="Grams"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`items.${index}.alloys.${alloyIndex}.alloy_grams`]"
                @update:model-value="(v) => (alloy.alloy_grams = v === '' ? null : Number(v))"
              />
              <button
                type="button"
                class="mb-1 justify-self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                aria-label="Remove alloy"
                @click="removeAlloyRow(row, alloyIndex)"
              >
                <Trash class="h-4 w-4" />
              </button>
            </div>
            <p v-if="row.alloys.length === 0" class="text-xs text-slate-400">No alloys added.</p>
          </div>

          <p class="mt-2 text-xs text-slate-500">
            Converted grams: <span class="font-medium text-slate-700">{{ convertedGramsFor(row).toFixed(4) }}</span>
            <span class="italic"> (server calculates the final values; this is a preview)</span>
          </p>
        </div>
      </section>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">Cancel</BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Recording…' : 'Record item conversion' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
