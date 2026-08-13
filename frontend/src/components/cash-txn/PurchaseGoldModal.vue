<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Plus, Trash } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import PartyBalanceCards from './PartyBalanceCards.vue'
import { purchaseGoldApi } from '@/lib/purchaseGoldApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { sourceTypeOptions } from '@/lib/cashTxnOptions'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type {
  BankDetail,
  CashTxnSourceType,
  Item,
  PurchaseGoldAmountSourceInput,
  PurchaseGoldType,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Purchase Gold modal — head buys gold from a customer, paying cash
|--------------------------------------------------------------------------
| Same POST /purchase-gold flow as the standalone page, minus the head/
| customer pickers — already fixed by the Cash Management hub selection.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  headId: number
  headName: string
  customerId: number
  customerName: string
  items: Item[]
  banks: BankDetail[]
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const toastStore = useToastStore()

const typeOptions: { value: PurchaseGoldType; label: string }[] = [
  { value: 'HEAD', label: 'Purchase Gold' },
  { value: 'OUT_CASH_CONVERTER', label: 'Out Cash Converter' },
]

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))
const bankOptions = computed(() =>
  props.banks.map((b) => ({ value: b.bank_id, label: b.account_name || `#${b.bank_id}` })),
)

function makeEmptySource(): PurchaseGoldAmountSourceInput {
  return { source: 'CASH_ON_HAND', bank_id: null, amount: null }
}

const form = reactive({
  type: 'HEAD' as PurchaseGoldType,
  item_id: null as number | null,
  total_cash: null as number | null,
  touch: null as number | null,
  per_gram_cash: null as number | null,
  amnt_transfer_to_head: true,
  remarks: '',
  retailer_id: null as number | null,
  amount_sources: [makeEmptySource()],
})
const images = ref<File[]>([])
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)

// Total cash and per-gram cash are what the operator actually types —
// purity and total grams are derived from them, the reverse of the old
// "type the weight, cash is calculated" flow. Same underlying relationship
// (purity = grams * touch / 100), just solved for a different variable.
const purity = computed(() => {
  if (form.total_cash === null || form.per_gram_cash === null || form.per_gram_cash === 0) return null
  return Number((form.total_cash / form.per_gram_cash).toFixed(4))
})
const totalGrams = computed(() => {
  if (purity.value === null || form.touch === null || form.touch === 0) return null
  return Number(((purity.value * 100) / form.touch).toFixed(4))
})

// Taken * — partial-delivery tracking. Each auto-fills to match the full
// calculated/entered amount whenever it changes, but stays independently
// editable once the operator touches it (so entering a smaller "taken"
// value for a partial delivery doesn't keep getting overwritten).
const takenTotalCash = ref<number | null>(null)
const takenTotalCashTouched = ref(false)
watch(
  () => form.total_cash,
  (v) => {
    if (!takenTotalCashTouched.value) takenTotalCash.value = v
  },
)

const takenPurity = ref<number | null>(null)
const takenPurityTouched = ref(false)
watch(purity, (v) => {
  if (!takenPurityTouched.value) takenPurity.value = v
})

const takenTotalGrams = ref<number | null>(null)
const takenTotalGramsTouched = ref(false)
watch(totalGrams, (v) => {
  if (!takenTotalGramsTouched.value) takenTotalGrams.value = v
})

// Balance preview — OB is fetched regardless of Type (it's just each
// party's current standing). CB is only projected for the standard HEAD
// flow (head buys gold, pays cash): gold moves grams/purity 1:1 with the
// calculated total_grams/purity, split across Hand Cash/RTGS by each
// payment source's type, same as PurchaseGoldService. OUT_CASH_CONVERTER's
// accounting direction isn't documented on the frontend, so no CB preview
// is shown for it rather than guessing — OB alone still displays.
const isLoadingBalance = ref(true)
const headBalance = reactive({ cash: 0, rtgs: 0, grams: 0, purity: 0 })
const customerBalance = reactive({ cash: 0, rtgs: 0, grams: 0, purity: 0 })

async function loadBalances() {
  isLoadingBalance.value = true
  try {
    const [headRes, customerRes] = await Promise.all([
      userDetailsApi.get(props.headId),
      userDetailsApi.get(props.customerId),
    ])
    headBalance.cash = headRes.user.rak_cash_balance
    headBalance.rtgs = headRes.user.rak_rtgs_balance
    headBalance.grams = headRes.user.grams_grand_total
    headBalance.purity = headRes.user.purity_grand_total
    customerBalance.cash = customerRes.user.rak_cash_balance
    customerBalance.rtgs = customerRes.user.rak_rtgs_balance
    customerBalance.grams = customerRes.user.grams_grand_total
    customerBalance.purity = customerRes.user.purity_grand_total
  } catch {
    // Non-fatal — the form still works without the balance preview.
  } finally {
    isLoadingBalance.value = false
  }
}
onMounted(loadBalances)

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function addSource() {
  form.amount_sources.push(makeEmptySource())
}
function removeSource(index: number) {
  form.amount_sources.splice(index, 1)
}

const sourceTotal = computed(() => form.amount_sources.reduce((sum, s) => sum + (s.amount ?? 0), 0))
const sourceTotalMatches = computed(
  () => Math.round(sourceTotal.value * 100) === Math.round((form.total_cash ?? 0) * 100),
)

const sourcesCashTotal = computed(() =>
  form.amount_sources
    .filter((s) => s.source === 'CASH_ON_HAND')
    .reduce((sum, s) => sum + (s.amount ?? 0), 0),
)
const sourcesBankTotal = computed(() =>
  form.amount_sources.filter((s) => s.source === 'BANK').reduce((sum, s) => sum + (s.amount ?? 0), 0),
)
const cashMoving = computed(
  () => form.type === 'HEAD' && form.amnt_transfer_to_head && sourceTotal.value > 0,
)

const headCbCash = computed(() => (cashMoving.value ? headBalance.cash - sourcesCashTotal.value : null))
const headCbRtgs = computed(() => (cashMoving.value ? headBalance.rtgs - sourcesBankTotal.value : null))
const headCbGrams = computed(() =>
  form.type === 'HEAD' && totalGrams.value !== null ? headBalance.grams + totalGrams.value : null,
)
const headCbPurity = computed(() =>
  form.type === 'HEAD' && purity.value !== null ? headBalance.purity + purity.value : null,
)

const customerCbCash = computed(() =>
  cashMoving.value ? customerBalance.cash + sourcesCashTotal.value : null,
)
const customerCbRtgs = computed(() =>
  cashMoving.value ? customerBalance.rtgs + sourcesBankTotal.value : null,
)
const customerCbGrams = computed(() =>
  form.type === 'HEAD' && totalGrams.value !== null ? customerBalance.grams - totalGrams.value : null,
)
const customerCbPurity = computed(() =>
  form.type === 'HEAD' && purity.value !== null ? customerBalance.purity - purity.value : null,
)

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.item_id === null) fieldErrors.item_id = 'Item is required.'
  if (form.total_cash === null || form.total_cash < 0) {
    fieldErrors.total_cash = 'Total cash is required.'
  }
  if (form.touch === null || form.touch < 0 || form.touch > 100) {
    fieldErrors.touch = 'Touch is required (0–100).'
  }
  if (form.per_gram_cash === null || form.per_gram_cash < 0) {
    fieldErrors.per_gram_cash = 'Per-gram cash is required.'
  }
  if (purity.value === null) {
    fieldErrors.purity = 'Purity could not be calculated — check total cash and per-gram cash.'
  }
  if (totalGrams.value === null) {
    fieldErrors.total_grams = 'Total grams could not be calculated — check purity and touch.'
  }

  const oversized = images.value.find((f) => f.size > 5 * 1024 * 1024)
  if (oversized) {
    fieldErrors.images = `${oversized.name} is over 5 MB — remove it or use a smaller image.`
  }

  if (form.amnt_transfer_to_head) {
    form.amount_sources.forEach((source, index) => {
      if (source.amount === null || source.amount < 0.01) {
        fieldErrors[`amount_sources.${index}.amount`] = `Row ${index + 1}: amount is required (min 0.01).`
      }
      if (source.source === 'BANK' && source.bank_id === null) {
        fieldErrors[`amount_sources.${index}.bank_id`] = `Row ${index + 1}: select a bank.`
      }
    })
    if (!sourceTotalMatches.value) {
      fieldErrors.amount_sources_total =
        `Payment sources total (${sourceTotal.value.toFixed(2)}) must equal total cash (${(form.total_cash ?? 0).toFixed(2)}).`
    }
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
    await purchaseGoldApi.create(
      {
        type: form.type,
        head_id: props.headId,
        customer_id: props.customerId,
        item_id: form.item_id,
        total_grams: totalGrams.value,
        touch: form.touch,
        per_gram_cash: form.per_gram_cash,
        purity: purity.value,
        total_cash: form.total_cash,
        taken_total_cash: takenTotalCash.value,
        taken_total_grams: takenTotalGrams.value,
        taken_purity: takenPurity.value,
        amnt_transfer_to_head: form.amnt_transfer_to_head,
        remarks: form.remarks,
        retailer_id: form.retailer_id,
        amount_sources: form.amount_sources.map((s) => ({ ...s })),
      },
      images.value,
    )
    toastStore.show('Purchase Gold transaction saved successfully.', 'success')
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
      formError.value = 'Failed to save Purchase Gold transaction.'
      toastStore.show('Failed to save Purchase Gold transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal
    title="Purchase Gold"
    :badge="`${customerName} to ${headName}`"
    badge-class="bg-amber-500"
    @close="emit('close')"
  >
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      Type changes real accounting behavior, not just the label — "Purchase Gold" is the standard
      head-buys-from-customer flow (stock IN), while "Out Cash Converter" is an unrelated stock OUT
      that reuses this same form, not an actual purchase. Delivery is always recorded as full, too —
      there's no partial-delivery tracking here.
    </p>

    <div>
      <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Balances</p>
      <PartyBalanceCards
        :left-label="headName"
        :left-ob-cash="headBalance.cash"
        :left-ob-rtgs="headBalance.rtgs"
        :left-cb-cash="headCbCash"
        :left-cb-rtgs="headCbRtgs"
        :left-ob-grams="headBalance.grams"
        :left-ob-purity="headBalance.purity"
        :left-cb-grams="headCbGrams"
        :left-cb-purity="headCbPurity"
        :right-label="customerName"
        :right-ob-cash="customerBalance.cash"
        :right-ob-rtgs="customerBalance.rtgs"
        :right-cb-cash="customerCbCash"
        :right-cb-rtgs="customerCbRtgs"
        :right-ob-grams="customerBalance.grams"
        :right-ob-purity="customerBalance.purity"
        :right-cb-grams="customerCbGrams"
        :right-cb-purity="customerCbPurity"
        :is-loading="isLoadingBalance"
      />
    </div>

    <form class="flex flex-col gap-5" @submit.prevent="handleSubmit">
      <div class="grid gap-3 sm:grid-cols-3">
        <BaseSelect
          id="type"
          :model-value="form.type"
          label="Type"
          required
          size="sm"
          :options="typeOptions"
          @update:model-value="(v) => (form.type = v as PurchaseGoldType)"
        />
        <BaseSelect
          id="item_id"
          :model-value="form.item_id"
          label="Item"
          required
          size="sm"
          placeholder="Select an item…"
          :options="itemOptions"
          :error="fieldErrors.item_id"
          @update:model-value="
            (v) => {
              form.item_id = v as number | null
              const item = items.find((i) => i.item_id === v)
              if (item) form.touch = item.default_touch
            }
          "
        />
        <BaseInput
          id="touch"
          :model-value="form.touch === null ? '' : String(form.touch)"
          label="Touch"
          type="number"
          step="0.001"
          required
          size="sm"
          :error="fieldErrors.touch"
          @update:model-value="(v) => (form.touch = v === '' ? null : Number(v))"
        />

        <BaseInput
          id="total_cash"
          :model-value="form.total_cash === null ? '' : String(form.total_cash)"
          label="Total cash"
          type="number"
          step="0.001"
          required
          size="sm"
          :error="fieldErrors.total_cash"
          @update:model-value="(v) => (form.total_cash = v === '' ? null : Number(v))"
        />
        <BaseInput
          id="per_gram_cash"
          :model-value="form.per_gram_cash === null ? '' : String(form.per_gram_cash)"
          label="Per-gram cash"
          type="number"
          step="0.001"
          required
          size="sm"
          :error="fieldErrors.per_gram_cash"
          @update:model-value="(v) => (form.per_gram_cash = v === '' ? null : Number(v))"
        />
        <BaseInput
          id="purity"
          :model-value="purity === null ? '' : String(purity)"
          label="Purity (calculated)"
          type="number"
          readonly
          size="sm"
          :error="fieldErrors.purity"
        />

        <BaseInput
          id="total_grams"
          :model-value="totalGrams === null ? '' : String(totalGrams)"
          label="Total grams (calculated)"
          type="number"
          readonly
          size="sm"
          :error="fieldErrors.total_grams"
        />
        <BaseCheckbox
          v-model="form.amnt_transfer_to_head"
          label="Transfer amount to head"
          class="self-end pb-2"
        />
        <BaseInput
          id="retailer_id"
          :model-value="form.retailer_id === null ? '' : String(form.retailer_id)"
          label="Retailer ID (optional)"
          type="number"
          step="1"
          size="sm"
          @update:model-value="(v) => (form.retailer_id = v === '' ? null : Number(v))"
        />
      </div>

      <div class="rounded-lg border border-slate-200 p-3">
        <h3 class="text-sm font-semibold text-slate-900">Taken (partial delivery)</h3>
        <p class="mt-1 mb-3 text-xs text-slate-500">
          Defaults to the full amount above — lower these only if the customer took less than the
          full total cash, purity, or grams in this delivery.
        </p>
        <div class="grid gap-3 sm:grid-cols-3">
          <BaseInput
            id="taken_total_cash"
            :model-value="takenTotalCash === null ? '' : String(takenTotalCash)"
            label="Taken total cash"
            type="number"
            step="0.001"
            size="sm"
            @update:model-value="
              (v) => {
                takenTotalCashTouched = true
                takenTotalCash = v === '' ? null : Number(v)
              }
            "
          />
          <BaseInput
            id="taken_purity"
            :model-value="takenPurity === null ? '' : String(takenPurity)"
            label="Taken purity"
            type="number"
            step="0.001"
            size="sm"
            @update:model-value="
              (v) => {
                takenPurityTouched = true
                takenPurity = v === '' ? null : Number(v)
              }
            "
          />
          <BaseInput
            id="taken_total_grams"
            :model-value="takenTotalGrams === null ? '' : String(takenTotalGrams)"
            label="Taken total grams"
            type="number"
            step="0.001"
            size="sm"
            @update:model-value="
              (v) => {
                takenTotalGramsTouched = true
                takenTotalGrams = v === '' ? null : Number(v)
              }
            "
          />
        </div>
      </div>

      <BaseTextarea id="remarks" v-model="form.remarks" label="Remarks" size="sm" :rows="2" />

      <div>
        <BaseFileInput
          id="images"
          v-model="images"
          label="Receipt images (optional)"
          hint="JPG, PNG, or WEBP, up to 5 MB each."
        />
        <p v-if="fieldErrors.images" class="mt-2 text-sm text-red-600">{{ fieldErrors.images }}</p>
      </div>

      <section v-if="form.amnt_transfer_to_head" class="border-t border-slate-200 pt-4">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-900">Payment sources</h3>
          <BaseButton variant="secondary" type="button" :icon="Plus" @click="addSource">
            Add source
          </BaseButton>
        </div>

        <div
          v-for="(source, index) in form.amount_sources"
          :key="index"
          class="mb-3 grid items-end gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_auto]"
        >
          <BaseSelect
            :id="`source_${index}`"
            :model-value="source.source"
            label="Source"
            size="sm"
            :options="sourceTypeOptions"
            @update:model-value="(v) => (source.source = v as CashTxnSourceType)"
          />
          <BaseInput
            :id="`source_amount_${index}`"
            :model-value="source.amount === null ? '' : String(source.amount)"
            label="Amount"
            type="number"
            step="0.001"
            size="sm"
            :error="fieldErrors[`amount_sources.${index}.amount`]"
            @update:model-value="(v) => (source.amount = v === '' ? null : Number(v))"
          />
          <button
            type="button"
            class="mb-1 justify-self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
            aria-label="Remove source"
            :disabled="form.amount_sources.length === 1"
            @click="removeSource(index)"
          >
            <Trash class="h-4 w-4" />
          </button>

          <BaseSelect
            v-if="source.source === 'BANK'"
            :id="`source_bank_${index}`"
            :model-value="source.bank_id"
            label="Bank"
            size="sm"
            placeholder="Select a bank…"
            :options="bankOptions"
            :error="fieldErrors[`amount_sources.${index}.bank_id`]"
            @update:model-value="(v) => (source.bank_id = v as number | null)"
          />
        </div>

        <div
          class="flex items-center justify-between rounded-lg px-3 py-2 text-sm"
          :class="sourceTotalMatches ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
        >
          <span>Sources total: {{ sourceTotal.toFixed(2) }}</span>
          <span>Total cash: {{ (form.total_cash ?? 0).toFixed(2) }}</span>
        </div>
      </section>

      <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save' }}
        </BaseButton>
        <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
