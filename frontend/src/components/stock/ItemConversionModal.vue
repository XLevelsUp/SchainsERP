<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Trash } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { nowDateTimeInputValue, toBackendDateTime } from '@/lib/date'
import { useToastStore } from '@/stores/toast'
import type { Item, ItemConversionAlloyInput, ItemConversionItemInput } from '@/types'

/*
|--------------------------------------------------------------------------
| Item Conversion modal — mirrors ItemConversionRequest.php
|--------------------------------------------------------------------------
| Replaces ItemConversionPanel.vue: Item Conversion is a modal action
| here, not part of the inline add-row/shared-Submit grid — see
| ItemChangeModal's comment for why (this one, Item Change, and GMS Out
| submit standalone instead of queuing rows).
|
| Scoped to `userId` (selectedUserId from the page's UserPickerPanel),
| same as the panel it replaces. actingUserId sent as userId too
| (X-User-ID header) — not changed here.
|
| Unlike Item Change, stock_in_id is nullable in ItemConversionRequest.php
| so it's always sent null with no backend-gap warning needed.
|
| Alloys: the grid version only exposed one alloy per row for space, but
| ItemConversionRequest accepts an array — this modal has room, so it
| exposes full add/remove alloy rows per item (matches the original
| modal this replaces).
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; userId: number | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

function makeEmptyAlloy(): ItemConversionAlloyInput {
  return { alloy_item_id: null, alloy_percentage: null, alloy_grams: null }
}
function makeEmptyRow(): ItemConversionItemInput {
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
    added_at: nowDateTimeInputValue(),
  }
}

const rows = ref<ItemConversionItemInput[]>([makeEmptyRow()])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

function addRow() {
  rows.value.push(makeEmptyRow())
}
function removeRow(index: number) {
  rows.value.splice(index, 1)
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
  fieldErrors.value = {}

  if (rows.value.length === 0) {
    fieldErrors.value.items = 'Add at least one item.'
  }

  rows.value.forEach((row, index) => {
    if (row.source_item_id === null) {
      fieldErrors.value[`${index}.source_item_id`] = `Row ${index + 1}: select the source item.`
    }
    if (row.target_item_id === null) {
      fieldErrors.value[`${index}.target_item_id`] = `Row ${index + 1}: select the target item.`
    }
    if (row.source_item_id !== null && row.source_item_id === row.target_item_id) {
      fieldErrors.value[`${index}.target_item_id`] = `Row ${index + 1}: target item must differ from source item.`
    }
    if (row.source_grams === null || row.source_grams <= 0) {
      fieldErrors.value[`${index}.source_grams`] = `Row ${index + 1}: source grams must be greater than 0.`
    }
    if (row.source_touch === null || row.source_touch < 0 || row.source_touch > 100) {
      fieldErrors.value[`${index}.source_touch`] = `Row ${index + 1}: source touch must be between 0 and 100.`
    }
    if (row.target_touch === null || row.target_touch < 0 || row.target_touch > 100) {
      fieldErrors.value[`${index}.target_touch`] = `Row ${index + 1}: target touch must be between 0 and 100.`
    }
    row.alloys.forEach((alloy, alloyIndex) => {
      if (alloy.alloy_item_id === null) {
        fieldErrors.value[`${index}.alloys.${alloyIndex}.alloy_item_id`] =
          `Row ${index + 1}, alloy ${alloyIndex + 1}: select an item.`
      }
      if (alloy.alloy_percentage === null || alloy.alloy_percentage < 0 || alloy.alloy_percentage > 100) {
        fieldErrors.value[`${index}.alloys.${alloyIndex}.alloy_percentage`] =
          `Row ${index + 1}, alloy ${alloyIndex + 1}: percentage must be between 0 and 100.`
      }
      if (alloy.alloy_grams === null || alloy.alloy_grams < 0) {
        fieldErrors.value[`${index}.alloys.${alloyIndex}.alloy_grams`] =
          `Row ${index + 1}, alloy ${alloyIndex + 1}: grams must be 0 or more.`
      }
    })
  })

  const firstError = Object.values(fieldErrors.value)[0]
  if (firstError) {
    toast.show(firstError, 'error')
    return false
  }
  return true
}

async function handleSubmit() {
  if (props.userId === null) {
    toast.show('Select a user (left panel) before recording an Item Conversion.', 'error')
    return
  }
  if (!validate()) return

  isSaving.value = true
  try {
    const result = await stockApi.postItemConversion(
      {
        user_id: props.userId,
        items: rows.value.map((row) => ({
          ...row,
          added_at: toBackendDateTime(row.added_at),
          alloys: row.alloys.filter((a) => a.alloy_item_id !== null),
        })),
      },
      props.userId,
    )
    toast.show(`Item conversion recorded — ${result.length} item(s).`, 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      if (err.errors) {
        toast.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toast.show(err.message, 'error')
      }
    } else {
      toast.show('Failed to record item conversion.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="Item Conversion" badge="OUT" badge-class="bg-red-600" max-width="max-w-5xl" @close="emit('close')">
    <p v-if="userId === null" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      Select a user in the left panel before recording an Item Conversion.
    </p>

    <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
      <section>
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-900">Items</h3>
          <BaseButton variant="secondary" type="button" :icon="Plus" @click="addRow">Add item</BaseButton>
        </div>
        <p v-if="fieldErrors.items" class="mb-2 text-sm text-red-600">{{ fieldErrors.items }}</p>

        <div v-for="(row, index) in rows" :key="index" class="mb-3 rounded-lg border border-slate-200 p-3">
          <div class="grid items-end gap-3 sm:grid-cols-3">
            <BaseInput :id="`added_at_${index}`" v-model="row.added_at" label="Date-Time" type="datetime-local" size="sm" />
            <BaseSelect
              :id="`source_item_${index}`"
              :model-value="row.source_item_id"
              label="Source item"
              size="sm"
              placeholder="Select item…"
              :options="itemOptions"
              :error="fieldErrors[`${index}.source_item_id`]"
              @update:model-value="(v) => (row.source_item_id = v as number | null)"
            />
            <BaseSelect
              :id="`target_item_${index}`"
              :model-value="row.target_item_id"
              label="Target item"
              size="sm"
              placeholder="Select item…"
              :options="itemOptions"
              :error="fieldErrors[`${index}.target_item_id`]"
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
              :error="fieldErrors[`${index}.source_grams`]"
              @update:model-value="(v) => (row.source_grams = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`source_touch_${index}`"
              :model-value="row.source_touch === null ? '' : String(row.source_touch)"
              label="Source touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`${index}.source_touch`]"
              @update:model-value="(v) => (row.source_touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`target_touch_${index}`"
              :model-value="row.target_touch === null ? '' : String(row.target_touch)"
              label="Target touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`${index}.target_touch`]"
              @update:model-value="(v) => (row.target_touch = v === '' ? null : Number(v))"
            />
            <button
              type="button"
              class="mb-1 justify-self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
              aria-label="Remove item"
              :disabled="rows.length === 1"
              @click="removeRow(index)"
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
                :error="fieldErrors[`${index}.alloys.${alloyIndex}.alloy_item_id`]"
                @update:model-value="(v) => (alloy.alloy_item_id = v as number | null)"
              />
              <BaseInput
                :id="`alloy_percentage_${index}_${alloyIndex}`"
                :model-value="alloy.alloy_percentage === null ? '' : String(alloy.alloy_percentage)"
                label="Percentage"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`${index}.alloys.${alloyIndex}.alloy_percentage`]"
                @update:model-value="(v) => (alloy.alloy_percentage = v === '' ? null : Number(v))"
              />
              <BaseInput
                :id="`alloy_grams_${index}_${alloyIndex}`"
                :model-value="alloy.alloy_grams === null ? '' : String(alloy.alloy_grams)"
                label="Grams"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`${index}.alloys.${alloyIndex}.alloy_grams`]"
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
