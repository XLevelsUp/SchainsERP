<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Minus } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import DirectionTag from '@/components/ui/DirectionTag.vue'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { nowDateTimeInputValue, toBackendDateTime } from '@/lib/date'
import { useToastStore } from '@/stores/toast'
import type { Item, NumericWastageOutItemInput } from '@/types'

/*
|--------------------------------------------------------------------------
| Numeric Wastage Out — inline multi-row entry, not a modal
|--------------------------------------------------------------------------
| Replaces NumericWastageOutModal.vue. Same shared-Submit orchestration
| contract as StockOutPanel.vue (see its comment) — stacks below
| StockOutPanel in the OUT column (Item Change/Item Conversion/GMS Out
| are modals now, not inline panels — see StockManagementView.vue's
| comment).
|
| given_by/given_to same direction as Stock Out (headId/givenTo).
| Amount (no. of pcs × amount per pc) is recorded as a cash payout to
| "given_to" automatically by the backend.
|
| No "Wastage" dropdown, unlike the legacy screen — there's no API to
| list wastage_details from (same reasoning as StockOutPanel's waste_id).
| "Wastage per piece" is the real plain-number input that drives it.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  items: Item[]
  headId: number | null
  givenTo: number | null
  reloadItems?: () => unknown
}>()
const emit = defineEmits<{ saved: []; missingUser: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

const rows = ref<NumericWastageOutItemInput[]>([])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

function makeEmptyRow(): NumericWastageOutItemInput {
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

// Deliberately no user/sign-in gate here (2026-10 request) — see
// StockOutPanel's comment on its own addRow for the full rationale.
function addRow() {
  rows.value.push(makeEmptyRow())
}

function removeRow(index: number) {
  rows.value.splice(index, 1)
}

function onItemSelect(row: NumericWastageOutItemInput, itemId: number | null) {
  row.item_id = itemId
  const item = props.items.find((i) => i.item_id === itemId)
  if (item) row.touch = item.default_touch
}

function wastageTotalFor(row: NumericWastageOutItemInput): number {
  return (row.no_of_pcs ?? 0) * (row.waste_total ?? 0)
}
function purityFor(row: NumericWastageOutItemInput): number {
  return (((row.grams ?? 0) + wastageTotalFor(row)) * (row.touch ?? 0)) / 100
}

const totals = computed(() => {
  let grams = 0
  let purity = 0
  let wastage = 0
  for (const row of rows.value) {
    grams += row.grams ?? 0
    purity += purityFor(row)
    wastage += wastageTotalFor(row)
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

function clear() {
  rows.value = []
  fieldErrors.value = {}
}

function hasRows() {
  return rows.value.length > 0
}

async function submit(): Promise<boolean> {
  if (!validate()) return false
  if (props.headId === null) {
    toast.show('You must be signed in to record a numeric wastage out.', 'error')
    return false
  }
  if (props.givenTo === null) {
    emit('missingUser')
    return false
  }

  isSaving.value = true
  try {
    const result = await stockApi.postNumericWasteOut(
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
    toast.show(`Numeric wastage out recorded — ${result.length} item(s).`, 'success')
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
      toast.show('Failed to record numeric wastage out.', 'error')
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
      <h2 class="text-sm font-semibold text-slate-900">Numeric Wastage Out</h2>
      <DirectionTag direction="out" variant="pill" />
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[800px] border-collapse text-sm">
        <thead>
          <tr
            class="border-b border-slate-100 font-figure text-[11px] font-semibold tracking-[0.06em] text-slate-500 uppercase"
          >
            <th class="px-2 py-1.5 text-left">Date-time</th>
            <th class="px-2 py-1.5 text-left">Item</th>
            <th class="px-2 py-1.5 text-right">Grams</th>
            <th class="px-2 py-1.5 text-right">Touch %</th>
            <th class="px-2 py-1.5 text-right">Pcs · Amt/pc</th>
            <th class="px-2 py-1.5 text-right">Waste/pc g</th>
            <th class="px-2 py-1.5 text-right">Waste g</th>
            <th class="px-2 py-1.5 text-right">Total g</th>
            <th class="px-2 py-1.5"><span class="sr-only">Row actions</span></th>
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
                :reload="reloadItems"
                :error="fieldErrors[`${index}.item_id`]"
                @update:model-value="(v) => onItemSelect(row, v as number | null)"
              />
              <BaseInput v-model="row.remarks" size="sm" placeholder="Remarks…" class="mt-1" />
              <BaseInput v-model="row.item_remarks" size="sm" placeholder="Item remarks…" class="mt-1" />
            </td>
            <td class="w-20 min-w-[5.5rem] p-1.5">
              <BaseInput
                :model-value="row.grams === null ? '' : String(row.grams)"
                type="number"
                step="0.001"
                size="sm"
                class="no-spin text-right font-figure tabular-nums"
                aria-label="Grams"
                :error="fieldErrors[`${index}.grams`]"
                @update:model-value="(v) => (row.grams = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 min-w-[5.5rem] p-1.5">
              <BaseInput
                :model-value="row.touch === null ? '' : String(row.touch)"
                type="number"
                step="0.001"
                size="sm"
                class="no-spin text-right font-figure tabular-nums"
                aria-label="Touch %"
                @update:model-value="(v) => (row.touch = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-24 min-w-[6rem] p-1.5">
              <BaseInput
                :model-value="row.no_of_pcs === null ? '' : String(row.no_of_pcs)"
                type="number"
                step="1"
                size="sm"
                placeholder="No. of pcs"
                class="no-spin text-right font-figure tabular-nums"
                aria-label="Number of pieces"
                :error="fieldErrors[`${index}.no_of_pcs`]"
                @update:model-value="(v) => (row.no_of_pcs = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="row.amount_pcs === null ? '' : String(row.amount_pcs)"
                type="number"
                step="0.001"
                size="sm"
                placeholder="Amount/pc"
                class="no-spin mt-1 text-right font-figure tabular-nums"
                aria-label="Amount per piece"
                @update:model-value="(v) => (row.amount_pcs = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 min-w-[5.5rem] p-1.5">
              <BaseInput
                :model-value="row.waste_total === null ? '' : String(row.waste_total)"
                type="number"
                step="0.001"
                size="sm"
                placeholder="Per pc"
                class="no-spin text-right font-figure tabular-nums"
                aria-label="Waste per piece, grams"
                @update:model-value="(v) => (row.waste_total = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5 pt-3 text-right font-figure tabular-nums text-slate-600">
              {{ formatNumber(wastageTotalFor(row)) }}
            </td>
            <td class="w-20 p-1.5 pt-3 text-right font-figure font-medium tabular-nums text-gold-700">
              {{ formatNumber(purityFor(row)) }}
            </td>
            <td class="p-1.5 pt-2">
              <div class="flex flex-col gap-0.5">
                <button
                  type="button"
                  class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-ink-900"
                  aria-label="Add another row"
                  title="Add another row"
                  @click="addRow"
                >
                  <Plus class="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  class="rounded p-1 text-slate-400 hover:bg-outflow-50 hover:text-outflow-700"
                  aria-label="Remove row"
                  title="Remove row"
                  @click="removeRow(index)"
                >
                  <Minus class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex items-center gap-5 border-t border-slate-200 bg-slate-50/60 px-3 py-2 text-xs">
      <span class="text-slate-500">
        Grams <span class="ml-1 font-figure text-[13px] font-semibold text-slate-900 tabular-nums">{{ formatNumber(totals.grams) }}</span>
      </span>
      <span class="text-slate-500">
        Purity <span class="ml-1 font-figure text-[13px] font-semibold text-gold-700 tabular-nums">{{ formatNumber(totals.purity) }}</span>
      </span>
      <span class="text-slate-500">
        Wastage <span class="ml-1 font-figure text-[13px] font-semibold text-slate-700 tabular-nums">{{ formatNumber(totals.wastage) }}</span>
      </span>
      <span v-if="isSaving" class="ml-auto text-slate-400 italic">Submitting…</span>
    </div>
  </BaseCard>
</template>
