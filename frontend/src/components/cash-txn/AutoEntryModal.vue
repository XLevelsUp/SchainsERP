<script setup lang="ts">
import { reactive, ref } from 'vue'
import { X, Trash, Plus, Image as ImageIcon } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import { validateImageFile } from '@/lib/imageValidation'
import { useToastStore } from '@/stores/toast'
import type { CashTxnDetail, CashTxnDetailFormValues, UserDetail } from '@/types'

// Unlike Purchase Gold / Gold To Cash / Cash To Gold, "Auto Entry" is just
// CashTxnDetailController's type=AUTO_ENTRY — a real, already-working path
// through cashTxnDetailsApi (same one EXPENSE/INCOME use). The legacy
// dialog batches several line items into one screen, but the backend only
// takes one record per call, so each row here becomes its own POST,
// submitted in sequence. AUTO_ENTRY isn't in AUTO_CALC_TYPES, so none of
// them move any balance — opening is stored as-is, closing equals opening.
const props = defineProps<{
  headUser: UserDetail
  counterpartyUser: UserDetail
  latestAccountBalance: number
  addedBy: number | null
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const toastStore = useToastStore()

interface EntryRow {
  dateTime: string
  categoryId: number | null
  amount: number | null
  remarks: string
}

function nowLocal(): string {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function makeEmptyRow(): EntryRow {
  return { dateTime: nowLocal(), categoryId: null, amount: null, remarks: '' }
}

const entries = reactive<EntryRow[]>([makeEmptyRow()])
const pendingImages = ref<File[]>([])

function addRow() {
  entries.push(makeEmptyRow())
}
function removeRow(index: number) {
  if (entries.length > 1) entries.splice(index, 1)
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) {
    for (const file of Array.from(input.files)) {
      const error = validateImageFile(file)
      if (error) {
        toastStore.show(error, 'error')
        continue
      }
      pendingImages.value.push(file)
    }
  }
  input.value = ''
}
function removePendingImage(index: number) {
  pendingImages.value.splice(index, 1)
}

const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const isSaving = ref(false)

function validate(): boolean {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''

  entries.forEach((row, index) => {
    if (row.amount === null || row.amount < 0) {
      fieldErrors[`amount.${index}`] = `Row ${index + 1}: amount is required.`
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

function buildPayload(row: EntryRow): CashTxnDetailFormValues {
  return {
    type: 'AUTO_ENTRY',
    given_to: props.counterpartyUser.user_id,
    given_by: props.headUser.user_id,
    category_id: row.categoryId,
    amount: row.amount,

    opening_account_balance: props.latestAccountBalance,
    opening_user_balance: props.headUser.rak_cash_balance,
    opening_bank_account_balance: null,
    opening_bank_user_balance: null,

    souce_type: 'CASH_ON_HAND',
    bank_id: null,
    bank_name: '',

    remarks: row.remarks,
    remainder: '',
    remainder_at: '',

    added_by: props.addedBy,

    is_active: true,
    is_hidden: false,
    is_show_to_all: false,

    amount_transfer_id: null,
    cash_to_gold_id: null,
    stock_id: null,
    amnt_transfer_from_head: true,
    internal_type: '',

    retailer_id: null,
    retailer_ob_cash_balance: null,
    retailer_ob_rtgs_balance: null,

    txn_type: 'NORMAL',
    bank_entry_date: '',

    machine_vendor_id: null,
    machine_vendor_ob_cash_balance: null,
    machine_vendor_ob_rtgs_balance: null,

    is_bill_cash: false,
    is_payment_cash: false,
    is_customer_affect: false,
    is_need_receipt: false,
    bill_payment_cash_type: '',
    partial_amount: null,
    actual_amount: null,
    receipt_cash_txn_id: null,

    given_by_arithmetic_operation: '',
    given_to_arithmetic_operation: '',

    cash_loan_type: '',
    per_gram_cash: null,

    over_all_bill_id: null,
    estimate_retailer_bill_id: null,
    estimate_metal_bill_id: null,

    is_admin_head_entry: false,
    admin_head_txn_id: null,
  }
}

async function handleSave() {
  if (!validate()) return

  isSaving.value = true
  formError.value = ''
  try {
    const created: CashTxnDetail[] = []
    for (const row of entries) {
      const saved = await cashTxnDetailsApi.create(buildPayload(row))
      created.push(saved)
    }

    // Photos have no natural per-row home once submission fans out into
    // several records — attach them to the last entry created.
    if (pendingImages.value.length && created.length) {
      const last = created[created.length - 1]
      await cashTxnDetailsApi.addImages(last.txn_id, pendingImages.value)
    }

    toastStore.show(
      created.length > 1
        ? `${created.length} auto entries created successfully.`
        : 'Auto entry created successfully.',
      'success',
    )
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      toastStore.show(err.message, 'error')
    } else {
      formError.value = 'Failed to save one or more entries.'
      toastStore.show('Failed to save one or more entries.', 'error')
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
      <div class="w-full max-w-3xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-900">Add Auto Entry</h2>
          <button
            type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="flex flex-col gap-5 px-6 py-5">
          <p
            v-if="formError"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          >
            {{ formError }}
          </p>

          <p class="text-sm text-slate-600">
            <span class="font-semibold text-slate-800">{{ headUser.name }}</span> :
            {{ (headUser.rak_cash_balance + headUser.rak_rtgs_balance).toLocaleString() }}
          </p>

          <table class="w-full max-w-sm overflow-hidden rounded-lg border border-slate-200 text-sm">
            <thead>
              <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                <th class="px-3 py-2">{{ counterpartyUser.name }}</th>
                <th class="px-3 py-2">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr class="bg-emerald-50/60">
                <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                <td class="px-3 py-2">
                  {{ (counterpartyUser.rak_cash_balance + counterpartyUser.rak_rtgs_balance).toLocaleString() }}
                </td>
              </tr>
              <tr class="bg-rose-50/60 text-slate-400">
                <td class="px-3 py-2 font-medium">CB</td>
                <td class="px-3 py-2">Not auto-calculated for this type</td>
              </tr>
            </tbody>
          </table>

          <div>
            <div class="mb-2 flex items-center justify-between">
              <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white">
                New Expense
              </span>
              <BaseButton variant="secondary" type="button" :icon="Plus" @click="addRow">
                Add row
              </BaseButton>
            </div>

            <div v-for="(row, index) in entries" :key="index" class="mb-3 rounded-lg border border-slate-200 p-3">
              <div class="grid items-end gap-3 sm:grid-cols-[1.2fr_1fr_1fr_1.4fr_auto]">
                <BaseInput
                  :id="`ae_datetime_${index}`"
                  v-model="row.dateTime"
                  label="Date-Time"
                  type="datetime-local"
                  size="sm"
                />
                <BaseInput
                  :id="`ae_category_${index}`"
                  :model-value="row.categoryId === null ? '' : String(row.categoryId)"
                  label="Category"
                  type="number"
                  size="sm"
                  placeholder="No category lookup yet"
                  @update:model-value="(v) => (row.categoryId = v === '' ? null : Number(v))"
                />
                <BaseInput
                  :id="`ae_amount_${index}`"
                  :model-value="row.amount === null ? '' : String(row.amount)"
                  label="Amount"
                  type="number"
                  step="0.01"
                  size="sm"
                  :error="fieldErrors[`amount.${index}`]"
                  @update:model-value="(v) => (row.amount = v === '' ? null : Number(v))"
                />
                <BaseInput
                  :id="`ae_remarks_${index}`"
                  v-model="row.remarks"
                  label="Remarks"
                  size="sm"
                />
                <button
                  type="button"
                  class="mb-1 justify-self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                  aria-label="Remove row"
                  :disabled="entries.length === 1"
                  @click="removeRow(index)"
                >
                  <Trash class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

          <div>
            <p class="mb-2 text-sm font-medium text-slate-700">Photos</p>
            <p class="mb-2 text-xs text-slate-400">Attached to the last row saved, if more than one.</p>

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
              class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400 transition-colors hover:border-brand-300"
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

          <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
            <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
            <BaseButton type="button" class="ml-auto" :disabled="isSaving" @click="handleSave">
              {{ isSaving ? 'Saving…' : 'Save' }}
            </BaseButton>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
