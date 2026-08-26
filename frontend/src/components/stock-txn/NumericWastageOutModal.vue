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
  NumericWastageOutFormValues,
  NumericWastageOutItemInput,
  UserDetailListItem,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Numeric Wastage Out modal — new screen, mirrors NumericWastageInModal's
| row shape but given_by is optional (defaults server-side to the acting
| user), per NumericWasteRequest.php. Calls stockApi.postNumericWasteOut ->
| POST /stock/numeric-waste, which is registered (unlike the In route).
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

function makeEmptyItem(): NumericWastageOutItemInput {
  return {
    item_id: null,
    grams: null,
    touch: 100,
    no_of_pcs: null,
    amount_pcs: 0,
    waste_total: 0,
    remarks: '',
    item_remarks: '',
  }
}
function makeEmptyForm(): NumericWastageOutFormValues {
  return { given_by: null, given_to: null, added_at: todayDateInputValue(), items: [makeEmptyItem()] }
}

const form = reactive<NumericWastageOutFormValues>(makeEmptyForm())
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
function onItemSelect(row: NumericWastageOutItemInput, itemId: number | null) {
  row.item_id = itemId
  const item = props.items.find((i) => i.item_id === itemId)
  if (item) row.touch = item.default_touch
}

function wastageTotalFor(row: NumericWastageOutItemInput): number {
  return (row.no_of_pcs ?? 0) * (row.waste_total ?? 0)
}
function amountFor(row: NumericWastageOutItemInput): number {
  return (row.no_of_pcs ?? 0) * (row.amount_pcs ?? 0)
}
function purityFor(row: NumericWastageOutItemInput): number {
  return (((row.grams ?? 0) + wastageTotalFor(row)) * (row.touch ?? 0)) / 100
}

// Mirrors NumericWasteRequest exactly — given_by is nullable/optional
// there (unlike Numeric Wastage In where both parties are required).
function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

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
    if (row.no_of_pcs === null || row.no_of_pcs <= 0) {
      fieldErrors[`items.${index}.no_of_pcs`] = `Row ${index + 1}: number of pieces must be greater than 0.`
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
    formError.value = 'You must be signed in to record a numeric wastage out.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    const result = await stockApi.postNumericWasteOut(
      { ...form, items: form.items.map((i) => ({ ...i })) },
      auth.user.user_id,
    )
    toastStore.show(`Numeric wastage out recorded — ${result.length} item(s).`, 'success')
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
      formError.value = 'Failed to record numeric wastage out.'
      toastStore.show('Failed to record numeric wastage out.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal
    title="Numeric Wastage Out"
    badge="OUT"
    badge-class="bg-red-600"
    max-width="max-w-4xl"
    @close="emit('close')"
  >
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>
    <p class="text-xs text-slate-500">
      Amount (no. of pcs × amount per pc) is recorded as a cash payout to "Given to" automatically.
    </p>

    <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
      <section class="grid gap-3 sm:grid-cols-3">
        <BaseSelect
          id="given_by"
          :model-value="form.given_by"
          label="Given by (optional)"
          size="sm"
          placeholder="Defaults to you…"
          :options="userOptions"
          :error="fieldErrors.given_by"
          @update:model-value="(v) => (form.given_by = v as number | null)"
        />
        <BaseSelect
          id="given_to"
          :model-value="form.given_to"
          label="Given to"
          required
          size="sm"
          placeholder="Select a user…"
          :options="userOptions"
          :error="fieldErrors.given_to"
          @update:model-value="(v) => (form.given_to = v as number | null)"
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
          <div class="grid items-end gap-3 sm:grid-cols-3">
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
              step="0.001"
              size="sm"
              :error="fieldErrors[`items.${index}.touch`]"
              @update:model-value="(v) => (row.touch = v === '' ? null : Number(v))"
            />
          </div>

          <div class="mt-2 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <BaseInput
              :id="`no_of_pcs_${index}`"
              :model-value="row.no_of_pcs === null ? '' : String(row.no_of_pcs)"
              label="No. of pieces"
              type="number"
              step="1"
              size="sm"
              :error="fieldErrors[`items.${index}.no_of_pcs`]"
              @update:model-value="(v) => (row.no_of_pcs = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`amount_pcs_${index}`"
              :model-value="row.amount_pcs === null ? '' : String(row.amount_pcs)"
              label="Amount per piece"
              type="number"
              step="0.001"
              size="sm"
              @update:model-value="(v) => (row.amount_pcs = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`waste_total_${index}`"
              :model-value="row.waste_total === null ? '' : String(row.waste_total)"
              label="Wastage per piece"
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
            Wastage total: <span class="font-medium text-slate-700">{{ wastageTotalFor(row).toFixed(4) }}</span>
            · Cash amount: <span class="font-medium text-slate-700">{{ amountFor(row).toFixed(2) }}</span>
            · Computed purity: <span class="font-medium text-slate-700">{{ purityFor(row).toFixed(4) }}</span>
            <span class="italic"> (server calculates the final values; this is a preview)</span>
          </p>
        </div>
      </section>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">Cancel</BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Recording…' : 'Record numeric wastage out' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
