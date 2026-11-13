<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { itemsApi } from '@/lib/itemsApi'
import { numericWastageEntriesApi } from '@/lib/numericWastageEntriesApi'
import { numericWastagePurity, numericWastageWeight } from '@/lib/numericWastage'
import { toNumber } from '@/lib/goldCorrection'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { Item, NumericWastageCorrectionPayload, NumericWastageCorrectionRequest } from '@/types'

/*
|--------------------------------------------------------------------------
| Correct a numeric wastage line (BACKEND_TODO §17, heads only)
|--------------------------------------------------------------------------
| Opened from a numeric wastage cash entry's detail modal. One line, with
| the same fields and the same WValue / Total calculations as Numeric
| Wastage In and Out (lib/numericWastage), prefilled with what was saved.
| The parties and the item can't change, and there's no date: the
| backend reverses the original line and posts this one, both dated today.
| Cash = pieces × amount per piece, moved the same way as a new line.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ request: NumericWastageCorrectionRequest }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const items = ref<Item[]>([])
async function reloadItems() {
  items.value = await itemsApi.list()
}
onMounted(() => {
  reloadItems().catch(() => {
    // The item only labels the locked picker; the form still works.
  })
})
const itemOptions = computed(() => {
  const id = props.request.input.item_id
  const name = items.value.find((i) => i.item_id === id)?.item_name ?? `Item #${id}`
  return [{ value: id, label: name }]
})

const input = props.request.input
const form = reactive({
  grams: toNumber(input.grams),
  touch: toNumber(input.touch),
  no_of_pcs: toNumber(input.no_of_pcs),
  amount_pcs: toNumber(input.amount_pcs) ?? 0,
  waste_total: toNumber(input.waste_total) ?? 0,
  remarks: input.remarks ?? '',
  item_remarks: input.item_remarks ?? '',
})

const isIn = props.request.direction === 'in'
const title = `Correct numeric wastage ${isIn ? 'in' : 'out'} #${props.request.id}`
const cash = computed(() => (form.no_of_pcs ?? 0) * (form.amount_pcs ?? 0))
const originalCash = (toNumber(input.no_of_pcs) ?? 0) * (toNumber(input.amount_pcs) ?? 0)

const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})
const formError = ref('')

// Same checks as the creation screens, plus the backend's touch range.
function validate(): boolean {
  const errors: Record<string, string> = {}
  if (form.grams === null || form.grams <= 0) errors.grams = 'Grams must be greater than 0.'
  if (form.touch === null || form.touch < 0 || form.touch > 100) errors.touch = 'Touch must be between 0 and 100.'
  if (form.no_of_pcs === null || form.no_of_pcs <= 0) errors.no_of_pcs = 'Number of pieces must be greater than 0.'
  if ((form.amount_pcs ?? 0) < 0) errors.amount_pcs = 'Amount per piece can\'t be negative.'
  if ((form.waste_total ?? 0) < 0) errors.waste_total = 'Wastage per piece can\'t be negative.'
  fieldErrors.value = errors
  return Object.keys(errors).length === 0
}

async function handleSubmit() {
  if (isSaving.value || !validate()) return

  const payload: NumericWastageCorrectionPayload = {
    grams: form.grams as number,
    touch: form.touch as number,
    no_of_pcs: form.no_of_pcs as number,
    amount_pcs: form.amount_pcs ?? 0,
    waste_total: form.waste_total ?? 0,
    remarks: form.remarks.trim() || null,
    item_remarks: form.item_remarks.trim() || null,
  }

  isSaving.value = true
  formError.value = ''
  try {
    const result = await numericWastageEntriesApi.correct(props.request.direction, props.request.id, payload)
    const newId = result.corrected.stock_in_detail_id ?? result.corrected.stock_id
    toast.show(`Numeric wastage #${props.request.id} corrected; the corrected entry is #${newId}.`, 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError && err.errors) {
      fieldErrors.value = Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0] ?? '']))
      formError.value = Object.values(fieldErrors.value)[0] ?? err.message
    } else {
      formError.value = err instanceof ApiError ? err.message : 'Failed to correct the numeric wastage entry.'
    }
  } finally {
    isSaving.value = false
  }
}

function numberModel(value: number | null): string {
  return value === null ? '' : String(value)
}
function toModel(v: string): number | null {
  return v === '' ? null : Number(v)
}
function formatNumber(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}
function formatMoney(value: number) {
  return Number.isFinite(value)
    ? value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : '0.00'
}
</script>

<template>
  <BaseModal
    :title="title"
    :badge="isIn ? 'IN' : 'OUT'"
    :badge-class="isIn ? 'bg-emerald-600' : 'bg-red-600'"
    max-width="max-w-3xl"
    @close="emit('close')"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        Saving reverses the original line and posts this corrected one, both dated today. The parties and
        item stay as they were.
      </p>

      <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt class="text-xs font-medium text-slate-500">Given by</dt>
          <dd class="text-slate-900">{{ request.givenByName }}</dd>
        </div>
        <div>
          <dt class="text-xs font-medium text-slate-500">Given to</dt>
          <dd class="text-slate-900">{{ request.givenToName }}</dd>
        </div>
      </dl>

      <div class="rounded-lg border border-slate-200 p-3">
        <BaseSelect
          id="nwc_item"
          :model-value="request.input.item_id"
          label="Item"
          size="sm"
          :options="itemOptions"
          disabled
        />

        <div class="mt-2 grid items-end gap-3 sm:grid-cols-4">
          <BaseInput
            id="nwc_grams"
            :model-value="numberModel(form.grams)"
            label="Grams"
            type="number"
            size="sm"
            :error="fieldErrors.grams"
            @update:model-value="(v) => (form.grams = toModel(v))"
          />
          <BaseInput
            id="nwc_touch"
            :model-value="numberModel(form.touch)"
            label="Touch"
            type="number"
            size="sm"
            :error="fieldErrors.touch"
            @update:model-value="(v) => (form.touch = toModel(v))"
          />
          <BaseInput
            id="nwc_no_of_pcs"
            :model-value="numberModel(form.no_of_pcs)"
            label="No. of Pcs"
            type="number"
            step="1"
            size="sm"
            :error="fieldErrors.no_of_pcs"
            @update:model-value="(v) => (form.no_of_pcs = toModel(v))"
          />
          <BaseInput
            id="nwc_amount_pcs"
            :model-value="numberModel(form.amount_pcs)"
            label="Amount/Pc"
            type="number"
            size="sm"
            :error="fieldErrors.amount_pcs"
            @update:model-value="(v) => (form.amount_pcs = toModel(v) ?? 0)"
          />
        </div>

        <div class="mt-2 grid items-end gap-3 sm:grid-cols-4">
          <BaseInput
            id="nwc_waste_total"
            :model-value="numberModel(form.waste_total)"
            label="Wastage (per pc)"
            type="number"
            size="sm"
            :error="fieldErrors.waste_total"
            @update:model-value="(v) => (form.waste_total = toModel(v) ?? 0)"
          />
          <div>
            <p class="mb-1 block text-xs font-medium text-slate-600">WValue</p>
            <p class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm tabular-nums text-slate-600">
              {{ formatNumber(numericWastageWeight(form)) }}
            </p>
          </div>
          <div>
            <p class="mb-1 block text-xs font-medium text-slate-600">Total</p>
            <p class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm tabular-nums text-slate-600">
              {{ formatNumber(numericWastagePurity(form)) }}
            </p>
          </div>
          <div>
            <p class="mb-1 block text-xs font-medium text-slate-600">Cash</p>
            <p class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm tabular-nums text-slate-600">
              {{ formatMoney(cash) }}
              <span v-if="cash !== originalCash" class="text-xs text-slate-400">(was {{ formatMoney(originalCash) }})</span>
            </p>
          </div>
        </div>

        <div class="mt-2 grid gap-3 sm:grid-cols-2">
          <BaseInput
            id="nwc_remarks"
            :model-value="form.remarks"
            label="Remarks"
            size="sm"
            :error="fieldErrors.remarks"
            @update:model-value="(v) => (form.remarks = v)"
          />
          <BaseInput
            id="nwc_item_remarks"
            :model-value="form.item_remarks"
            label="Item remarks"
            size="sm"
            :error="fieldErrors.item_remarks"
            @update:model-value="(v) => (form.item_remarks = v)"
          />
        </div>
      </div>

      <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ formError }}
      </p>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">Cancel</BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save correction' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
