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
import type { Item, ItemChangeFormValues, ItemChangeItemInput, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Item Change modal — new screen, mirrors ItemChangeRequest.php.
|--------------------------------------------------------------------------
| stock_in_id (the existing stock lot being changed) is hidden from the UI
| per explicit request — it's still sent in the payload (always null) since
| ItemChangeRequest.php validates it as `required|integer|exists:stock_details,
| stock_id`. Until a backend endpoint lists a user's stock lots (or the
| requirement is dropped server-side), submissions will fail that
| validation rule — flagged to the backend team, not worked around here.
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

function makeEmptyItem(): ItemChangeItemInput {
  return {
    stock_in_id: null,
    from_item_id: null,
    to_item_id: null,
    grams: null,
    from_touch: 100,
    req_touch: 100,
    remarks: '',
    item_remarks: '',
  }
}
function makeEmptyForm(): ItemChangeFormValues {
  return { user_id: null, added_at: todayDateInputValue(), items: [makeEmptyItem()] }
}

const form = reactive<ItemChangeFormValues>(makeEmptyForm())
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

// Mirrors StockOutService::createItemChange()'s fallback formula — display
// preview only; the server derives the final purity.
function purityFor(row: ItemChangeItemInput): number {
  return ((row.grams ?? 0) * (row.req_touch ?? 0)) / 100
}

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.user_id === null) fieldErrors.user_id = 'User is required.'

  if (form.items.length === 0) {
    fieldErrors.items = 'Add at least one item.'
  }

  form.items.forEach((row, index) => {
    if (row.from_item_id === null) {
      fieldErrors[`items.${index}.from_item_id`] = `Row ${index + 1}: select the current item.`
    }
    if (row.to_item_id === null) {
      fieldErrors[`items.${index}.to_item_id`] = `Row ${index + 1}: select the new item.`
    }
    if (row.from_item_id !== null && row.from_item_id === row.to_item_id) {
      fieldErrors[`items.${index}.to_item_id`] = `Row ${index + 1}: new item must differ from current item.`
    }
    if (row.grams === null || row.grams <= 0) {
      fieldErrors[`items.${index}.grams`] = `Row ${index + 1}: grams must be greater than 0.`
    }
    if (row.from_touch === null || row.from_touch < 0 || row.from_touch > 100) {
      fieldErrors[`items.${index}.from_touch`] = `Row ${index + 1}: from-touch must be between 0 and 100.`
    }
    if (row.req_touch === null || row.req_touch < 0 || row.req_touch > 100) {
      fieldErrors[`items.${index}.req_touch`] = `Row ${index + 1}: requested touch must be between 0 and 100.`
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
    formError.value = 'You must be signed in to record an item change.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    const result = await stockApi.postItemChange(
      { ...form, items: form.items.map((i) => ({ ...i })) },
      auth.user.user_id,
    )
    toastStore.show(`Item change recorded — ${result.length} item(s).`, 'success')
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
      formError.value = 'Failed to record item change.'
      toastStore.show('Failed to record item change.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="Item Change" badge="OUT" badge-class="bg-red-600" max-width="max-w-4xl" @close="emit('close')">
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>
    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      <strong>Pending backend:</strong> the stock lot ID field is hidden here, but the backend
      still requires a valid one per item — submissions will fail until a lot-lookup endpoint
      exists or the requirement is dropped server-side.
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
              :id="`from_item_${index}`"
              :model-value="row.from_item_id"
              label="From item"
              size="sm"
              placeholder="Current item…"
              :options="itemOptions"
              :error="fieldErrors[`items.${index}.from_item_id`]"
              @update:model-value="(v) => (row.from_item_id = v as number | null)"
            />
            <BaseSelect
              :id="`to_item_${index}`"
              :model-value="row.to_item_id"
              label="To item"
              size="sm"
              placeholder="New item…"
              :options="itemOptions"
              :error="fieldErrors[`items.${index}.to_item_id`]"
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
              :error="fieldErrors[`items.${index}.grams`]"
              @update:model-value="(v) => (row.grams = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`from_touch_${index}`"
              :model-value="row.from_touch === null ? '' : String(row.from_touch)"
              label="From touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`items.${index}.from_touch`]"
              @update:model-value="(v) => (row.from_touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :id="`req_touch_${index}`"
              :model-value="row.req_touch === null ? '' : String(row.req_touch)"
              label="Requested touch"
              type="number"
              step="0.001"
              size="sm"
              :error="fieldErrors[`items.${index}.req_touch`]"
              @update:model-value="(v) => (row.req_touch = v === '' ? null : Number(v))"
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
  </BaseModal>
</template>
