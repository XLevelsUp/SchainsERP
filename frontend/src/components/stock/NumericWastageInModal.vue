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
import type { Item, NumericWastageInItemInput } from '@/types'

/*
|--------------------------------------------------------------------------
| Numeric Wastage In modal — mirrors NumericWastageInRequest.php
|--------------------------------------------------------------------------
| Replaces NumericWastageInPanel.vue: requested to match the legacy "Add
| Numeric Wastages" dialog layout (Item/Grams/Touch/Pcs/Wastage/WValue/
| Total table, Add + Close/Save) — same pattern as ItemChangeModal/
| ItemConversionModal/GmsOutModal/GmsInModal (see StockManagementView.vue's
| comment for why these are modals and Stock Out/In stay inline).
|
| Both given_by and given_to are required, same direction as Stock In:
| given_by = givenBy prop (selectedUserId), given_to = headId. Scoped via
| props from the page's UserPickerPanel, not an internal picker — same
| reasoning as GmsInModal's comment.
|
| No "Wastage" dropdown, unlike the legacy screenshot (which shows a
| disabled/placeholder "Waste" select above the Wastage input) — carried
| forward from NumericWastageInPanel.vue: there's no API for wastage-type
| presets, so it's a single numeric "per pc" input, same as before.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; headId: number | null; givenBy: number | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

function makeEmptyRow(): NumericWastageInItemInput {
  return {
    item_id: null,
    grams: null,
    touch: 100,
    no_of_pcs: null,
    amount_pcs: 0,
    waste_total: 0,
    remarks: '',
    item_remarks: '',
    added_at: nowDateTimeInputValue(),
  }
}

const rows = ref<NumericWastageInItemInput[]>([makeEmptyRow()])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

function addRow() {
  rows.value.push(makeEmptyRow())
}
function removeRow(index: number) {
  rows.value.splice(index, 1)
}

function onItemSelect(row: NumericWastageInItemInput, itemId: number | null) {
  row.item_id = itemId
  const item = props.items.find((i) => i.item_id === itemId)
  if (item) row.touch = item.default_touch
}

function wastageTotalFor(row: NumericWastageInItemInput): number {
  return (row.no_of_pcs ?? 0) * (row.waste_total ?? 0)
}
function purityFor(row: NumericWastageInItemInput): number {
  return (((row.grams ?? 0) + wastageTotalFor(row)) * (row.touch ?? 0)) / 100
}

function validate(): boolean {
  fieldErrors.value = {}
  if (rows.value.length === 0) {
    fieldErrors.value.items = 'Add at least one item.'
  }

  rows.value.forEach((row, index) => {
    if (row.item_id === null) fieldErrors.value[`${index}.item_id`] = `Row ${index + 1}: select an item.`
    if (row.grams === null || row.grams <= 0) {
      fieldErrors.value[`${index}.grams`] = `Row ${index + 1}: grams must be greater than 0.`
    }
    if (row.no_of_pcs === null || row.no_of_pcs <= 0) {
      fieldErrors.value[`${index}.no_of_pcs`] = `Row ${index + 1}: number of pieces must be greater than 0.`
    }
  })

  const firstError = Object.values(fieldErrors.value)[0]
  if (firstError) {
    toast.show(firstError, 'error')
    return false
  }
  return true
}

async function handleSubmit() {
  if (props.headId === null) {
    toast.show('You must be signed in to record a numeric wastage in.', 'error')
    return
  }
  if (props.givenBy === null) {
    toast.show('Select a user (left panel) before recording a Numeric Wastage In.', 'error')
    return
  }
  if (!validate()) return

  isSaving.value = true
  try {
    const result = await stockApi.postNumericWasteIn(
      {
        given_by: props.givenBy,
        given_to: props.headId,
        items: rows.value.map((row) => ({
          ...row,
          added_at: toBackendDateTime(row.added_at),
        })),
      },
      props.headId,
    )
    toast.show(`Numeric wastage in recorded — ${result.length} item(s).`, 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      if (err.errors) {
        toast.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toast.show(err.message, 'error')
      }
    } else {
      toast.show('Failed to record numeric wastage in.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}
</script>

<template>
  <BaseModal title="Numeric Wastage In" badge="IN" badge-class="bg-emerald-600" max-width="max-w-4xl" @close="emit('close')">
    <p v-if="givenBy === null" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      Select a user in the left panel before recording a Numeric Wastage In.
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
            <div class="sm:col-span-2">
              <BaseSelect
                :id="`item_${index}`"
                :model-value="row.item_id"
                label="Item"
                size="sm"
                placeholder="Select an item…"
                :options="itemOptions"
                :error="fieldErrors[`${index}.item_id`]"
                @update:model-value="(v) => onItemSelect(row, v as number | null)"
              />
            </div>
          </div>

          <div class="mt-2 grid items-end gap-3 sm:grid-cols-4">
            <BaseInput
              :id="`grams_${index}`"
              :model-value="row.grams === null ? '' : String(row.grams)"
              label="Grams"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`${index}.grams`]"
              @update:model-value="(v) => (row.grams = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`touch_${index}`"
              :model-value="row.touch === null ? '' : String(row.touch)"
              label="Touch"
              type="number"
              step="0.001"
              size="sm"
              @update:model-value="(v) => (row.touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`no_of_pcs_${index}`"
              :model-value="row.no_of_pcs === null ? '' : String(row.no_of_pcs)"
              label="No. of Pcs"
              type="number"
              step="1"
              size="sm"
              :error="fieldErrors[`${index}.no_of_pcs`]"
              @update:model-value="(v) => (row.no_of_pcs = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`amount_pcs_${index}`"
              :model-value="row.amount_pcs === null ? '' : String(row.amount_pcs)"
              label="Amount/Pc"
              type="number"
              step="0.001"
              size="sm"
              @update:model-value="(v) => (row.amount_pcs = v === '' ? null : Number(v))"
            />
          </div>

          <div class="mt-2 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <BaseInput
              :id="`waste_total_${index}`"
              :model-value="row.waste_total === null ? '' : String(row.waste_total)"
              label="Wastage (per pc)"
              type="number"
              step="0.001"
              size="sm"
              @update:model-value="(v) => (row.waste_total = v === '' ? null : Number(v))"
            />
            <div>
              <p class="mb-1 block text-xs font-medium text-slate-600">WValue</p>
              <p class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm tabular-nums text-slate-600">
                {{ formatNumber(wastageTotalFor(row)) }}
              </p>
            </div>
            <div>
              <p class="mb-1 block text-xs font-medium text-slate-600">Total</p>
              <p class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm tabular-nums text-slate-600">
                {{ formatNumber(purityFor(row)) }}
              </p>
            </div>
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
        </div>
      </section>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">Cancel</BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Recording…' : 'Record numeric wastage in' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
