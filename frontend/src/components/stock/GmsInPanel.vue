<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Minus, Layers } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import MetalPickerModal from '@/components/stock/MetalPickerModal.vue'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { nowDateTimeInputValue, toBackendDateTime } from '@/lib/date'
import { useToastStore } from '@/stores/toast'
import { isMetalItem } from '@/lib/metalItem'
import type { GmsInItemInput, Item, MetalPickerSelection } from '@/types'

/*
|--------------------------------------------------------------------------
| GMS In — inline multi-row entry, not a modal
|--------------------------------------------------------------------------
| Mirrors GmsOutPanel.vue (see its comment for the full rationale) —
| replaces GmsInModal.vue. Unlike GMS Out, both given_by and given_to are
| required, matching the old modal: given_by = whichever user is selected
| in UserPickerPanel (givenBy prop), given_to = the logged-in head
| (headId prop) — same direction as Stock In.
|
| Metal picker (see StockOutPanel's comment for the full contract) is
| scoped to givenBy here, same direction as Stock In.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; headId: number | null; givenBy: number | null }>()
const emit = defineEmits<{ saved: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

const rows = ref<GmsInItemInput[]>([])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

function makeEmptyRow(): GmsInItemInput {
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

function addRow() {
  if (props.headId === null) {
    toast.show('You must be signed in to record a GMS in.', 'error')
    return
  }
  if (props.givenBy === null) {
    toast.show('Select a user (left panel) before adding a GMS In row.', 'error')
    return
  }
  rows.value.push(makeEmptyRow())
}

function removeRow(index: number) {
  rows.value.splice(index, 1)
  metalPickerRowIndex.value = null
}

const metalPickerRowIndex = ref<number | null>(null)

function onItemSelect(row: GmsInItemInput, index: number, itemId: number | null) {
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

  const lotRows: GmsInItemInput[] = selection.map((lot) => ({
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

function netGramsFor(row: GmsInItemInput): number {
  return (row.grams ?? 0) - (row.stone ?? 0) - (row.thread ?? 0)
}
function wastageGramsFor(row: GmsInItemInput): number {
  return (netGramsFor(row) * (row.wastage ?? 0)) / 100
}
function purityFor(row: GmsInItemInput): number {
  return ((netGramsFor(row) + wastageGramsFor(row)) * (row.hall_mark ?? 0)) / 100
}

const totals = computed(() => {
  let grams = 0
  let purity = 0
  let wastage = 0
  for (const row of rows.value) {
    grams += row.grams ?? 0
    purity += purityFor(row)
    wastage += wastageGramsFor(row)
  }
  return { grams, purity, wastage }
})

function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

function validate(): boolean {
  fieldErrors.value = {}
  if (rows.value.length === 0) return false

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

function clear() {
  rows.value = []
  fieldErrors.value = {}
  metalPickerRowIndex.value = null
}

function hasRows() {
  return rows.value.length > 0
}

async function submit(): Promise<boolean> {
  if (!validate()) return false
  if (props.headId === null || props.givenBy === null) return false

  isSaving.value = true
  try {
    const result = await stockApi.postGmsIn(
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
    toast.show(`GMS in recorded — ${result.length} item(s).`, 'success')
    clear()
    emit('saved')
    return true
  } catch (err) {
    if (err instanceof ApiError) {
      if (err.errors) {
        toast.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toast.show(err.message, 'error')
      }
    } else {
      toast.show('Failed to record GMS in.', 'error')
    }
    return false
  } finally {
    isSaving.value = false
  }
}

defineExpose({ addRow, submit, clear, hasRows, totals })
</script>

<template>
  <BaseCard v-if="rows.length > 0" :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2">
      <h2 class="text-sm font-semibold text-slate-900">GMS In</h2>
      <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">IN</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[880px] border-collapse text-sm">
        <thead>
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <th class="px-2 py-1.5 text-left">Date-Time</th>
            <th class="px-2 py-1.5 text-left">Item</th>
            <th class="px-2 py-1.5 text-left">Grams</th>
            <th class="px-2 py-1.5 text-left">Stone</th>
            <th class="px-2 py-1.5 text-left">Thread</th>
            <th class="px-2 py-1.5 text-left">Waste %</th>
            <th class="px-2 py-1.5 text-left">Hall Mark</th>
            <th class="px-2 py-1.5 text-left">M.Touch</th>
            <th class="px-2 py-1.5 text-left">M.Touch Waste</th>
            <th class="px-2 py-1.5 text-left">Purity</th>
            <th class="px-2 py-1.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(row, index) in rows" :key="index" class="align-top">
            <td class="p-1.5">
              <BaseInput v-model="row.added_at" type="datetime-local" size="sm" />
            </td>
            <td class="min-w-[160px] p-1.5">
              <BaseSelect
                :model-value="row.item_id"
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
              <BaseInput v-model="row.remarks" size="sm" placeholder="Remarks…" class="mt-1" />
              <BaseInput v-model="row.item_remarks" size="sm" placeholder="Item remarks…" class="mt-1" />
            </td>
            <td class="w-20 p-1.5">
              <BaseInput
                :model-value="row.grams === null ? '' : String(row.grams)"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`${index}.grams`]"
                @update:model-value="(v) => (row.grams = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-16 p-1.5">
              <BaseInput
                :model-value="row.stone === null ? '' : String(row.stone)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.stone = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-16 p-1.5">
              <BaseInput
                :model-value="row.thread === null ? '' : String(row.thread)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.thread = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-16 p-1.5">
              <BaseInput
                :model-value="row.wastage === null ? '' : String(row.wastage)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.wastage = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5">
              <BaseInput
                :model-value="row.hall_mark === null ? '' : String(row.hall_mark)"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`${index}.hall_mark`]"
                @update:model-value="(v) => (row.hall_mark = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5">
              <BaseInput
                :model-value="row.mtouch === null ? '' : String(row.mtouch)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.mtouch = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5">
              <BaseInput
                :model-value="row.mtouch_wastage === null ? '' : String(row.mtouch_wastage)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.mtouch_wastage = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5 pt-3 tabular-nums text-slate-600">{{ formatNumber(purityFor(row)) }}</td>
            <td class="p-1.5 pt-3">
              <div class="flex flex-col gap-1">
                <button
                  type="button"
                  class="rounded p-0.5 text-emerald-600 hover:bg-emerald-50"
                  aria-label="Add another row"
                  @click="addRow"
                >
                  <Plus class="h-4 w-4" />
                </button>
                <button
                  type="button"
                  class="rounded p-0.5 text-red-600 hover:bg-red-50"
                  aria-label="Remove row"
                  @click="removeRow(index)"
                >
                  <Minus class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex items-center gap-4 border-t border-slate-200 px-3 py-2 text-xs">
      <span class="text-slate-600">
        Grams: <span class="font-semibold text-slate-900 tabular-nums">{{ formatNumber(totals.grams) }}</span>
      </span>
      <span class="text-slate-600">
        Purity: <span class="font-semibold text-amber-700 tabular-nums">{{ formatNumber(totals.purity) }}</span>
      </span>
      <span class="text-slate-600">
        Wastage: <span class="font-semibold text-red-700 tabular-nums">{{ formatNumber(totals.wastage) }}</span>
      </span>
      <span v-if="isSaving" class="italic text-slate-400">Submitting…</span>
    </div>

    <MetalPickerModal
      v-if="metalPickerRowIndex !== null && givenBy !== null"
      :item-id="rows[metalPickerRowIndex]!.item_id!"
      :user-id="givenBy"
      :required="rows[metalPickerRowIndex]!.grams ?? 0"
      @close="metalPickerRowIndex = null"
      @confirm="handleMetalConfirm"
    />
  </BaseCard>
</template>
