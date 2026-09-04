<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Trash, Layers } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import MetalPickerModal from '@/components/stock/MetalPickerModal.vue'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { nowDateTimeInputValue, toBackendDateTime } from '@/lib/date'
import { useToastStore } from '@/stores/toast'
import { isMetalItem } from '@/lib/metalItem'
import type { GmsOutItemInput, Item, MetalPickerSelection } from '@/types'

/*
|--------------------------------------------------------------------------
| GMS Out modal — mirrors GmsOutRequest.php
|--------------------------------------------------------------------------
| Replaces GmsOutPanel.vue: GMS Out is a modal action here, not part of
| the inline add-row/shared-Submit grid — see ItemChangeModal's comment
| for why (this one, Item Change, and Item Conversion submit standalone
| instead of queuing rows).
|
| given_by defaults to headId (page context, the logged-in head) same as
| the panel it replaces — given_by is optional server-side but this
| always sends it for consistency with Stock Out's page-context model.
| given_to is the selected user (givenTo prop).
|
| Metal picker (opens when an item named "Metal" is selected) preserved
| exactly from GmsOutPanel — see MetalPickerModal's own comment for the
| full contract.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; headId: number | null; givenTo: number | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

function makeEmptyRow(): GmsOutItemInput {
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
    added_at: nowDateTimeInputValue(),
  }
}

const rows = ref<GmsOutItemInput[]>([makeEmptyRow()])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

function addRow() {
  rows.value.push(makeEmptyRow())
}
function removeRow(index: number) {
  rows.value.splice(index, 1)
  if (metalPickerRowIndex.value === index) metalPickerRowIndex.value = null
}

const metalPickerRowIndex = ref<number | null>(null)

function onItemSelect(row: GmsOutItemInput, index: number, itemId: number | null) {
  row.item_id = itemId
  const item = props.items.find((i) => i.item_id === itemId)
  if (isMetalItem(item)) metalPickerRowIndex.value = index
}

function handleMetalConfirm(selection: MetalPickerSelection[]) {
  const index = metalPickerRowIndex.value
  metalPickerRowIndex.value = null
  if (index === null) return

  const source = rows.value[index]
  if (!source || selection.length === 0) return

  const lotRows: GmsOutItemInput[] = selection.map((lot) => ({
    item_id: source.item_id,
    grams: lot.taken,
    stone: source.stone,
    thread: source.thread,
    wastage: source.wastage,
    hall_mark: lot.touch,
    mtouch: source.mtouch,
    mtouch_wastage: source.mtouch_wastage,
    remarks: source.remarks,
    item_remarks: [source.item_remarks, `Lot #${lot.id} (${lot.party_name})`].filter(Boolean).join(' — '),
    added_at: source.added_at,
  }))
  rows.value.splice(index, 1, ...lotRows)
}

function netGramsFor(row: GmsOutItemInput): number {
  return (row.grams ?? 0) - (row.stone ?? 0) - (row.thread ?? 0)
}
function wastageGramsFor(row: GmsOutItemInput): number {
  return (netGramsFor(row) * (row.wastage ?? 0)) / 100
}
function purityFor(row: GmsOutItemInput): number {
  return ((netGramsFor(row) + wastageGramsFor(row)) * (row.hall_mark ?? 0)) / 100
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
    if (row.hall_mark === null || row.hall_mark < 0 || row.hall_mark > 100) {
      fieldErrors.value[`${index}.hall_mark`] = `Row ${index + 1}: hall mark must be between 0 and 100.`
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
    toast.show('You must be signed in to record a GMS out.', 'error')
    return
  }
  if (props.givenTo === null) {
    toast.show('Select a user (left panel) before recording a GMS Out.', 'error')
    return
  }
  if (!validate()) return

  isSaving.value = true
  try {
    const result = await stockApi.postGmsOut(
      {
        given_by: props.headId,
        given_to: props.givenTo,
        items: rows.value.map((row) => ({
          ...row,
          added_at: toBackendDateTime(row.added_at),
        })),
      },
      props.headId,
    )
    toast.show(`GMS out recorded — ${result.length} item(s).`, 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      if (err.errors) {
        toast.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toast.show(err.message, 'error')
      }
    } else {
      toast.show('Failed to record GMS out.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="GMS Out" badge="OUT" badge-class="bg-red-600" max-width="max-w-5xl" @close="emit('close')">
    <p v-if="givenTo === null" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      Select a user in the left panel before recording a GMS Out.
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
                @update:model-value="(v) => onItemSelect(row, index, v as number | null)"
              />
              <button
                v-if="isMetalItem(items.find((i) => i.item_id === row.item_id))"
                type="button"
                class="mt-1 flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700"
                @click="metalPickerRowIndex = index"
              >
                <Layers class="h-3 w-3" /> Pick lots…
              </button>
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
              label="Waste %"
              type="number"
              step="0.001"
              size="sm"
              @update:model-value="(v) => (row.wastage = v === '' ? null : Number(v))"
            />
          </div>

          <div class="mt-2 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <BaseInput
              :id="`hall_mark_${index}`"
              :model-value="row.hall_mark === null ? '' : String(row.hall_mark)"
              label="Hall Mark"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`${index}.hall_mark`]"
              @update:model-value="(v) => (row.hall_mark = v === '' ? null : Number(v))"
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
              label="M.Touch Waste"
              type="number"
              step="0.001"
              size="sm"
              @update:model-value="(v) => (row.mtouch_wastage = v === '' ? null : Number(v))"
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

          <p class="mt-2 text-xs text-slate-500">
            Computed purity: <span class="font-medium text-slate-700">{{ purityFor(row).toFixed(4) }}</span>
            <span class="italic"> (server calculates the final values; this is a preview)</span>
          </p>
        </div>
      </section>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">Cancel</BaseButton>
        <BaseButton type="submit" :disabled="isSaving">{{ isSaving ? 'Recording…' : 'Record GMS out' }}</BaseButton>
      </div>
    </form>

    <MetalPickerModal
      v-if="metalPickerRowIndex !== null && headId !== null"
      :item-id="rows[metalPickerRowIndex]!.item_id!"
      :user-id="headId"
      :required="rows[metalPickerRowIndex]!.grams ?? 0"
      @close="metalPickerRowIndex = null"
      @confirm="handleMetalConfirm"
    />
  </BaseModal>
</template>
