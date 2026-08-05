<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw, Image as ImageIcon } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDateTime } from '@/lib/date'
import type {
  ArithmeticOperation,
  BankDetail,
  BillPaymentCashType,
  CashLoanType,
  CashTxnDetail,
  CashTxnDetailFormValues,
  CashTxnEntryType,
  CashTxnImage,
  CashTxnSourceType,
  CashTxnType,
  DataTableColumn,
  UserDetail,
} from '@/types'

const auth = useAuthStore()
const toastStore = useToastStore()

const transactions = ref<CashTxnDetail[]>([])
const users = ref<UserDetail[]>([])
const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

/*
|--------------------------------------------------------------------------
| Option lists
|--------------------------------------------------------------------------
| These mirror the exact `in:` enum lists validated by
| CashTxnDetailController::storeValidator()/updateValidator() — nothing
| here is invented, only humanized for display.
*/

const typeOptions: { value: CashTxnType; label: string }[] = [
  { value: 'INCOME', label: 'Income' },
  { value: 'EXPENSE', label: 'Expense' },
  { value: 'AUTO_ENTRY', label: 'Auto Entry' },
  { value: 'CASH_TO_GOLD', label: 'Cash to Gold' },
  { value: 'PURCHASE_GOLD', label: 'Purchase Gold' },
  { value: 'SALE_GOLD', label: 'Sale Gold' },
  { value: 'AMOUNT_TRANSFER', label: 'Amount Transfer' },
  { value: 'GOLD_TO_CASH', label: 'Gold to Cash' },
  { value: 'INTERNAL_TRANSFER', label: 'Internal Transfer' },
  { value: 'IN_CASH_CONVERTER', label: 'In Cash Converter' },
  { value: 'OUT_CASH_CONVERTER', label: 'Out Cash Converter' },
  { value: 'CashToGold', label: 'Cash to Gold (legacy)' },
  { value: 'CASH_LOAN', label: 'Cash Loan' },
]
const typeLabel = (value: CashTxnType) => typeOptions.find((o) => o.value === value)?.label ?? value

// Only these two types actually drive balance math server-side — every
// other type stores the entry with closing balances equal to whatever
// opening values are submitted (CashTxnDetailController::calculateBalances).
const AUTO_CALC_TYPES: CashTxnType[] = ['INCOME', 'EXPENSE']

const sourceTypeOptions: { value: CashTxnSourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]

const txnTypeOptions: { value: CashTxnEntryType; label: string }[] = [
  { value: 'NORMAL', label: 'Normal' },
  { value: 'ATTENDANCE', label: 'Attendance' },
]

const billPaymentCashTypeOptions: { value: BillPaymentCashType; label: string }[] = [
  { value: 'ON_SPOT_NILL', label: 'On spot / nil' },
  { value: 'ON_ACCOUNTABLE', label: 'On accountable' },
  { value: 'PARTIAL_PAYMENT', label: 'Partial payment' },
]

const arithmeticOperationOptions: { value: ArithmeticOperation; label: string }[] = [
  { value: '+', label: '+ (add)' },
  { value: '-', label: '- (subtract)' },
]

const cashLoanTypeOptions: { value: CashLoanType; label: string }[] = [
  { value: 'cash_loan_taken', label: 'Cash loan taken' },
  { value: 'cash_loan_given', label: 'Cash loan given' },
  { value: 'interest_payment', label: 'Interest payment' },
  { value: 'interest_receipt', label: 'Interest receipt' },
]

function userLabel(user: UserDetail) {
  return `${user.name} (${user.user_name})`
}

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.user_id, label: userLabel(u) })),
)
const bankOptions = computed(() =>
  banks.value.map((b) => ({ value: b.bank_id, label: b.bank_name })),
)
const receiptTxnOptions = computed(() =>
  transactions.value.map((t) => ({
    value: t.txn_id,
    label: `#${t.txn_id} · ${typeLabel(t.type)} · ${t.amount}`,
  })),
)

function userName(id: number | null, cachedUser?: UserDetail | null) {
  if (id === null) return '—'
  return cachedUser?.name ?? users.value.find((u) => u.user_id === id)?.name ?? `#${id}`
}

/*
|--------------------------------------------------------------------------
| List + search
|--------------------------------------------------------------------------
*/

const columns: DataTableColumn<CashTxnDetail>[] = [
  { key: 'type', label: 'Type' },
  { key: 'given_by', label: 'Given by' },
  { key: 'given_to', label: 'Given to' },
  { key: 'amount', label: 'Amount' },
  { key: 'souce_type', label: 'Source' },
  { key: 'closing_account_balance', label: 'Balance' },
  { key: 'is_active', label: 'Status' },
  { key: 'added_at', label: 'Added' },
  { key: 'txn_id', label: '' },
]

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [txnData, usersData, banksData] = await Promise.all([
      cashTxnDetailsApi.list(),
      userDetailsApi.list(),
      bankDetailsApi.list(),
    ])
    transactions.value = txnData
    users.value = usersData
    banks.value = banksData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load cash transactions.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

const filteredTransactions = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return transactions.value
  return transactions.value.filter((t) => {
    const haystack = [
      typeLabel(t.type),
      t.remarks ?? '',
      t.givenByUser?.name ?? '',
      t.givenToUser?.name ?? '',
      String(t.txn_id),
    ]
      .join(' ')
      .toLowerCase()
    return haystack.includes(query)
  })
})

/*
|--------------------------------------------------------------------------
| Form state
|--------------------------------------------------------------------------
*/

function makeEmptyForm(): CashTxnDetailFormValues {
  const latestBalance = transactions.value[0]?.closing_account_balance ?? 0
  return {
    type: 'INCOME',
    given_to: null,
    given_by: null,
    category_id: null,
    amount: null,

    opening_account_balance: latestBalance,
    opening_user_balance: 0,
    opening_bank_account_balance: null,
    opening_bank_user_balance: null,

    souce_type: 'CASH_ON_HAND',
    bank_id: null,
    bank_name: '',

    remarks: '',
    remainder: '',
    remainder_at: '',

    added_by: auth.user?.user_id ?? null,

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

const isFormOpen = ref(false)
const isAdvancedOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<CashTxnDetailFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})
const editingImages = ref<CashTxnImage[]>([])
const pendingImages = ref<File[]>([])

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function openCreateForm() {
  editingId.value = null
  Object.assign(form, makeEmptyForm())
  formError.value = ''
  clearFieldErrors()
  editingImages.value = []
  pendingImages.value = []
  isAdvancedOpen.value = false
  isFormOpen.value = true
}

function openEditForm(txn: CashTxnDetail) {
  editingId.value = txn.txn_id
  Object.assign(form, {
    type: txn.type,
    given_to: txn.given_to,
    given_by: txn.given_by,
    category_id: txn.category_id,
    amount: txn.amount,

    opening_account_balance: txn.opening_account_balance,
    opening_user_balance: txn.opening_user_balance,
    opening_bank_account_balance: txn.opening_bank_account_balance,
    opening_bank_user_balance: txn.opening_bank_user_balance,

    souce_type: txn.souce_type,
    bank_id: txn.bank_id,
    bank_name: txn.bank?.bank_name ?? '',

    remarks: txn.remarks ?? '',
    remainder: txn.remainder ?? '',
    remainder_at: txn.remainder_at ? txn.remainder_at.slice(0, 10) : '',

    added_by: txn.added_by,

    is_active: txn.is_active,
    is_hidden: txn.is_hidden,
    is_show_to_all: txn.is_show_to_all,

    amount_transfer_id: txn.amount_transfer_id,
    cash_to_gold_id: txn.cash_to_gold_id,
    stock_id: txn.stock_id,
    amnt_transfer_from_head: txn.amnt_transfer_from_head,
    internal_type: txn.internal_type ?? '',

    retailer_id: txn.retailer_id,
    retailer_ob_cash_balance: txn.retailer_ob_cash_balance,
    retailer_ob_rtgs_balance: txn.retailer_ob_rtgs_balance,

    txn_type: txn.txn_type,
    bank_entry_date: txn.bank_entry_date ? txn.bank_entry_date.slice(0, 10) : '',

    machine_vendor_id: txn.machine_vendor_id,
    machine_vendor_ob_cash_balance: txn.machine_vendor_ob_cash_balance,
    machine_vendor_ob_rtgs_balance: txn.machine_vendor_ob_rtgs_balance,

    is_bill_cash: txn.is_bill_cash ?? false,
    is_payment_cash: txn.is_payment_cash ?? false,
    is_customer_affect: txn.is_customer_affect ?? false,
    is_need_receipt: txn.is_need_receipt ?? false,
    bill_payment_cash_type: txn.bill_payment_cash_type ?? '',
    partial_amount: txn.partial_amount,
    actual_amount: txn.actual_amount,
    receipt_cash_txn_id: txn.receipt_cash_txn_id,

    given_by_arithmetic_operation: txn.given_by_arithmetic_operation ?? '',
    given_to_arithmetic_operation: txn.given_to_arithmetic_operation ?? '',

    cash_loan_type: txn.cash_loan_type ?? '',
    per_gram_cash: txn.per_gram_cash,

    over_all_bill_id: txn.over_all_bill_id,
    estimate_retailer_bill_id: txn.estimate_retailer_bill_id,
    estimate_metal_bill_id: txn.estimate_metal_bill_id,

    is_admin_head_entry: txn.is_admin_head_entry,
    admin_head_txn_id: txn.admin_head_txn_id,
  })
  formError.value = ''
  clearFieldErrors()
  editingImages.value = [...txn.images]
  pendingImages.value = []
  isAdvancedOpen.value = false
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

// Prefill helpers — convenience defaults only; every field stays editable
// and nothing here is enforced server-side.
function onGivenByChange(userId: number | null) {
  form.given_by = userId
  if (editingId.value !== null) return
  const user = users.value.find((u) => u.user_id === userId)
  if (user) form.opening_user_balance = user.rak_cash_balance
}

function onBankChange(bankId: number | null) {
  form.bank_id = bankId
  const bank = banks.value.find((b) => b.bank_id === bankId)
  form.bank_name = bank?.bank_name ?? ''
  if (bank && editingId.value === null) {
    form.opening_bank_account_balance = bank.current_balance
  }
}

/*
|--------------------------------------------------------------------------
| Live closing-balance preview
|--------------------------------------------------------------------------
| Mirrors CashTxnDetailController::calculateBalances() exactly: cash math
| runs whenever type is INCOME/EXPENSE, bank math runs additionally when
| souce_type is BANK. Every other type is a manual entry — closing equals
| opening. Purely informational; the backend recalculates authoritatively.
*/

const balancePreview = computed(() => {
  const amount = form.amount ?? 0
  const openingAccount = form.opening_account_balance ?? 0
  const openingUser = form.opening_user_balance ?? 0
  const openingBankAccount = form.opening_bank_account_balance ?? 0
  const openingBankUser = form.opening_bank_user_balance ?? 0

  let closingAccount = openingAccount
  let closingUser = openingUser
  let closingBankAccount = openingBankAccount
  let closingBankUser = openingBankUser

  if (form.type === 'INCOME') {
    closingAccount = openingAccount + amount
    closingUser = openingUser - amount
  } else if (form.type === 'EXPENSE') {
    closingAccount = openingAccount - amount
    closingUser = openingUser + amount
  }

  if (form.souce_type === 'BANK') {
    if (form.type === 'INCOME') {
      closingBankAccount = openingBankAccount + amount
      closingBankUser = openingBankUser - amount
    } else if (form.type === 'EXPENSE') {
      closingBankAccount = openingBankAccount - amount
      closingBankUser = openingBankUser + amount
    }
  }

  return { closingAccount, closingUser, closingBankAccount, closingBankUser }
})

const isAutoCalcType = computed(() => AUTO_CALC_TYPES.includes(form.type))

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
| Mirrors CashTxnDetailController::storeValidator()/updateValidator():
| core fields + arithmetic amount are always required, bank_id/bank_name/
| opening_bank_account_balance are required only when souce_type is BANK,
| and remarks/remainder carry the same max lengths as the backend.
*/

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (!form.type) fieldErrors.type = 'Transaction type is required.'
  if (form.given_by === null) fieldErrors.given_by = 'Given by user is required.'
  if (form.given_to === null) fieldErrors.given_to = 'Given to user is required.'
  if (form.added_by === null) fieldErrors.added_by = 'Added by user is required.'

  if (form.amount === null || Number.isNaN(form.amount)) {
    fieldErrors.amount = 'Amount is required.'
  } else if (form.amount < 0) {
    fieldErrors.amount = 'Amount cannot be negative.'
  }

  if (form.opening_account_balance === null) {
    fieldErrors.opening_account_balance = 'Opening account balance is required.'
  }
  if (form.opening_user_balance === null) {
    fieldErrors.opening_user_balance = 'Opening user balance is required.'
  }
  if (!form.souce_type) fieldErrors.souce_type = 'Source type is required.'

  if (form.souce_type === 'BANK') {
    if (form.bank_id === null) fieldErrors.bank_id = 'Select a bank.'
    if (form.opening_bank_account_balance === null) {
      fieldErrors.opening_bank_account_balance = 'Opening bank balance is required.'
    }
  }

  if (form.remarks.length > 5000) {
    fieldErrors.remarks = 'Remarks must be 5000 characters or fewer.'
  }
  if (form.remainder.length > 1500) {
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

/*
|--------------------------------------------------------------------------
| Images
|--------------------------------------------------------------------------
*/

// Mirrors CashTxnDetailController::addImages()'s validator: image,
// mimes:jpg,jpeg,png,webp, max:5120 (KB). Catching this client-side avoids
// a generic 422 after the fact — and since the backend validates the whole
// batch before storing anything, one bad file would otherwise reject every
// good file selected alongside it.
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_IMAGE_SIZE_BYTES = 5120 * 1024

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) {
    for (const file of Array.from(input.files)) {
      if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
        toastStore.show(`${file.name}: unsupported file type. Use JPG, PNG or WEBP.`, 'error')
        continue
      }
      if (file.size > MAX_IMAGE_SIZE_BYTES) {
        toastStore.show(`${file.name}: file is larger than 5MB.`, 'error')
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

const removingImageId = ref<number | null>(null)

async function handleRemoveExistingImage(image: CashTxnImage) {
  removingImageId.value = image.image_id
  try {
    await cashTxnDetailsApi.deleteImage(image.image_id)
    editingImages.value = editingImages.value.filter((img) => img.image_id !== image.image_id)
    toastStore.show('Image removed.', 'success')
  } catch (err) {
    toastStore.show(err instanceof ApiError ? err.message : 'Failed to remove image.', 'error')
  } finally {
    removingImageId.value = null
  }
}

/*
|--------------------------------------------------------------------------
| Save / delete
|--------------------------------------------------------------------------
*/

async function handleSubmit() {
  if (!validate()) return

  isSaving.value = true
  try {
    const saved =
      editingId.value !== null
        ? await cashTxnDetailsApi.update(editingId.value, { ...form })
        : await cashTxnDetailsApi.create({ ...form })

    if (pendingImages.value.length) {
      await cashTxnDetailsApi.addImages(saved.txn_id, pendingImages.value)
      pendingImages.value = []
    }

    isFormOpen.value = false
    toastStore.show(
      editingId.value !== null
        ? 'Transaction updated successfully.'
        : 'Transaction created successfully.',
      'success',
    )
    await loadData()
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

const deletingId = ref<number | null>(null)

async function handleDelete(txn: CashTxnDetail) {
  deletingId.value = txn.txn_id
  try {
    await cashTxnDetailsApi.remove(txn.txn_id)
    await loadData()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete transaction.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Cash Transactions"
      description="Record cash and bank ledger entries with automatic balance tracking."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New transaction</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit transaction' : 'New transaction' }}
        </h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close form"
          @click="closeForm"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <p
        v-if="formError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ formError }}
      </p>

      <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <!-- Core transaction -->
        <section class="grid gap-3 sm:grid-cols-3">
          <BaseSelect
            id="type"
            :model-value="form.type"
            label="Type"
            required
            size="sm"
            :options="typeOptions"
            :error="fieldErrors.type"
            @update:model-value="(v) => (form.type = v as CashTxnType)"
          />
          <BaseSelect
            id="given_by"
            :model-value="form.given_by"
            label="Given by"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.given_by"
            @update:model-value="(v) => onGivenByChange(v as number | null)"
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
          <BaseInput
            id="amount"
            :model-value="form.amount === null ? '' : String(form.amount)"
            label="Amount"
            type="number"
            step="0.01"
            required
            size="sm"
            :error="fieldErrors.amount"
            @update:model-value="(v) => (form.amount = v === '' ? null : Number(v))"
          />
          <BaseSelect
            id="added_by"
            :model-value="form.added_by"
            label="Added by"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.added_by"
            @update:model-value="(v) => (form.added_by = v as number | null)"
          />
          <BaseSelect
            id="txn_type"
            :model-value="form.txn_type"
            label="Entry type"
            size="sm"
            :options="txnTypeOptions"
            @update:model-value="(v) => (form.txn_type = v as CashTxnEntryType)"
          />
        </section>

        <p v-if="!isAutoCalcType" class="-mt-3 text-xs text-amber-700">
          Only Income/Expense auto-calculate closing balances. For this type, closing balances
          are stored exactly as entered below.
        </p>

        <!-- Source & balances -->
        <section class="border-t border-slate-200 pt-4">
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Source &amp; balances</h3>
          <div class="grid gap-3 sm:grid-cols-3">
            <BaseSelect
              id="souce_type"
              :model-value="form.souce_type"
              label="Source"
              required
              size="sm"
              :options="sourceTypeOptions"
              :error="fieldErrors.souce_type"
              @update:model-value="(v) => (form.souce_type = v as CashTxnSourceType)"
            />
            <BaseInput
              id="opening_account_balance"
              :model-value="
                form.opening_account_balance === null ? '' : String(form.opening_account_balance)
              "
              label="Opening account balance"
              type="number"
              step="0.01"
              required
              size="sm"
              :error="fieldErrors.opening_account_balance"
              @update:model-value="
                (v) => (form.opening_account_balance = v === '' ? null : Number(v))
              "
            />
            <BaseInput
              id="opening_user_balance"
              :model-value="
                form.opening_user_balance === null ? '' : String(form.opening_user_balance)
              "
              label="Opening user balance"
              type="number"
              step="0.01"
              required
              size="sm"
              :error="fieldErrors.opening_user_balance"
              @update:model-value="
                (v) => (form.opening_user_balance = v === '' ? null : Number(v))
              "
            />

            <template v-if="form.souce_type === 'BANK'">
              <BaseSelect
                id="bank_id"
                :model-value="form.bank_id"
                label="Bank"
                required
                size="sm"
                placeholder="Select a bank…"
                :options="bankOptions"
                :error="fieldErrors.bank_id"
                @update:model-value="(v) => onBankChange(v as number | null)"
              />
              <BaseInput
                id="opening_bank_account_balance"
                :model-value="
                  form.opening_bank_account_balance === null
                    ? ''
                    : String(form.opening_bank_account_balance)
                "
                label="Opening bank balance"
                type="number"
                step="0.01"
                required
                size="sm"
                :error="fieldErrors.opening_bank_account_balance"
                @update:model-value="
                  (v) => (form.opening_bank_account_balance = v === '' ? null : Number(v))
                "
              />
              <BaseInput
                id="opening_bank_user_balance"
                :model-value="
                  form.opening_bank_user_balance === null
                    ? ''
                    : String(form.opening_bank_user_balance)
                "
                label="Opening bank user balance"
                type="number"
                step="0.01"
                size="sm"
                @update:model-value="
                  (v) => (form.opening_bank_user_balance = v === '' ? null : Number(v))
                "
              />
            </template>
          </div>

          <div class="mt-3 grid gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm sm:grid-cols-4">
            <div>
              <p class="text-xs text-slate-500">Closing account balance</p>
              <p class="font-medium text-slate-900">{{ balancePreview.closingAccount }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Closing user balance</p>
              <p class="font-medium text-slate-900">{{ balancePreview.closingUser }}</p>
            </div>
            <template v-if="form.souce_type === 'BANK'">
              <div>
                <p class="text-xs text-slate-500">Closing bank balance</p>
                <p class="font-medium text-slate-900">{{ balancePreview.closingBankAccount }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Closing bank user balance</p>
                <p class="font-medium text-slate-900">{{ balancePreview.closingBankUser }}</p>
              </div>
            </template>
          </div>
        </section>

        <!-- Status & reminder -->
        <section class="border-t border-slate-200 pt-4">
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Status &amp; reminder</h3>
          <div class="grid gap-3 sm:grid-cols-3">
            <BaseTextarea
              id="remarks"
              v-model="form.remarks"
              label="Remarks"
              size="sm"
              :rows="2"
              class="sm:col-span-3"
              :error="fieldErrors.remarks"
            />
            <BaseInput
              id="remainder"
              v-model="form.remainder"
              label="Reminder note"
              size="sm"
              placeholder="Optional"
              :error="fieldErrors.remainder"
            />
            <BaseInput
              id="remainder_at"
              v-model="form.remainder_at"
              label="Reminder date"
              type="date"
              size="sm"
            />
          </div>
          <div class="mt-3 flex flex-wrap gap-6">
            <BaseCheckbox v-model="form.is_active" label="Active" />
            <BaseCheckbox v-model="form.is_hidden" label="Hidden" />
            <BaseCheckbox v-model="form.is_show_to_all" label="Show to all" />
            <BaseCheckbox v-model="form.is_admin_head_entry" label="Admin head entry" />
          </div>
        </section>

        <!-- Advanced / linked records -->
        <section class="border-t border-slate-200 pt-4">
          <button
            type="button"
            class="mb-3 text-sm font-semibold text-brand-700 hover:text-brand-800"
            @click="isAdvancedOpen = !isAdvancedOpen"
          >
            {{ isAdvancedOpen ? 'Hide' : 'Show' }} advanced / linked-record fields
          </button>

          <div v-if="isAdvancedOpen" class="flex flex-col gap-6">
            <div class="grid gap-3 sm:grid-cols-3">
              <BaseInput
                id="category_id"
                :model-value="form.category_id === null ? '' : String(form.category_id)"
                label="Category ID"
                type="number"
                size="sm"
                placeholder="No category lookup yet"
                @update:model-value="(v) => (form.category_id = v === '' ? null : Number(v))"
              />
              <BaseInput
                id="amount_transfer_id"
                :model-value="
                  form.amount_transfer_id === null ? '' : String(form.amount_transfer_id)
                "
                label="Amount transfer ID"
                type="number"
                size="sm"
                @update:model-value="
                  (v) => (form.amount_transfer_id = v === '' ? null : Number(v))
                "
              />
              <BaseCheckbox
                v-model="form.amnt_transfer_from_head"
                label="Amount transfer from head"
                class="self-end pb-2"
              />

              <BaseInput
                id="cash_to_gold_id"
                :model-value="form.cash_to_gold_id === null ? '' : String(form.cash_to_gold_id)"
                label="Cash-to-gold ID"
                type="number"
                size="sm"
                @update:model-value="(v) => (form.cash_to_gold_id = v === '' ? null : Number(v))"
              />
              <BaseInput
                id="stock_id"
                :model-value="form.stock_id === null ? '' : String(form.stock_id)"
                label="Stock ID"
                type="number"
                size="sm"
                @update:model-value="(v) => (form.stock_id = v === '' ? null : Number(v))"
              />
              <BaseInput
                id="internal_type"
                v-model="form.internal_type"
                label="Internal type"
                size="sm"
              />

              <BaseInput
                id="per_gram_cash"
                :model-value="form.per_gram_cash === null ? '' : String(form.per_gram_cash)"
                label="Per-gram cash"
                type="number"
                step="0.01"
                size="sm"
                @update:model-value="(v) => (form.per_gram_cash = v === '' ? null : Number(v))"
              />
              <BaseSelect
                id="cash_loan_type"
                :model-value="form.cash_loan_type"
                label="Cash loan type"
                size="sm"
                placeholder="Not a loan entry"
                :options="cashLoanTypeOptions"
                @update:model-value="(v) => (form.cash_loan_type = v as CashLoanType | '')"
              />
              <BaseInput
                id="bank_entry_date"
                v-model="form.bank_entry_date"
                label="Bank entry date"
                type="date"
                size="sm"
              />
            </div>

            <div class="border-t border-slate-100 pt-4">
              <h4 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Retailer
              </h4>
              <div class="grid gap-3 sm:grid-cols-3">
                <BaseInput
                  id="retailer_id"
                  :model-value="form.retailer_id === null ? '' : String(form.retailer_id)"
                  label="Retailer ID"
                  type="number"
                  size="sm"
                  @update:model-value="(v) => (form.retailer_id = v === '' ? null : Number(v))"
                />
                <BaseInput
                  id="retailer_ob_cash_balance"
                  :model-value="
                    form.retailer_ob_cash_balance === null
                      ? ''
                      : String(form.retailer_ob_cash_balance)
                  "
                  label="Retailer opening cash balance"
                  type="number"
                  step="0.01"
                  size="sm"
                  @update:model-value="
                    (v) => (form.retailer_ob_cash_balance = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="retailer_ob_rtgs_balance"
                  :model-value="
                    form.retailer_ob_rtgs_balance === null
                      ? ''
                      : String(form.retailer_ob_rtgs_balance)
                  "
                  label="Retailer opening RTGS balance"
                  type="number"
                  step="0.01"
                  size="sm"
                  @update:model-value="
                    (v) => (form.retailer_ob_rtgs_balance = v === '' ? null : Number(v))
                  "
                />
              </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
              <h4 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Machine vendor
              </h4>
              <div class="grid gap-3 sm:grid-cols-3">
                <BaseInput
                  id="machine_vendor_id"
                  :model-value="
                    form.machine_vendor_id === null ? '' : String(form.machine_vendor_id)
                  "
                  label="Machine vendor ID"
                  type="number"
                  size="sm"
                  @update:model-value="
                    (v) => (form.machine_vendor_id = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="machine_vendor_ob_cash_balance"
                  :model-value="
                    form.machine_vendor_ob_cash_balance === null
                      ? ''
                      : String(form.machine_vendor_ob_cash_balance)
                  "
                  label="Vendor opening cash balance"
                  type="number"
                  step="0.01"
                  size="sm"
                  @update:model-value="
                    (v) => (form.machine_vendor_ob_cash_balance = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="machine_vendor_ob_rtgs_balance"
                  :model-value="
                    form.machine_vendor_ob_rtgs_balance === null
                      ? ''
                      : String(form.machine_vendor_ob_rtgs_balance)
                  "
                  label="Vendor opening RTGS balance"
                  type="number"
                  step="0.01"
                  size="sm"
                  @update:model-value="
                    (v) => (form.machine_vendor_ob_rtgs_balance = v === '' ? null : Number(v))
                  "
                />
              </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
              <h4 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Bill &amp; payment
              </h4>
              <div class="grid gap-3 sm:grid-cols-3">
                <BaseSelect
                  id="bill_payment_cash_type"
                  :model-value="form.bill_payment_cash_type"
                  label="Bill payment cash type"
                  size="sm"
                  placeholder="None"
                  :options="billPaymentCashTypeOptions"
                  @update:model-value="
                    (v) => (form.bill_payment_cash_type = v as BillPaymentCashType | '')
                  "
                />
                <BaseInput
                  id="partial_amount"
                  :model-value="form.partial_amount === null ? '' : String(form.partial_amount)"
                  label="Partial amount"
                  type="number"
                  step="0.01"
                  size="sm"
                  @update:model-value="
                    (v) => (form.partial_amount = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="actual_amount"
                  :model-value="form.actual_amount === null ? '' : String(form.actual_amount)"
                  label="Actual amount"
                  type="number"
                  step="0.01"
                  size="sm"
                  @update:model-value="(v) => (form.actual_amount = v === '' ? null : Number(v))"
                />
                <BaseSelect
                  id="receipt_cash_txn_id"
                  :model-value="form.receipt_cash_txn_id"
                  label="Receipt for transaction"
                  size="sm"
                  placeholder="None"
                  :options="receiptTxnOptions"
                  @update:model-value="
                    (v) => (form.receipt_cash_txn_id = v as number | null)
                  "
                />
              </div>
              <div class="mt-3 flex flex-wrap gap-6">
                <BaseCheckbox v-model="form.is_bill_cash" label="Is bill cash" />
                <BaseCheckbox v-model="form.is_payment_cash" label="Is payment cash" />
                <BaseCheckbox v-model="form.is_customer_affect" label="Affects customer" />
                <BaseCheckbox v-model="form.is_need_receipt" label="Needs receipt" />
              </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
              <h4 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Arithmetic &amp; bill links
              </h4>
              <div class="grid gap-3 sm:grid-cols-3">
                <BaseSelect
                  id="given_by_arithmetic_operation"
                  :model-value="form.given_by_arithmetic_operation"
                  label="Given-by operation"
                  size="sm"
                  placeholder="None"
                  :options="arithmeticOperationOptions"
                  @update:model-value="
                    (v) => (form.given_by_arithmetic_operation = v as ArithmeticOperation | '')
                  "
                />
                <BaseSelect
                  id="given_to_arithmetic_operation"
                  :model-value="form.given_to_arithmetic_operation"
                  label="Given-to operation"
                  size="sm"
                  placeholder="None"
                  :options="arithmeticOperationOptions"
                  @update:model-value="
                    (v) => (form.given_to_arithmetic_operation = v as ArithmeticOperation | '')
                  "
                />
                <BaseInput
                  id="admin_head_txn_id"
                  :model-value="
                    form.admin_head_txn_id === null ? '' : String(form.admin_head_txn_id)
                  "
                  label="Admin head transaction ID"
                  type="number"
                  size="sm"
                  @update:model-value="
                    (v) => (form.admin_head_txn_id = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="over_all_bill_id"
                  :model-value="
                    form.over_all_bill_id === null ? '' : String(form.over_all_bill_id)
                  "
                  label="Overall bill ID"
                  type="number"
                  size="sm"
                  @update:model-value="
                    (v) => (form.over_all_bill_id = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="estimate_retailer_bill_id"
                  :model-value="
                    form.estimate_retailer_bill_id === null
                      ? ''
                      : String(form.estimate_retailer_bill_id)
                  "
                  label="Estimate retailer bill ID"
                  type="number"
                  size="sm"
                  @update:model-value="
                    (v) => (form.estimate_retailer_bill_id = v === '' ? null : Number(v))
                  "
                />
                <BaseInput
                  id="estimate_metal_bill_id"
                  :model-value="
                    form.estimate_metal_bill_id === null
                      ? ''
                      : String(form.estimate_metal_bill_id)
                  "
                  label="Estimate metal bill ID"
                  type="number"
                  size="sm"
                  @update:model-value="
                    (v) => (form.estimate_metal_bill_id = v === '' ? null : Number(v))
                  "
                />
              </div>
            </div>
          </div>
        </section>

        <!-- Images -->
        <section class="border-t border-slate-200 pt-4">
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Images</h3>

          <div v-if="editingImages.length" class="mb-3 flex flex-wrap gap-3">
            <div
              v-for="image in editingImages"
              :key="image.image_id"
              class="relative h-20 w-20 overflow-hidden rounded-lg border border-slate-200"
            >
              <img :src="image.image_full_url" class="h-full w-full object-cover" alt="" />
              <button
                type="button"
                class="absolute top-0.5 right-0.5 rounded-full bg-white/90 p-0.5 text-slate-500 hover:text-red-600 disabled:opacity-50"
                aria-label="Remove image"
                :disabled="removingImageId === image.image_id"
                @click="handleRemoveExistingImage(image)"
              >
                <X class="h-3.5 w-3.5" />
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

          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            class="block w-full max-w-sm text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
            @change="handleFileSelect"
          />
          <p class="mt-1 text-xs text-slate-500">JPG, PNG or WEBP, up to 5MB each.</p>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create transaction' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="closeForm">Cancel</BaseButton>
        </div>
      </form>
    </BaseCard>

    <div class="mb-4 flex items-center gap-2">
      <BaseInput
        v-model="searchQuery"
        type="search"
        :icon="Search"
        placeholder="Search transactions…"
        aria-label="Search transactions"
        class="w-full max-w-xs"
      />
    </div>

    <div
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </div>

    <div
      v-if="isLoading"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading cash transactions…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredTransactions"
      empty-message="No cash transactions yet. Add your first one to get started."
    >
      <template #type="{ value }">{{ typeLabel(value as CashTxnType) }}</template>

      <template #given_by="{ row }">
        {{ userName((row as CashTxnDetail).given_by, (row as CashTxnDetail).givenByUser) }}
      </template>

      <template #given_to="{ row }">
        {{ userName((row as CashTxnDetail).given_to, (row as CashTxnDetail).givenToUser) }}
      </template>

      <template #amount="{ value }">{{ Number(value).toLocaleString() }}</template>

      <template #souce_type="{ row }">
        {{
          (row as CashTxnDetail).souce_type === 'BANK'
            ? `Bank · ${(row as CashTxnDetail).bank?.bank_name ?? '#' + (row as CashTxnDetail).bank_id}`
            : 'Cash on hand'
        }}
      </template>

      <template #closing_account_balance="{ value }">
        {{ Number(value).toLocaleString() }}
      </template>

      <template #is_active="{ row }">
        <div class="flex flex-wrap gap-1">
          <span
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="
              (row as CashTxnDetail).is_active
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            {{ (row as CashTxnDetail).is_active ? 'Active' : 'Inactive' }}
          </span>
          <span
            v-if="(row as CashTxnDetail).is_hidden"
            class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700"
          >
            Hidden
          </span>
        </div>
      </template>

      <template #added_at="{ value }">{{ formatDateTime(value as string) }}</template>

      <template #txn_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit transaction"
            @click="openEditForm(row as CashTxnDetail)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete transaction #${(row as CashTxnDetail).txn_id}? This restores the affected balances.`"
            @confirm="handleDelete(row as CashTxnDetail)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete transaction"
                :disabled="deletingId === (row as CashTxnDetail).txn_id"
                @click="toggle"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </ConfirmPopover>
        </div>
      </template>
    </DataTable>
  </div>
</template>
