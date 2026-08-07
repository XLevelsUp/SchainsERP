<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw, Printer, Image as ImageIcon } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import TransactionEntryModal from '@/components/cash-txn/TransactionEntryModal.vue'
import PurchaseGoldModal from '@/components/cash-txn/PurchaseGoldModal.vue'
import GoldToCashModal from '@/components/cash-txn/GoldToCashModal.vue'
import CashToGoldModal from '@/components/cash-txn/CashToGoldModal.vue'
import SaleGoldQuickModal from '@/components/cash-txn/SaleGoldQuickModal.vue'
import AutoEntryModal from '@/components/cash-txn/AutoEntryModal.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { ApiError } from '@/lib/api'
import { validateImageFile } from '@/lib/imageValidation'
import {
  typeOptions,
  typeLabel,
  AUTO_CALC_TYPES,
  OUT_TYPES,
  IN_TYPES,
  sourceTypeOptions,
  txnTypeOptions,
  billPaymentCashTypeOptions,
  arithmeticOperationOptions,
  cashLoanTypeOptions,
} from '@/lib/cashTxnOptions'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
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
  Item,
  UserDetail,
} from '@/types'

const auth = useAuthStore()
const toastStore = useToastStore()

const transactions = ref<CashTxnDetail[]>([])
const users = ref<UserDetail[]>([])
const items = ref<Item[]>([])
const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')

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
function categoryLabel(id: number | null) {
  return id === null ? '—' : String(id)
}

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [txnData, usersData, banksData, itemsData] = await Promise.all([
      cashTxnDetailsApi.list(),
      userDetailsApi.list(),
      bankDetailsApi.list(),
      itemsApi.list(),
    ])
    transactions.value = txnData
    users.value = usersData
    banks.value = banksData
    items.value = itemsData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load cash transactions.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

/*
|--------------------------------------------------------------------------
| Filter bar — Head / User / date range
|--------------------------------------------------------------------------
| "Roles" is intentionally left out here: GET /api/v1/roles is currently
| 404 (route dropped in a backend merge), so a Role→User cascade can't be
| built against it. Head/User selects both work today.
*/

const headId = ref<number | null>(null)
const counterpartyId = ref<number | null>(null)
const fromDate = ref('')
const toDate = ref('')
const searchQuery = ref('')

const headUser = computed(() => users.value.find((u) => u.user_id === headId.value) ?? null)
const counterpartyUser = computed(
  () => users.value.find((u) => u.user_id === counterpartyId.value) ?? null,
)
const canQuickCreate = computed(() => headUser.value !== null && counterpartyUser.value !== null)

function onHeadFilterChange(id: number | null) {
  headId.value = id
  counterpartyId.value = null
}

const scopedTransactions = computed(() => {
  if (headId.value === null) return []
  const query = searchQuery.value.trim().toLowerCase()
  return transactions.value.filter((t) => {
    const involvesHead = t.given_by === headId.value || t.given_to === headId.value
    if (!involvesHead) return false

    if (counterpartyId.value !== null) {
      const involvesCounterparty =
        t.given_by === counterpartyId.value || t.given_to === counterpartyId.value
      if (!involvesCounterparty) return false
    }

    const day = t.added_at?.slice(0, 10)
    if (fromDate.value && (!day || day < fromDate.value)) return false
    if (toDate.value && (!day || day > toDate.value)) return false

    if (query) {
      const haystack = [
        typeLabel(t.type),
        t.remarks ?? '',
        t.given_by_user?.name ?? '',
        t.given_to_user?.name ?? '',
        String(t.txn_id),
      ]
        .join(' ')
        .toLowerCase()
      if (!haystack.includes(query)) return false
    }

    return true
  })
})

const outRows = computed(() => scopedTransactions.value.filter((t) => OUT_TYPES.includes(t.type)))
const inRows = computed(() => scopedTransactions.value.filter((t) => IN_TYPES.includes(t.type)))
const outTotal = computed(() => outRows.value.reduce((sum, t) => sum + t.amount, 0))
const inTotal = computed(() => inRows.value.reduce((sum, t) => sum + t.amount, 0))

const PAGE_SIZE = 10
const outVisible = ref(PAGE_SIZE)
const inVisible = ref(PAGE_SIZE)
const visibleOutRows = computed(() => outRows.value.slice(0, outVisible.value))
const visibleInRows = computed(() => inRows.value.slice(0, inVisible.value))

function handlePrint() {
  window.print()
}

/*
|--------------------------------------------------------------------------
| Quick-action buttons — one-for-one with the legacy dashboard's IN/OUT tab
| groups (see lib/cashTxnOptions.ts). SaleGold links to its own page (it
| already has a full multi payment-source flow). Purchase Gold, Gold To
| Cash, and Cash To Gold each open their own dedicated (UI-only, unwired)
| modal — none of the three have a backend endpoint yet.
|--------------------------------------------------------------------------
*/

const outActions: { type: CashTxnType; label: string; classes: string }[] = [
  { type: 'EXPENSE', label: 'OUT', classes: 'bg-red-600 hover:bg-red-700' },
]
const inActions: { type: CashTxnType; label: string; classes: string }[] = [
  { type: 'INCOME', label: 'IN', classes: 'bg-emerald-600 hover:bg-emerald-700' },
]

/*
|--------------------------------------------------------------------------
| Quick-create / edit modal
|--------------------------------------------------------------------------
*/

const isModalOpen = ref(false)
const modalType = ref<CashTxnType>('EXPENSE')
const editingTxn = ref<CashTxnDetail | null>(null)
const modalHead = ref<UserDetail | null>(null)
const modalCounterparty = ref<UserDetail | null>(null)

function openQuickCreate(type: CashTxnType) {
  if (!headUser.value || !counterpartyUser.value) {
    toastStore.show('Select a Head and a User first.', 'error')
    return
  }
  modalType.value = type
  editingTxn.value = null
  modalHead.value = headUser.value
  modalCounterparty.value = counterpartyUser.value
  isModalOpen.value = true
}

// Purchase Gold has no backend endpoint yet (no controller, no route) —
// this opens a UI-only form matching the legacy dialog's layout so it's
// ready to wire up once that flow exists. See PurchaseGoldModal.vue.
const isPurchaseGoldModalOpen = ref(false)

function openPurchaseGoldModal() {
  if (!headUser.value || !counterpartyUser.value) {
    toastStore.show('Select a Head and a User first.', 'error')
    return
  }
  isPurchaseGoldModalOpen.value = true
}

// Gold To Cash has no backend endpoint either — same UI-only situation.
const isGoldToCashModalOpen = ref(false)

function openGoldToCashModal() {
  if (!headUser.value || !counterpartyUser.value) {
    toastStore.show('Select a Head and a User first.', 'error')
    return
  }
  isGoldToCashModalOpen.value = true
}

// Cash To Gold has no backend endpoint either — same UI-only situation.
const isCashToGoldModalOpen = ref(false)

function openCashToGoldModal() {
  if (!headUser.value || !counterpartyUser.value) {
    toastStore.show('Select a Head and a User first.', 'error')
    return
  }
  isCashToGoldModalOpen.value = true
}

// Sale Gold has a real backend (SaleGoldController) — this opens a
// functional in-context modal instead of navigating to the /sale-gold page.
const isSaleGoldModalOpen = ref(false)

function openSaleGoldModal() {
  if (!headUser.value || !counterpartyUser.value) {
    toastStore.show('Select a Head and a User first.', 'error')
    return
  }
  isSaleGoldModalOpen.value = true
}

async function handleSaleGoldSaved() {
  isSaleGoldModalOpen.value = false
  await loadData()
}

// Auto Entry reuses the real cashTxnDetailsApi (type=AUTO_ENTRY is already
// a working path) — see AutoEntryModal.vue for why it's its own component
// rather than TransactionEntryModal (the legacy dialog batches rows).
const isAutoEntryModalOpen = ref(false)

function openAutoEntryModal() {
  if (!headUser.value || !counterpartyUser.value) {
    toastStore.show('Select a Head and a User first.', 'error')
    return
  }
  isAutoEntryModalOpen.value = true
}

async function handleAutoEntrySaved() {
  isAutoEntryModalOpen.value = false
  await loadData()
}

function openEditRow(txn: CashTxnDetail) {
  const head = users.value.find((u) => u.user_id === txn.given_by) ?? null
  const counterparty = users.value.find((u) => u.user_id === txn.given_to) ?? null
  if (!head || !counterparty) {
    toastStore.show('Could not resolve the users on this transaction.', 'error')
    return
  }
  modalType.value = txn.type
  editingTxn.value = txn
  modalHead.value = head
  modalCounterparty.value = counterparty
  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
}

async function handleModalSaved() {
  isModalOpen.value = false
  await loadData()
}

function handleUseAdvancedForm() {
  isModalOpen.value = false
  if (editingTxn.value) {
    openEditForm(editingTxn.value)
  } else {
    openCreateForm()
    form.given_by = modalHead.value?.user_id ?? null
    form.given_to = modalCounterparty.value?.user_id ?? null
    form.type = modalType.value
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

/*
|--------------------------------------------------------------------------
| Advanced form — the full field set (retailer/vendor/bill-split/arithmetic
| links/etc.), reachable via "Advanced entry" or the modal's escape hatch
| for the rare entries the streamlined modal above doesn't cover.
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
</script>

<template>
  <div>
    <PageHeader
      class="print:hidden"
      title="Cash Transactions"
      description="Record cash and bank ledger entries with automatic balance tracking."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton variant="secondary" :icon="Printer" @click="handlePrint">Print</BaseButton>
        <BaseButton variant="secondary" :icon="Plus" @click="openCreateForm">Advanced entry</BaseButton>
      </template>
    </PageHeader>

    <!-- Filter bar -->
    <BaseCard class="mb-6 print:hidden">
      <div class="grid gap-3 sm:grid-cols-4">
        <BaseSelect
          id="head"
          :model-value="headId"
          label="Head"
          size="sm"
          placeholder="Select a head…"
          :options="userOptions"
          @update:model-value="(v) => onHeadFilterChange(v as number | null)"
        />
        <BaseSelect
          id="counterparty"
          :model-value="counterpartyId"
          label="User"
          size="sm"
          placeholder="Select a user…"
          :disabled="headId === null"
          :options="userOptions"
          @update:model-value="(v) => (counterpartyId = v as number | null)"
        />
        <BaseInput id="from_date" v-model="fromDate" label="From date" type="date" size="sm" />
        <BaseInput id="to_date" v-model="toDate" label="Date" type="date" size="sm" />
      </div>
      <BaseInput
        v-model="searchQuery"
        type="search"
        :icon="Search"
        placeholder="Search transactions…"
        aria-label="Search transactions"
        class="mt-3 w-full max-w-xs"
      />
    </BaseCard>

    <!-- Quick-action buttons -->
    <div v-if="canQuickCreate" class="mb-6 grid gap-4 sm:grid-cols-2 print:hidden">
      <div>
        <p class="mb-2 text-xs font-semibold tracking-wide text-red-700 uppercase">Out</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="a in outActions"
            :key="a.type"
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition-colors"
            :class="a.classes"
            @click="openQuickCreate(a.type)"
          >
            {{ a.label }}
          </button>
          <button
            type="button"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
            @click="openPurchaseGoldModal"
          >
            Purchase Gold
          </button>
          <button
            type="button"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700"
            @click="openGoldToCashModal"
          >
            Gold To Cash
          </button>
        </div>
      </div>
      <div>
        <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-700 uppercase">In</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="a in inActions"
            :key="a.type"
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition-colors"
            :class="a.classes"
            @click="openQuickCreate(a.type)"
          >
            {{ a.label }}
          </button>
          <button
            type="button"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
            @click="openAutoEntryModal"
          >
            Auto Entry
          </button>
          <button
            type="button"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
            @click="openCashToGoldModal"
          >
            Cash To Gold
          </button>
          <button
            type="button"
            class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-purple-700"
            @click="openSaleGoldModal"
          >
            Sale Gold
          </button>
        </div>
      </div>
    </div>
    <p v-else-if="headId !== null" class="mb-6 text-sm text-slate-500 print:hidden">
      Select a User above to record a new transaction with this head.
    </p>

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

    <!-- Out / In ledger -->
    <div v-else-if="headId !== null">
      <p class="mb-4 hidden text-sm text-slate-600 print:block">
        {{ headUser?.name }}<template v-if="counterpartyUser"> — {{ counterpartyUser.name }}</template>
      </p>
      <div class="grid gap-6 lg:grid-cols-2 print:grid-cols-1">
      <div>
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-900">Out</h3>
          <span class="text-sm font-semibold text-red-700">{{ outTotal.toLocaleString() }}</span>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">ID</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Given To</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Given By</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Category</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Source</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Cash</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Type</th>
                <th class="px-3 py-2 print:hidden"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="outRows.length === 0">
                <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">No out transactions.</td>
              </tr>
              <tr v-for="row in visibleOutRows" :key="row.txn_id" class="bg-red-50/40">
                <td class="px-3 py-2">{{ row.txn_id }}</td>
                <td class="px-3 py-2">{{ userName(row.given_to, row.given_to_user) }}</td>
                <td class="px-3 py-2">{{ userName(row.given_by, row.given_by_user) }}</td>
                <td class="px-3 py-2">{{ categoryLabel(row.category_id) }}</td>
                <td class="px-3 py-2">
                  {{ row.souce_type === 'BANK' ? `Bank · ${row.bank?.bank_name ?? '#' + row.bank_id}` : 'Cash on hand' }}
                </td>
                <td class="px-3 py-2 font-medium">{{ row.amount.toLocaleString() }}</td>
                <td class="px-3 py-2">{{ typeLabel(row.type) }}</td>
                <td class="px-3 py-2 print:hidden">
                  <div class="flex items-center justify-end gap-1">
                    <button
                      type="button"
                      class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                      aria-label="Edit transaction"
                      @click="openEditRow(row)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                    <ConfirmPopover
                      :message="`Delete transaction #${row.txn_id}? This restores the affected balances.`"
                      @confirm="handleDelete(row)"
                    >
                      <template #default="{ toggle }">
                        <button
                          type="button"
                          class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                          aria-label="Delete transaction"
                          :disabled="deletingId === row.txn_id"
                          @click="toggle"
                        >
                          <Trash2 class="h-4 w-4" />
                        </button>
                      </template>
                    </ConfirmPopover>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <BaseButton
          v-if="outVisible < outRows.length"
          variant="secondary"
          class="mt-3 w-full print:hidden"
          @click="outVisible += PAGE_SIZE"
        >
          Load more
        </BaseButton>
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-900">In</h3>
          <span class="text-sm font-semibold text-emerald-700">{{ inTotal.toLocaleString() }}</span>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">ID</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Given To</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Given By</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Category</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Source</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Cash</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Type</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Remarks</th>
                <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Reminder</th>
                <th class="px-3 py-2 print:hidden"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="inRows.length === 0">
                <td colspan="10" class="px-3 py-8 text-center text-sm text-slate-500">No in transactions.</td>
              </tr>
              <tr v-for="row in visibleInRows" :key="row.txn_id" class="bg-blue-50/40">
                <td class="px-3 py-2">{{ row.txn_id }}</td>
                <td class="px-3 py-2">{{ userName(row.given_to, row.given_to_user) }}</td>
                <td class="px-3 py-2">{{ userName(row.given_by, row.given_by_user) }}</td>
                <td class="px-3 py-2">{{ categoryLabel(row.category_id) }}</td>
                <td class="px-3 py-2">
                  {{ row.souce_type === 'BANK' ? `Bank · ${row.bank?.bank_name ?? '#' + row.bank_id}` : 'Cash on hand' }}
                </td>
                <td class="px-3 py-2 font-medium">{{ row.amount.toLocaleString() }}</td>
                <td class="px-3 py-2">{{ typeLabel(row.type) }}</td>
                <td class="px-3 py-2">{{ row.remarks || '—' }}</td>
                <td class="px-3 py-2">{{ row.remainder || '—' }}</td>
                <td class="px-3 py-2 print:hidden">
                  <div class="flex items-center justify-end gap-1">
                    <button
                      type="button"
                      class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                      aria-label="Edit transaction"
                      @click="openEditRow(row)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                    <ConfirmPopover
                      :message="`Delete transaction #${row.txn_id}? This restores the affected balances.`"
                      @confirm="handleDelete(row)"
                    >
                      <template #default="{ toggle }">
                        <button
                          type="button"
                          class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                          aria-label="Delete transaction"
                          :disabled="deletingId === row.txn_id"
                          @click="toggle"
                        >
                          <Trash2 class="h-4 w-4" />
                        </button>
                      </template>
                    </ConfirmPopover>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <BaseButton
          v-if="inVisible < inRows.length"
          variant="secondary"
          class="mt-3 w-full print:hidden"
          @click="inVisible += PAGE_SIZE"
        >
          Load more
        </BaseButton>
      </div>
      </div>
    </div>

    <p
      v-else
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Select a Head above to view their cash ledger.
    </p>

    <TransactionEntryModal
      v-if="isModalOpen && modalHead && modalCounterparty"
      :head-user="modalHead"
      :counterparty-user="modalCounterparty"
      :type="modalType"
      :banks="banks"
      :latest-account-balance="transactions[0]?.closing_account_balance ?? 0"
      :added-by="auth.user?.user_id ?? null"
      :editing-transaction="editingTxn"
      @close="closeModal"
      @saved="handleModalSaved"
      @use-advanced-form="handleUseAdvancedForm"
    />

    <PurchaseGoldModal
      v-if="isPurchaseGoldModalOpen && headUser && counterpartyUser"
      :head-user="headUser"
      :counterparty-user="counterpartyUser"
      :items="items"
      :banks="banks"
      @close="isPurchaseGoldModalOpen = false"
    />

    <GoldToCashModal
      v-if="isGoldToCashModalOpen && headUser && counterpartyUser"
      :head-user="headUser"
      :counterparty-user="counterpartyUser"
      :banks="banks"
      @close="isGoldToCashModalOpen = false"
    />

    <CashToGoldModal
      v-if="isCashToGoldModalOpen && headUser && counterpartyUser"
      :head-user="headUser"
      :counterparty-user="counterpartyUser"
      :items="items"
      :banks="banks"
      @close="isCashToGoldModalOpen = false"
    />

    <SaleGoldQuickModal
      v-if="isSaleGoldModalOpen && headUser && counterpartyUser"
      :head-user="headUser"
      :counterparty-user="counterpartyUser"
      :items="items"
      :banks="banks"
      :latest-account-balance="transactions[0]?.closing_account_balance ?? 0"
      @close="isSaleGoldModalOpen = false"
      @saved="handleSaleGoldSaved"
    />

    <AutoEntryModal
      v-if="isAutoEntryModalOpen && headUser && counterpartyUser"
      :head-user="headUser"
      :counterparty-user="counterpartyUser"
      :latest-account-balance="transactions[0]?.closing_account_balance ?? 0"
      :added-by="auth.user?.user_id ?? null"
      @close="isAutoEntryModalOpen = false"
      @saved="handleAutoEntrySaved"
    />

    <!-- Advanced form: full field set, reachable via "Advanced entry" above
         or the streamlined modal's escape hatch. -->
    <BaseCard v-if="isFormOpen" class="mt-6 print:hidden">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit transaction' : 'New transaction (advanced form)' }}
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
  </div>
</template>
