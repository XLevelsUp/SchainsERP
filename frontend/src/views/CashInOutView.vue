<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ArrowDownCircle, ArrowUpCircle, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { cashTxnPostApi } from '@/lib/cashTxnDetailsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import { sourceTypeOptions } from '@/lib/cashTxnOptions'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { BankDetail, CashTxnPostFormValues, CashTxnPostResult, CashTxnSourceType, UserDetail } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash In / Out — quick posting screen
|--------------------------------------------------------------------------
| Targets POST /cash-txn-details/in and /cash-txn-details/out
| (CashTxnDetailController::postIncome/postExpense), the only cash-txn
| write endpoints that match the current backend schema as of PR #13.
| See the warning block at the top of types/cashTxnDetail.ts for why the
| full ledger CRUD on CashTransactionsView.vue is currently broken and
| was left alone rather than folded into this screen.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()
const toastStore = useToastStore()

const users = ref<UserDetail[]>([])
const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')

function userLabel(user: UserDetail) {
  return `${user.name} (${user.user_name})`
}

function bankLabel(bank: BankDetail) {
  return bank.account_name ?? bank.bank_name ?? `#${bank.bank_id}`
}

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.user_id, label: userLabel(u) })),
)
const bankOptions = computed(() => banks.value.map((b) => ({ value: b.bank_id, label: bankLabel(b) })))

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [usersData, banksData] = await Promise.all([userDetailsApi.list(), bankDetailsApi.list()])
    users.value = usersData
    banks.value = banksData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load users/banks.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

function makeEmptyForm(): CashTxnPostFormValues {
  return {
    sender_id: null,
    recipient_id: null,
    category_id: null,
    amount: null,
    payment_method: 'CASH_ON_HAND',
    bank_account_id: null,
    remarks: '',
    images: [],
  }
}

const direction = ref<'in' | 'out'>('in')
const form = reactive<CashTxnPostFormValues>(makeEmptyForm())
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)
const lastResult = ref<CashTxnPostResult | null>(null)

function setDirection(d: 'in' | 'out') {
  direction.value = d
  lastResult.value = null
}

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function resetForm() {
  Object.assign(form, makeEmptyForm())
  clearFieldErrors()
  formError.value = ''
  lastResult.value = null
}

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.sender_id === null) fieldErrors.sender_id = 'Sender is required.'
  if (form.recipient_id === null) fieldErrors.recipient_id = 'Recipient is required.'
  if (
    form.sender_id !== null &&
    form.recipient_id !== null &&
    form.sender_id === form.recipient_id
  ) {
    fieldErrors.recipient_id = 'Recipient cannot be the same user as the sender.'
  }
  if (form.amount === null || Number.isNaN(form.amount) || form.amount <= 0) {
    fieldErrors.amount = 'Amount must be greater than 0.'
  }
  if (form.payment_method === 'BANK' && form.bank_account_id === null) {
    fieldErrors.bank_account_id = 'Select a bank account.'
  }

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

  isSaving.value = true
  try {
    const actingUserId = auth.user?.user_id ?? 1
    const result =
      direction.value === 'in'
        ? await cashTxnPostApi.postIncome(form, actingUserId)
        : await cashTxnPostApi.postExpense(form, actingUserId)

    lastResult.value = result
    toastStore.show(
      direction.value === 'in' ? 'Cash in recorded successfully.' : 'Cash out recorded successfully.',
      'success',
    )
    resetForm()
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
      formError.value = 'Failed to record transaction.'
      toastStore.show('Failed to record transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Cash In / Out"
      description="Record a cash or bank transfer between two users. Uses the current backend schema (sender/recipient balances)."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
      </template>
    </PageHeader>

    <div
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </div>

    <div class="mb-4 flex gap-2">
      <button
        type="button"
        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
        :class="
          direction === 'in'
            ? 'bg-emerald-600 text-white'
            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
        "
        @click="setDirection('in')"
      >
        <ArrowDownCircle class="h-4 w-4" /> Cash In
      </button>
      <button
        type="button"
        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
        :class="
          direction === 'out'
            ? 'bg-red-600 text-white'
            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
        "
        @click="setDirection('out')"
      >
        <ArrowUpCircle class="h-4 w-4" /> Cash Out
      </button>
    </div>

    <BaseCard>
      <p
        v-if="formError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ formError }}
      </p>
      <p
        v-if="lastResult"
        class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
      >
        Recorded transaction #{{ lastResult.txn_id }}. Sender closing cash:
        {{ lastResult.sender_closing_cash }} · Recipient closing cash:
        {{ lastResult.recipient_closing_cash }}
      </p>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="grid gap-3 sm:grid-cols-2">
          <BaseSelect
            id="sender_id"
            :model-value="form.sender_id"
            label="Sender"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.sender_id"
            @update:model-value="(v) => (form.sender_id = v as number | null)"
          />
          <BaseSelect
            id="recipient_id"
            :model-value="form.recipient_id"
            label="Recipient"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.recipient_id"
            @update:model-value="(v) => (form.recipient_id = v as number | null)"
          />
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
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
            id="payment_method"
            :model-value="form.payment_method"
            label="Payment method"
            required
            size="sm"
            :options="sourceTypeOptions"
            @update:model-value="(v) => (form.payment_method = v as CashTxnSourceType)"
          />
          <BaseSelect
            v-if="form.payment_method === 'BANK'"
            id="bank_account_id"
            :model-value="form.bank_account_id"
            label="Bank account"
            required
            size="sm"
            placeholder="Select a bank…"
            :options="bankOptions"
            :error="fieldErrors.bank_account_id"
            @update:model-value="(v) => (form.bank_account_id = v as number | null)"
          />
          <BaseInput
            id="category_id"
            :model-value="form.category_id === null ? '' : String(form.category_id)"
            label="Category ID"
            type="number"
            size="sm"
            placeholder="Optional"
            @update:model-value="(v) => (form.category_id = v === '' ? null : Number(v))"
          />
        </div>

        <BaseTextarea id="remarks" v-model="form.remarks" label="Remarks" size="sm" :rows="2" />

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving || isLoading">
            {{ isSaving ? 'Saving…' : direction === 'in' ? 'Record cash in' : 'Record cash out' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="resetForm">Clear</BaseButton>
        </div>
      </form>
    </BaseCard>
  </div>
</template>
