<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { X, Image as ImageIcon } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import { validateImageFile } from '@/lib/imageValidation'
import { useToastStore } from '@/stores/toast'
import { typeLabel, AUTO_CALC_TYPES, sourceTypeOptions } from '@/lib/cashTxnOptions'
import type {
  BankDetail,
  CashTxnDetail,
  CashTxnDetailFormValues,
  CashTxnImage,
  CashTxnSourceType,
  CashTxnType,
  UserDetail,
} from '@/types'

// One modal handles both create (editingTransaction unset) and edit
// (editingTransaction set) for any type — given_by/given_to/type are fixed
// by the caller (the outer Head/User filter + which quick-action button was
// clicked, or the row being edited), not chosen inside the modal, matching
// the reference "Add Expense" dialog which only ever shows a fixed
// "<Head> to <User> :" pair.
const props = defineProps<{
  headUser: UserDetail
  counterpartyUser: UserDetail
  type: CashTxnType
  banks: BankDetail[]
  latestAccountBalance: number
  addedBy: number | null
  editingTransaction?: CashTxnDetail | null
}>()

const emit = defineEmits<{
  close: []
  saved: [txn: CashTxnDetail]
  useAdvancedForm: []
}>()

const toastStore = useToastStore()
const isEditing = computed(() => props.editingTransaction != null)

const categoryId = ref<number | null>(props.editingTransaction?.category_id ?? null)
const amount = ref<number | null>(props.editingTransaction?.amount ?? null)
const sourceType = ref<CashTxnSourceType>(props.editingTransaction?.souce_type ?? 'CASH_ON_HAND')
const bankId = ref<number | null>(props.editingTransaction?.bank_id ?? null)
const amntTransferFromHead = ref(props.editingTransaction?.amnt_transfer_from_head ?? true)
const remarks = ref(props.editingTransaction?.remarks ?? '')
const remainderAt = ref(props.editingTransaction?.remainder_at?.slice(0, 10) ?? '')
const remainder = ref(props.editingTransaction?.remainder ?? '')
const pendingImages = ref<File[]>([])
const existingImages = ref<CashTxnImage[]>(props.editingTransaction?.images ?? [])

const bankOptions = computed(() => props.banks.map((b) => ({ value: b.bank_id, label: b.bank_name })))
const selectedBank = computed(() => props.banks.find((b) => b.bank_id === bankId.value) ?? null)

const isAutoCalc = computed(() => AUTO_CALC_TYPES.includes(props.type))

// Mirrors CashTxnDetailController::calculateBalances() exactly for the
// given_by side (the only side the backend actually persists).
const headDelta = computed(() => {
  if (!isAutoCalc.value || amount.value === null) return 0
  return props.type === 'INCOME' ? amount.value : -amount.value
})

const headOpeningCash = computed(() => props.headUser.rak_cash_balance)
const headOpeningRtgs = computed(() => props.headUser.rak_rtgs_balance)
const headClosingCash = computed(() => headOpeningCash.value + headDelta.value)
const headClosingRtgs = computed(() =>
  sourceType.value === 'BANK' ? headOpeningRtgs.value + headDelta.value : headOpeningRtgs.value,
)

// given_to's balance is never read or written by the backend for cash
// transactions today (only given_by's rak_cash_balance persists) — shown
// as the mirrored, zero-sum side of the same delta purely for reference.
const counterpartyOpeningCash = computed(() => props.counterpartyUser.rak_cash_balance)
const counterpartyOpeningRtgs = computed(() => props.counterpartyUser.rak_rtgs_balance)
const counterpartyClosingCash = computed(() => counterpartyOpeningCash.value - headDelta.value)
const counterpartyClosingRtgs = computed(() =>
  sourceType.value === 'BANK'
    ? counterpartyOpeningRtgs.value - headDelta.value
    : counterpartyOpeningRtgs.value,
)

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  addFiles(input.files)
  input.value = ''
}

function addFiles(files: FileList | File[] | null) {
  if (!files) return
  for (const file of Array.from(files)) {
    const error = validateImageFile(file)
    if (error) {
      toastStore.show(error, 'error')
      continue
    }
    pendingImages.value.push(file)
  }
}

const isDraggingOver = ref(false)
function handleDrop(event: DragEvent) {
  isDraggingOver.value = false
  addFiles(event.dataTransfer?.files ?? null)
}

function removePendingImage(index: number) {
  pendingImages.value.splice(index, 1)
}

const removingImageId = ref<number | null>(null)
async function removeExistingImage(image: CashTxnImage) {
  removingImageId.value = image.image_id
  try {
    await cashTxnDetailsApi.deleteImage(image.image_id)
    existingImages.value = existingImages.value.filter((img) => img.image_id !== image.image_id)
    toastStore.show('Image removed.', 'success')
  } catch (err) {
    toastStore.show(err instanceof ApiError ? err.message : 'Failed to remove image.', 'error')
  } finally {
    removingImageId.value = null
  }
}

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const isSaving = ref(false)

function validate(): boolean {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''

  if (amount.value === null || Number.isNaN(amount.value)) {
    fieldErrors.amount = 'Amount is required.'
  } else if (amount.value < 0) {
    fieldErrors.amount = 'Amount cannot be negative.'
  }
  if (sourceType.value === 'BANK' && bankId.value === null) {
    fieldErrors.bank_id = 'Select a bank.'
  }
  if (remarks.value.length > 5000) {
    fieldErrors.remarks = 'Remarks must be 5000 characters or fewer.'
  }
  if (remainder.value.length > 1500) {
    fieldErrors.remainder = 'Reminder note must be 1500 characters or fewer.'
  }

  const firstError = Object.values(fieldErrors)[0]
  if (firstError) {
    formError.value = firstError
    toastStore.show(firstError, 'error')
    return false
  }
  return true
}

function buildPayload(): CashTxnDetailFormValues {
  return {
    type: props.type,
    given_to: props.counterpartyUser.user_id,
    given_by: props.headUser.user_id,
    category_id: categoryId.value,
    amount: amount.value,

    opening_account_balance: isEditing.value
      ? (props.editingTransaction?.opening_account_balance ?? headOpeningCash.value)
      : (props.latestAccountBalance ?? headOpeningCash.value),
    opening_user_balance: headOpeningCash.value,
    opening_bank_account_balance:
      sourceType.value === 'BANK' ? (selectedBank.value?.current_balance ?? 0) : null,
    opening_bank_user_balance: sourceType.value === 'BANK' ? headOpeningRtgs.value : null,

    souce_type: sourceType.value,
    bank_id: bankId.value,
    bank_name: selectedBank.value?.bank_name ?? '',

    remarks: remarks.value,
    remainder: remainder.value,
    remainder_at: remainderAt.value,

    added_by: props.addedBy,

    is_active: props.editingTransaction?.is_active ?? true,
    is_hidden: props.editingTransaction?.is_hidden ?? false,
    is_show_to_all: props.editingTransaction?.is_show_to_all ?? false,

    amount_transfer_id: props.editingTransaction?.amount_transfer_id ?? null,
    cash_to_gold_id: props.editingTransaction?.cash_to_gold_id ?? null,
    stock_id: props.editingTransaction?.stock_id ?? null,
    amnt_transfer_from_head: amntTransferFromHead.value,
    internal_type: props.editingTransaction?.internal_type ?? '',

    retailer_id: props.editingTransaction?.retailer_id ?? null,
    retailer_ob_cash_balance: props.editingTransaction?.retailer_ob_cash_balance ?? null,
    retailer_ob_rtgs_balance: props.editingTransaction?.retailer_ob_rtgs_balance ?? null,

    txn_type: props.editingTransaction?.txn_type ?? 'NORMAL',
    bank_entry_date: props.editingTransaction?.bank_entry_date?.slice(0, 10) ?? '',

    machine_vendor_id: props.editingTransaction?.machine_vendor_id ?? null,
    machine_vendor_ob_cash_balance: props.editingTransaction?.machine_vendor_ob_cash_balance ?? null,
    machine_vendor_ob_rtgs_balance: props.editingTransaction?.machine_vendor_ob_rtgs_balance ?? null,

    is_bill_cash: props.editingTransaction?.is_bill_cash ?? false,
    is_payment_cash: props.editingTransaction?.is_payment_cash ?? false,
    is_customer_affect: props.editingTransaction?.is_customer_affect ?? false,
    is_need_receipt: props.editingTransaction?.is_need_receipt ?? false,
    bill_payment_cash_type: props.editingTransaction?.bill_payment_cash_type ?? '',
    partial_amount: props.editingTransaction?.partial_amount ?? null,
    actual_amount: props.editingTransaction?.actual_amount ?? null,
    receipt_cash_txn_id: props.editingTransaction?.receipt_cash_txn_id ?? null,

    given_by_arithmetic_operation: props.editingTransaction?.given_by_arithmetic_operation ?? '',
    given_to_arithmetic_operation: props.editingTransaction?.given_to_arithmetic_operation ?? '',

    cash_loan_type: props.editingTransaction?.cash_loan_type ?? '',
    per_gram_cash: props.editingTransaction?.per_gram_cash ?? null,

    over_all_bill_id: props.editingTransaction?.over_all_bill_id ?? null,
    estimate_retailer_bill_id: props.editingTransaction?.estimate_retailer_bill_id ?? null,
    estimate_metal_bill_id: props.editingTransaction?.estimate_metal_bill_id ?? null,

    is_admin_head_entry: props.editingTransaction?.is_admin_head_entry ?? false,
    admin_head_txn_id: props.editingTransaction?.admin_head_txn_id ?? null,
  }
}

async function handleSubmit() {
  if (!validate()) return

  isSaving.value = true
  try {
    const saved = isEditing.value
      ? await cashTxnDetailsApi.update(props.editingTransaction!.txn_id, buildPayload())
      : await cashTxnDetailsApi.create(buildPayload())

    if (pendingImages.value.length) {
      await cashTxnDetailsApi.addImages(saved.txn_id, pendingImages.value)
    }

    toastStore.show(
      isEditing.value ? 'Transaction updated successfully.' : 'Transaction created successfully.',
      'success',
    )
    emit('saved', saved)
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
      formError.value = 'Failed to save transaction.'
      toastStore.show('Failed to save transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-8 print:hidden"
    >
      <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-900">
            {{ isEditing ? 'Edit' : 'Add' }} {{ typeLabel(type) }}
          </h2>
          <button
            type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <form class="flex flex-col gap-5 px-6 py-5" @submit.prevent="handleSubmit">
          <p class="text-sm text-slate-600">
            <span class="font-semibold text-slate-800">{{ headUser.name }}</span>
            to
            <span class="font-semibold text-slate-800">{{ counterpartyUser.name }}</span> :
          </p>

          <div class="overflow-x-auto">
            <div class="grid gap-4 sm:grid-cols-2">
              <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
                <thead>
                  <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                    <th class="px-3 py-2">{{ headUser.name }}</th>
                    <th class="px-3 py-2">Hand Cash</th>
                    <th class="px-3 py-2">RTGS Cash</th>
                    <th class="px-3 py-2">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="bg-emerald-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                    <td class="px-3 py-2">{{ headOpeningCash.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headOpeningRtgs.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ (headOpeningCash + headOpeningRtgs).toLocaleString() }}</td>
                  </tr>
                  <tr class="bg-rose-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">CB</td>
                    <td class="px-3 py-2">{{ headClosingCash.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headClosingRtgs.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ (headClosingCash + headClosingRtgs).toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>

              <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
                <thead>
                  <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                    <th class="px-3 py-2">{{ counterpartyUser.name }}</th>
                    <th class="px-3 py-2">Hand Cash</th>
                    <th class="px-3 py-2">RTGS Cash</th>
                    <th class="px-3 py-2">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="bg-emerald-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                    <td class="px-3 py-2">{{ counterpartyOpeningCash.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ counterpartyOpeningRtgs.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{ (counterpartyOpeningCash + counterpartyOpeningRtgs).toLocaleString() }}
                    </td>
                  </tr>
                  <tr class="bg-rose-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">CB</td>
                    <td class="px-3 py-2">{{ counterpartyClosingCash.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ counterpartyClosingRtgs.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{ (counterpartyClosingCash + counterpartyClosingRtgs).toLocaleString() }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="mt-1 text-xs text-slate-400">
              Only {{ headUser.name }}'s balance updates automatically —
              {{ counterpartyUser.name }}'s side is shown for reference only.
            </p>
            <p v-if="!isAutoCalc" class="mt-1 text-xs text-amber-700">
              {{ typeLabel(type) }} doesn't auto-calculate closing balances — CB is shown equal to
              OB until the amount is entered.
            </p>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <BaseInput
              id="category_id"
              :model-value="categoryId === null ? '' : String(categoryId)"
              label="Category"
              type="number"
              size="sm"
              placeholder="No category lookup yet"
              @update:model-value="(v) => (categoryId = v === '' ? null : Number(v))"
            />
            <BaseInput
              id="amount"
              :model-value="amount === null ? '' : String(amount)"
              label="Amount"
              type="number"
              step="0.01"
              required
              size="sm"
              :error="fieldErrors.amount"
              @update:model-value="(v) => (amount = v === '' ? null : Number(v))"
            />
            <BaseSelect
              id="souce_type"
              v-model="sourceType"
              label="Source Type"
              required
              size="sm"
              :options="sourceTypeOptions"
            />
            <BaseSelect
              v-if="sourceType === 'BANK'"
              id="bank_id"
              v-model="bankId"
              label="Bank"
              required
              size="sm"
              placeholder="Select a bank…"
              :options="bankOptions"
              :error="fieldErrors.bank_id"
            />
          </div>

          <BaseCheckbox v-model="amntTransferFromHead" label="Amount transfer from head" />

          <BaseTextarea
            id="remarks"
            v-model="remarks"
            label="Remarks"
            size="sm"
            :rows="2"
            :error="fieldErrors.remarks"
          />

          <div>
            <p class="mb-2 text-sm font-medium text-slate-700">Photos</p>

            <div v-if="existingImages.length" class="mb-3 flex flex-wrap gap-3">
              <div
                v-for="image in existingImages"
                :key="image.image_id"
                class="relative h-16 w-16 overflow-hidden rounded-lg border border-slate-200"
              >
                <img :src="image.image_full_url" class="h-full w-full object-cover" alt="" />
                <button
                  type="button"
                  class="absolute top-0.5 right-0.5 rounded-full bg-white/90 p-0.5 text-slate-500 hover:text-red-600 disabled:opacity-50"
                  aria-label="Remove image"
                  :disabled="removingImageId === image.image_id"
                  @click="removeExistingImage(image)"
                >
                  <X class="h-3 w-3" />
                </button>
              </div>
            </div>

            <div v-if="pendingImages.length" class="mb-3 flex flex-wrap gap-2">
              <span
                v-for="(file, index) in pendingImages"
                :key="index"
                class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-1 pr-1 pl-2.5 text-xs text-slate-700"
              >
                <ImageIcon class="h-3.5 w-3.5" />
                {{ file.name }}
                <button
                  type="button"
                  class="rounded-full p-0.5 hover:bg-slate-200"
                  aria-label="Remove selected file"
                  @click="removePendingImage(index)"
                >
                  <X class="h-3 w-3" />
                </button>
              </span>
            </div>

            <label
              class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-6 text-center text-sm text-slate-400 transition-colors"
              :class="isDraggingOver ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200'"
              @dragover.prevent="isDraggingOver = true"
              @dragleave.prevent="isDraggingOver = false"
              @drop.prevent="handleDrop"
            >
              Drag &amp; drop files here, or click to browse
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                class="hidden"
                @change="handleFileSelect"
              />
            </label>
            <p class="mt-1 text-xs text-slate-500">JPG, PNG or WEBP, up to 5MB each.</p>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <BaseInput id="remainder_at" v-model="remainderAt" label="Reminder date" type="date" size="sm" />
            <BaseInput
              id="remainder"
              v-model="remainder"
              label="Reminder note"
              size="sm"
              placeholder="Optional"
              :error="fieldErrors.remainder"
            />
          </div>

          <p
            v-if="formError"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          >
            {{ formError }}
          </p>

          <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
            <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
            <button
              type="button"
              class="text-xs text-slate-400 hover:text-slate-600"
              @click="emit('useAdvancedForm')"
            >
              Need retailer/vendor/bill-split fields? Use the advanced form instead
            </button>
            <BaseButton type="submit" :disabled="isSaving" class="ml-auto">
              {{ isSaving ? 'Saving…' : 'Save' }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
