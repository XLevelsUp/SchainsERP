<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import PartyBalanceCards from './PartyBalanceCards.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { cashCategoriesApi } from '@/lib/cashCategoriesApi'
import { sourceTypeOptions } from '@/lib/cashTxnOptions'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { BankDetail, CashCategory, CashTxnPostFormValues, CashTxnSourceType } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash In / Out — targets postIncome/postExpense (StoreCashTxnDetailRequest)
|--------------------------------------------------------------------------
| OUT: head pays the user (sender=head, recipient=user).
| IN: the user pays the head (sender=user, recipient=head).
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  headId: number
  headName: string
  userId: number
  userName: string
  direction: 'in' | 'out'
  banks: BankDetail[]
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toastStore = useToastStore()

const bankOptions = props.banks.map((b) => ({
  value: b.bank_id,
  label: b.account_name || `#${b.bank_id}`,
}))

// sender = who pays, recipient = who receives.
const senderId = props.direction === 'out' ? props.headId : props.userId
const recipientId = props.direction === 'out' ? props.userId : props.headId
const senderName = props.direction === 'out' ? props.headName : props.userName
const recipientName = props.direction === 'out' ? props.userName : props.headName

const isLoadingBalances = ref(true)
const senderBalance = reactive({ cash: 0, rtgs: 0 })
const recipientBalance = reactive({ cash: 0, rtgs: 0 })

async function loadBalances() {
  isLoadingBalances.value = true
  try {
    const [senderRes, recipientRes] = await Promise.all([
      userDetailsApi.get(senderId),
      userDetailsApi.get(recipientId),
    ])
    senderBalance.cash = senderRes.user.rak_cash_balance
    senderBalance.rtgs = senderRes.user.rak_rtgs_balance
    recipientBalance.cash = recipientRes.user.rak_cash_balance
    recipientBalance.rtgs = recipientRes.user.rak_rtgs_balance
  } catch {
    // Non-fatal — the form still works without the balance preview.
  } finally {
    isLoadingBalances.value = false
  }
}

onMounted(loadBalances)

// Categories aren't filtered by direction (category_type isn't validated
// against the transaction direction server-side) — showing every active
// category avoids hiding one the user legitimately wants.
const categories = ref<CashCategory[]>([])
async function loadCategories() {
  try {
    categories.value = (await cashCategoriesApi.list()).filter((c) => c.is_active)
  } catch {
    // Non-fatal — the form still works with category left unset.
  }
}
onMounted(loadCategories)

const categoryOptions = computed(() =>
  categories.value.map((c) => ({
    value: c.category_id,
    label: c.category_type ? `${c.category_name} (${c.category_type})` : c.category_name,
  })),
)

const form = reactive<CashTxnPostFormValues>({
  sender_id: senderId,
  recipient_id: recipientId,
  category_id: null,
  amount: null,
  payment_method: 'CASH_ON_HAND',
  bank_account_id: null,
  remarks: '',
  images: [],
})

// CB (closing balance) preview — mirrors CashTxnDetailService::storeIncome/
// storeExpense exactly: CASH_ON_HAND moves the Hand Cash column only, BANK
// moves the RTGS Cash column only; sender always loses what recipient
// gains. Null (shown as "—") until there's an amount to compute from.
const senderCb = computed(() => {
  if (form.amount === null || Number.isNaN(form.amount)) return null
  return form.payment_method === 'CASH_ON_HAND'
    ? { cash: senderBalance.cash - form.amount, rtgs: senderBalance.rtgs }
    : { cash: senderBalance.cash, rtgs: senderBalance.rtgs - form.amount }
})
const recipientCb = computed(() => {
  if (form.amount === null || Number.isNaN(form.amount)) return null
  return form.payment_method === 'CASH_ON_HAND'
    ? { cash: recipientBalance.cash + form.amount, rtgs: recipientBalance.rtgs }
    : { cash: recipientBalance.cash, rtgs: recipientBalance.rtgs + form.amount }
})

const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.amount === null || Number.isNaN(form.amount) || form.amount <= 0) {
    fieldErrors.amount = 'Amount must be greater than 0.'
  }
  if (form.payment_method === 'BANK' && form.bank_account_id === null) {
    fieldErrors.bank_account_id = 'Select a bank account.'
  }

  const oversized = form.images.find((f) => f.size > 5 * 1024 * 1024)
  if (oversized) {
    fieldErrors.images = `${oversized.name} is over 5 MB — remove it or use a smaller image.`
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
    if (props.direction === 'in') {
      await cashTxnDetailsApi.postIncome(form, actingUserId)
    } else {
      await cashTxnDetailsApi.postExpense(form, actingUserId)
    }
    toastStore.show(
      props.direction === 'in' ? 'Cash in recorded successfully.' : 'Cash out recorded successfully.',
      'success',
    )
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
      formError.value = 'Failed to record transaction.'
      toastStore.show('Failed to record transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal
    :title="direction === 'out' ? 'Add Expense (Out)' : 'Add Income (In)'"
    @close="emit('close')"
  >
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <p class="text-center text-sm text-slate-600">
      <span class="font-semibold text-slate-800">{{ senderName }}</span>
      to
      <span class="font-semibold text-slate-800">{{ recipientName }}</span> :
    </p>

    <PartyBalanceCards
      :left-label="senderName"
      :left-ob-cash="senderBalance.cash"
      :left-ob-rtgs="senderBalance.rtgs"
      :left-cb-cash="senderCb?.cash ?? null"
      :left-cb-rtgs="senderCb?.rtgs ?? null"
      :right-label="recipientName"
      :right-ob-cash="recipientBalance.cash"
      :right-ob-rtgs="recipientBalance.rtgs"
      :right-cb-cash="recipientCb?.cash ?? null"
      :right-cb-rtgs="recipientCb?.rtgs ?? null"
      :is-loading="isLoadingBalances"
    />

    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <div class="grid gap-3 sm:grid-cols-2">
        <BaseSelect
          id="category_id"
          :model-value="form.category_id"
          label="Category (optional)"
          size="sm"
          placeholder="No category"
          :options="categoryOptions"
          @update:model-value="(v) => (form.category_id = v as number | null)"
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
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <BaseSelect
          id="payment_method"
          :model-value="form.payment_method"
          label="Source type"
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
      </div>

      <BaseTextarea id="remarks" v-model="form.remarks" label="Remarks" size="sm" :rows="2" />

      <div>
        <BaseFileInput
          id="images"
          v-model="form.images"
          label="Receipt images (optional)"
          hint="JPG, PNG, or WEBP, up to 5 MB each."
        />
        <p v-if="fieldErrors.images" class="mt-2 text-sm text-red-600">{{ fieldErrors.images }}</p>
      </div>

      <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save' }}
        </BaseButton>
        <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
