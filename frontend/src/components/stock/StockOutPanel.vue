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
import type { Item, MetalPickerSelection, StockOutItemInput } from '@/types'

/*
|--------------------------------------------------------------------------
| Stock Out — inline multi-row entry, not a modal
|--------------------------------------------------------------------------
| Replaces StockOutModal.vue for the "New Out" action: the legacy screen
| never opens a dialog for this — clicking "NEW OUT" (or a row's own "+")
| appends one more row to a compact grid that lives directly on the page,
| and a single Submit posts every accumulated row in one POST /stock/out
| call (the `items` array already supported multiple rows even in the old
| modal; only the shell/layout changed here, not the payload/validation/
| calculations).
|
| Dense table layout (not label+input blocks) so this sits side-by-side
| with StockInPanel per the legacy screen's two-column OUT|IN layout.
|
| given_by/given_to are NOT per-row fields (unlike the old modal, which
| let you pick any two users) — they come from page-level context here,
| matching CashManagementView's existing Head/User gate pattern:
| given_by = the logged-in head (headId prop), given_to = whichever user
| is selected in UserPickerPanel (givenTo prop). addRow() refuses to add a
| row until both are set.
|
| waste_id has no picker for the same reason as before (no API to read
| wastage_details from) — waste_total (%) is the real input; waste_value/
| purity shown per row are a client-side preview only, the backend
| derives the authoritative values (StockInventoryService::createStockOut).
|
| Not implemented — no backend contract found for these legacy-screen
| elements, flagging rather than guessing: the "REPLY ID" badge (tied to
| REPLY OUT, which itself has no endpoint yet) and the "Order"/"View"
| per-row buttons.
|
| Selecting the item literally named "Metal" opens MetalPickerModal
| (GET /stock-details/available-metals, API doc #22) scoped to headId —
| that's the *source* of this OUT transaction's stock, matching the
| endpoint's own query (lots previously given TO the user_id you pass).
| Saving there replaces the triggering row with one row per lot taken
| (touch comes from the lot; waste_total/remarks/added_at carry over from
| the row that triggered it). "Pick lots…" reopens it for an
| already-metal row without re-touching the Item dropdown.
|
| No Submit/Clear buttons here — rows just sit here ("stored in session")
| until the page-level shared Submit button (StockManagementView) fires
| every panel with pending rows at once, each still posting to its own
| endpoint separately (not merged into one request). addRow/submit/clear/
| hasRows/totals are exposed for that parent orchestration; submit()
| resolves false (not thrown) for "nothing to do" or a failed request, so
| the parent can tell real successes from no-ops without try/catch.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; headId: number | null; givenTo: number | null }>()
const emit = defineEmits<{ saved: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

const rows = ref<StockOutItemInput[]>([])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

function makeEmptyRow(): StockOutItemInput {
  return {
    item_id: null,
    grams: null,
    touch: 100,
    waste_total: 0,
    remarks: '',
    item_remarks: '',
    added_at: nowDateTimeInputValue(),
  }
}

function addRow() {
  if (props.headId === null) {
    toast.show('You must be signed in to record a stock out.', 'error')
    return
  }
  if (props.givenTo === null) {
    toast.show('Select a user (left panel) before adding a Stock Out row.', 'error')
    return
  }
  rows.value.push(makeEmptyRow())
}

function removeRow(index: number) {
  rows.value.splice(index, 1)
  metalPickerRowIndex.value = null
}

const metalPickerRowIndex = ref<number | null>(null)

function onItemSelect(row: StockOutItemInput, index: number, itemId: number | null) {
  row.item_id = itemId
  const item = props.items.find((i) => i.item_id === itemId)
  if (item) row.touch = item.default_touch
  if (isMetalItem(item)) metalPickerRowIndex.value = index
}

function handleMetalConfirm(selection: MetalPickerSelection[]) {
  const index = metalPickerRowIndex.value
  metalPickerRowIndex.value = null
  if (index === null) return

  const source = rows.value[index]
  if (!source || selection.length === 0) return

  const lotRows: StockOutItemInput[] = selection.map((lot) => ({
    item_id: source.item_id,
    grams: lot.taken,
    touch: lot.touch,
    waste_total: source.waste_total,
    remarks: source.remarks,
    item_remarks: [source.item_remarks, `Lot #${lot.id} (${lot.party_name})`].filter(Boolean).join(' — '),
    added_at: source.added_at,
  }))
  rows.value.splice(index, 1, ...lotRows)
}

function wasteValueFor(row: StockOutItemInput): number {
  return ((row.grams ?? 0) * (row.waste_total ?? 0)) / 100
}
function purityFor(row: StockOutItemInput): number {
  return ((row.grams ?? 0) * (row.touch ?? 0)) / 100 + wasteValueFor(row)
}

const totals = computed(() => {
  let grams = 0
  let purity = 0
  let wastage = 0
  for (const row of rows.value) {
    grams += row.grams ?? 0
    purity += purityFor(row)
    wastage += wasteValueFor(row)
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
    if (row.touch === null || row.touch < 0 || row.touch > 100) {
      fieldErrors.value[`${index}.touch`] = `Row ${index + 1}: touch must be between 0 and 100.`
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
  if (props.headId === null || props.givenTo === null) return false

  isSaving.value = true
  try {
    const result = await stockApi.postStockOut(
      {
        given_by: props.headId,
        given_to: props.givenTo,
        retailer_id: null,
        items: rows.value.map((row) => ({
          ...row,
          added_at: toBackendDateTime(row.added_at),
        })),
      },
      props.headId,
    )
    toast.show(`Stock out recorded — bill #${result.bill_id}.`, 'success')
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
      toast.show('Failed to record stock out.', 'error')
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
      <h2 class="text-sm font-semibold text-slate-900">Stock Out</h2>
      <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">OUT</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[720px] border-collapse text-sm">
        <thead>
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <th class="px-2 py-1.5 text-left">Date-Time</th>
            <th class="px-2 py-1.5 text-left">Item</th>
            <th class="px-2 py-1.5 text-left">Grams</th>
            <th class="px-2 py-1.5 text-left">Touch</th>
            <th class="px-2 py-1.5 text-left">Waste %</th>
            <th class="px-2 py-1.5 text-left">WValue</th>
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
            <td class="w-20 p-1.5">
              <BaseInput
                :model-value="row.touch === null ? '' : String(row.touch)"
                type="number"
                step="0.001"
                size="sm"
                :error="fieldErrors[`${index}.touch`]"
                @update:model-value="(v) => (row.touch = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5">
              <BaseInput
                :model-value="row.waste_total === null ? '' : String(row.waste_total)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (row.waste_total = v === '' ? null : Number(v))"
              />
            </td>
            <td class="w-20 p-1.5 pt-3 tabular-nums text-slate-600">{{ formatNumber(wasteValueFor(row)) }}</td>
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
      v-if="metalPickerRowIndex !== null && headId !== null"
      :item-id="rows[metalPickerRowIndex]!.item_id!"
      :user-id="headId"
      :required="rows[metalPickerRowIndex]!.grams ?? 0"
      @close="metalPickerRowIndex = null"
      @confirm="handleMetalConfirm"
    />
  </BaseCard>
</template>
