<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Trash, Layers, X } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import MetalPickerModal from '@/components/stock/MetalPickerModal.vue'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { nowDateTimeInputValue, toBackendDateTime } from '@/lib/date'
import { isMetalItem } from '@/lib/metalItem'
import { useToastStore } from '@/stores/toast'
import type { Item, ItemChangeItemInput, MetalPickerSelection } from '@/types'

/*
|--------------------------------------------------------------------------
| Item Change modal — mirrors ItemChangeRequest.php
|--------------------------------------------------------------------------
| Replaces ItemChangePanel.vue: Item Change is a modal action here, not
| part of the inline add-row/shared-Submit grid used by Stock Out/In, GMS
| In, and Numeric Wastage (see StockManagementView.vue's comment for why
| — this one, Item Conversion, and GMS Out submit standalone instead of
| queuing rows).
|
| Scoped to `userId` (selectedUserId from the page's UserPickerPanel, the
| user whose stock composition is being changed) rather than an internal
| user picker — matches the panel it replaces. actingUserId sent as
| userId too (X-User-ID header), same as the panel — not changed here.
|
| stock_in_id (the source lot) was `required` in ItemChangeRequest with no
| way to obtain one, which used to make every submission fail. Backend
| commit 6747887 relaxed it to `nullable` and taught
| StockOutService::createItemChange to skip the parent-lot draw-down when
| it's absent, so the modal now submits with or without a lot.
|
| It's still worth sending one where we can: with a lot the service
| deducts that lot's balance, completes it at zero, and records real
| OB/CB purity against it; without one those snapshot fields land as 0.
| Selecting the item literally named "Metal" as the From item therefore
| opens MetalPickerModal (GET /stock-details/available-metals) scoped to
| `userId` — createItemChange sets both given_by and given_to to that same
| user, which matches the endpoint's own `where('given_to', ...)` query.
| Saving there replaces the triggering row with one row per lot taken,
| carrying stock_in_id and the lot's touch. Same pattern as GmsOutModal.
|
| Non-metal items have no lot-lookup endpoint, so those rows post with a
| null stock_in_id — accepted by the backend, just without the lot link.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ items: Item[]; userId: number | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))

function makeEmptyRow(): ItemChangeItemInput {
  return {
    stock_in_id: null,
    from_item_id: null,
    to_item_id: null,
    grams: null,
    from_touch: 100,
    req_touch: 100,
    remarks: '',
    item_remarks: '',
    added_at: nowDateTimeInputValue(),
  }
}

const rows = ref<ItemChangeItemInput[]>([makeEmptyRow()])
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

// Opening the picker is the only side effect of choosing a From item —
// from_touch is deliberately left alone so the operator's typed value (or
// the 100 default) still stands, exactly as before.
function onFromItemSelect(row: ItemChangeItemInput, index: number, itemId: number | null) {
  row.from_item_id = itemId
  // The lot no longer belongs to whatever item was just picked.
  row.stock_in_id = null
  if (isMetalItem(props.items.find((i) => i.item_id === itemId))) metalPickerRowIndex.value = index
}

function handleMetalConfirm(selection: MetalPickerSelection[]) {
  const index = metalPickerRowIndex.value
  metalPickerRowIndex.value = null
  if (index === null) return

  const source = rows.value[index]
  if (!source || selection.length === 0) return

  // One row per lot taken: grams and from_touch come from the lot itself,
  // everything else carries over from the row that opened the picker.
  const lotRows: ItemChangeItemInput[] = selection.map((lot) => ({
    stock_in_id: lot.id,
    from_item_id: source.from_item_id,
    to_item_id: source.to_item_id,
    grams: lot.taken,
    from_touch: lot.touch,
    req_touch: source.req_touch,
    remarks: source.remarks,
    item_remarks: [source.item_remarks, `Lot #${lot.id} (${lot.party_name})`]
      .filter(Boolean)
      .join(' — '),
    added_at: source.added_at,
  }))
  rows.value.splice(index, 1, ...lotRows)
}

// Mirrors StockOutService::createItemChange()'s fallback formula — display
// preview only; the server derives the final purity.
function purityFor(row: ItemChangeItemInput): number {
  return ((row.grams ?? 0) * (row.req_touch ?? 0)) / 100
}

function validate(): boolean {
  fieldErrors.value = {}

  if (rows.value.length === 0) {
    fieldErrors.value.items = 'Add at least one item.'
  }

  rows.value.forEach((row, index) => {
    if (row.from_item_id === null) fieldErrors.value[`${index}.from_item_id`] = `Row ${index + 1}: select the current item.`
    if (row.to_item_id === null) fieldErrors.value[`${index}.to_item_id`] = `Row ${index + 1}: select the new item.`
    if (row.from_item_id !== null && row.from_item_id === row.to_item_id) {
      fieldErrors.value[`${index}.to_item_id`] = `Row ${index + 1}: new item must differ from current item.`
    }
    if (row.grams === null || row.grams <= 0) {
      fieldErrors.value[`${index}.grams`] = `Row ${index + 1}: grams must be greater than 0.`
    }
    if (row.from_touch === null || row.from_touch < 0 || row.from_touch > 100) {
      fieldErrors.value[`${index}.from_touch`] = `Row ${index + 1}: from-touch must be between 0 and 100.`
    }
    if (row.req_touch === null || row.req_touch < 0 || row.req_touch > 100) {
      fieldErrors.value[`${index}.req_touch`] = `Row ${index + 1}: requested touch must be between 0 and 100.`
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
  if (props.userId === null) {
    toast.show('Select a user (left panel) before recording an Item Change.', 'error')
    return
  }
  if (!validate()) return

  isSaving.value = true
  try {
    const result = await stockApi.postItemChange(
      {
        user_id: props.userId,
        items: rows.value.map((row) => ({
          ...row,
          added_at: toBackendDateTime(row.added_at),
        })),
      },
      props.userId,
    )
    toast.show(`Item change recorded — ${result.length} item(s).`, 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      if (err.errors) {
        toast.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toast.show(err.message, 'error')
      }
    } else {
      toast.show('Failed to record item change.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="Item Change" badge="OUT" badge-class="bg-red-600" max-width="max-w-4xl" @close="emit('close')">
    <p v-if="userId === null" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      Select a user in the left panel before recording an Item Change.
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
            <div>
              <BaseSelect
                :id="`from_item_${index}`"
                :model-value="row.from_item_id"
                label="From item"
                size="sm"
                placeholder="Current item…"
                :options="itemOptions"
                :error="fieldErrors[`${index}.from_item_id`]"
                @update:model-value="(v) => onFromItemSelect(row, index, v as number | null)"
              />
              <button
                v-if="isMetalItem(items.find((i) => i.item_id === row.from_item_id))"
                type="button"
                class="mt-1 flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700"
                @click="metalPickerRowIndex = index"
              >
                <Layers class="h-3 w-3" /> Pick lots…
              </button>
              <span
                v-if="row.stock_in_id !== null"
                class="mt-1 inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-xs text-slate-600"
              >
                Lot #{{ row.stock_in_id }}
                <button
                  type="button"
                  class="text-slate-400 hover:text-red-600"
                  aria-label="Unlink stock lot"
                  @click="row.stock_in_id = null"
                >
                  <X class="h-3 w-3" />
                </button>
              </span>
            </div>
            <BaseSelect
              :id="`to_item_${index}`"
              :model-value="row.to_item_id"
              label="To item"
              size="sm"
              placeholder="New item…"
              :options="itemOptions"
              :error="fieldErrors[`${index}.to_item_id`]"
              @update:model-value="(v) => (row.to_item_id = v as number | null)"
            />
          </div>

          <div class="mt-2 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
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
              :id="`from_touch_${index}`"
              :model-value="row.from_touch === null ? '' : String(row.from_touch)"
              label="From touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`${index}.from_touch`]"
              @update:model-value="(v) => (row.from_touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`req_touch_${index}`"
              :model-value="row.req_touch === null ? '' : String(row.req_touch)"
              label="Requested touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`${index}.req_touch`]"
              @update:model-value="(v) => (row.req_touch = v === '' ? null : Number(v))"
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
        <BaseButton type="submit" :disabled="isSaving">{{ isSaving ? 'Recording…' : 'Record item change' }}</BaseButton>
      </div>
    </form>

    <MetalPickerModal
      v-if="metalPickerRowIndex !== null && userId !== null"
      :item-id="rows[metalPickerRowIndex]!.from_item_id!"
      :user-id="userId"
      :required="rows[metalPickerRowIndex]!.grams ?? 0"
      @close="metalPickerRowIndex = null"
      @confirm="handleMetalConfirm"
    />
  </BaseModal>
</template>
