<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { stockApi } from '@/lib/stockApi'
import { ApiError } from '@/lib/api'
import { userOptionLabel } from '@/lib/userLabel'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash Out — POST /stock/cash-out (StockDetailsController::postCash)
|--------------------------------------------------------------------------
| Implemented in PR #31 with no route; routed in PR #42, which is what made
| this screen possible.
|
| It sits under /stock but writes cash, not stock: StockOutService::
| createCashOut inserts ONE cash_txn_details row (type EXPENSE,
| payment_method CASH_ON_HAND) and moves rak_cash_balance from the sender to
| the recipient.
|
| The sender is always the acting user — createCashOut takes it from
| $addedBy and there is no field for it. So this records "I handed cash to
| them", never a transfer between two other people. The form says so rather
| than offering a sender picker that would be ignored.
|
| Three fields only, because CashOutRequest validates exactly three:
| given_to (required, must exist), amount (required, numeric, > 0) and
| remarks (nullable, max 5000). Notably there is no date field — the row is
| stamped by the database, so a cash out cannot be backdated here. There is
| also no payment-method choice: it is hardcoded CASH_ON_HAND server-side,
| which is why this is not a general-purpose cash transfer screen. Cash
| Management's own endpoints remain the place for those.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ users: UserDetailListItem[] }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  given_to: null as number | null,
  amount: null as number | null,
  remarks: '',
})

const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

// The acting user is the sender, so offering them as the recipient would
// only produce a self-transfer the backend would happily record.
const recipientOptions = computed(() =>
  props.users
    .filter((u) => u.id !== auth.user?.user_id)
    .map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)

function validate(): boolean {
  fieldErrors.value = {}
  if (form.given_to === null) fieldErrors.value.given_to = 'Select who receives the cash.'
  if (form.amount === null || form.amount <= 0) {
    fieldErrors.value.amount = 'Amount must be greater than 0.'
  }
  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit() {
  if (!validate()) return
  if (isSaving.value) return
  const actingUserId = auth.user?.user_id
  if (actingUserId === undefined) return

  isSaving.value = true
  try {
    await stockApi.postCashOut(
      { given_to: form.given_to, amount: form.amount, remarks: form.remarks },
      actingUserId,
    )
    toast.show('Cash out recorded.', 'success')
    emit('saved')
  } catch (err) {
    if (err instanceof ApiError) {
      toast.show(err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message, 'error')
    } else {
      toast.show('Failed to record the cash out.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="Cash Out" max-width="max-w-lg" @close="emit('close')">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="handleSubmit">
      <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
        Records cash handed over by
        <span class="font-medium text-slate-900">{{ auth.user?.name || 'you' }}</span> as
        cash on hand. The sender is always the signed-in user, and the entry is stamped with the
        current date and time.
      </p>

      <BaseSelect
        id="cash_out_given_to"
        v-model="form.given_to"
        label="Given to"
        placeholder="Select recipient…"
        :options="recipientOptions"
        :error="fieldErrors.given_to"
        required
        searchable
      />

      <BaseInput
        id="cash_out_amount"
        :model-value="form.amount === null ? '' : String(form.amount)"
        label="Amount"
        type="number"
        step="0.01"
        min="0"
        placeholder="0.00"
        :error="fieldErrors.amount"
        required
        @update:model-value="(v) => (form.amount = v === '' ? null : Number(v))"
      />

      <BaseInput
        id="cash_out_remarks"
        v-model="form.remarks"
        label="Remarks"
        type="text"
        placeholder="Optional note"
      />

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">
          Cancel
        </BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Recording…' : 'Record cash out' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
