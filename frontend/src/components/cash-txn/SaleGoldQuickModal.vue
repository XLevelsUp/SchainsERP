<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { X, Trash, Plus } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import { saleGoldApi } from '@/lib/saleGoldApi'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type {
  BankDetail,
  Item,
  SaleGoldAmountSourceInput,
  SaleGoldRecord,
  SaleGoldSourceType,
  UserDetail,
} from '@/types'

// Unlike Purchase Gold / Gold To Cash / Cash To Gold, Sale Gold has a real
// backend (SaleGoldController) — this submits for real via saleGoldApi,
// reusing the same payload shape as the full /sale-gold page.
const props = defineProps<{
  headUser: UserDetail
  counterpartyUser: UserDetail
  items: Item[]
  banks: BankDetail[]
  latestAccountBalance: number
}>()

const emit = defineEmits<{ close: []; saved: [record: SaleGoldRecord] }>()

const toastStore = useToastStore()

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))
const bankOptions = computed(() => props.banks.map((b) => ({ value: b.bank_id, label: b.bank_name })))
const sourceTypeOptions: { value: SaleGoldSourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]

const itemId = ref<number | null>(null)
const grams = ref<number | null>(null)
const touch = ref<number | null>(100)
const perGramCash = ref<number | null>(null)
const amountTransferToHead = ref(true)

function makeEmptySource(): SaleGoldAmountSourceInput {
  return {
    source_type: 'CASH_ON_HAND',
    amount: null,
    bank_id: null,
    bank_name: '',
    opening_bank_account_balance: null,
    opening_bank_user_balance: null,
  }
}
const amountSources = reactive<SaleGoldAmountSourceInput[]>([makeEmptySource()])

function addSourceRow() {
  amountSources.push(makeEmptySource())
}
function removeSourceRow(index: number) {
  if (amountSources.length > 1) amountSources.splice(index, 1)
}
function onSourceBankChange(source: SaleGoldAmountSourceInput, bankId: number | null) {
  source.bank_id = bankId
  const bank = props.banks.find((b) => b.bank_id === bankId)
  source.bank_name = bank?.bank_name ?? ''
  source.opening_bank_account_balance = bank?.current_balance ?? null
}

// Purity and Total Cash are derived, not entered — matching the legacy
// dialog's greyed-out fields. Deriving total_cash from the sources instead
// of asking for it separately also means the backend's "sources must sum
// to total_cash" check is satisfied by construction, never by coincidence.
const purity = computed(() => {
  if (grams.value === null || touch.value === null) return null
  return Number(((grams.value * touch.value) / 100).toFixed(4))
})
const totalCash = computed(() =>
  amountSources.reduce((sum, s) => sum + (s.amount ?? 0), 0),
)

// Mirrors CashTxnDetailController's actual effect on the head's balance for
// SALE_GOLD: only CASH_ON_HAND sources move rak_cash_balance (cumulatively);
// BANK sources move the bank's balance, not the user's. The counterparty's
// balance is never touched by this endpoint at all.
const headClosingCash = computed(() => {
  const cashSourcesTotal = amountSources
    .filter((s) => s.source_type === 'CASH_ON_HAND')
    .reduce((sum, s) => sum + (s.amount ?? 0), 0)
  return props.headUser.rak_cash_balance + cashSourcesTotal
})

const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const isSaving = ref(false)

function validate(): boolean {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''

  if (itemId.value === null) fieldErrors.item_id = 'Item is required.'
  if (grams.value === null || grams.value < 0.001) fieldErrors.grams = 'Grams is required (min 0.001).'
  if (touch.value === null || touch.value < 0) fieldErrors.touch = 'Touch is required.'
  if (perGramCash.value === null || perGramCash.value < 0) {
    fieldErrors.per_gram_cash = 'Per purity cash is required.'
  }
  if (totalCash.value < 0.01) {
    fieldErrors.amount_sources = 'Add at least one payment source with an amount.'
  }
  amountSources.forEach((source, index) => {
    if (source.amount === null || source.amount < 0.01) {
      fieldErrors[`amount_sources.${index}.amount`] = `Row ${index + 1}: amount is required (min 0.01).`
    }
    if (source.source_type === 'BANK' && source.bank_id === null) {
      fieldErrors[`amount_sources.${index}.bank_id`] = `Row ${index + 1}: select a bank.`
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

async function handleSave() {
  if (!validate()) return

  isSaving.value = true
  try {
    const result = await saleGoldApi.create({
      head_id: props.headUser.user_id,
      customer_id: props.counterpartyUser.user_id,
      item_id: itemId.value,
      stock_id: null,
      grams: grams.value,
      touch: touch.value,
      purity: purity.value,
      per_gram_cash: perGramCash.value,
      total_cash: totalCash.value,
      amount_transfer_to_head: amountTransferToHead.value,
      remarks: '',
      opening_account_balance: props.latestAccountBalance,
      opening_user_balance: props.headUser.rak_cash_balance,
      amount_sources: amountSources.map((s) => ({ ...s })),
    })
    toastStore.show('Sale gold entry created successfully.', 'success')
    emit('saved', result.cash_to_gold)
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
      formError.value = 'Failed to create sale gold entry.'
      toastStore.show('Failed to create sale gold entry.', 'error')
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
          <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-slate-900">Sale Gold</h2>
            <span class="rounded-full bg-sky-500 px-2.5 py-1 text-xs font-semibold text-white">
              METALS
            </span>
          </div>
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
            <span class="font-semibold text-slate-800">{{ headUser.name }}</span>
            to
            <span class="font-semibold text-slate-800">{{ counterpartyUser.name }}</span> :
          </p>

          <div class="overflow-x-auto">
            <div class="grid gap-4 sm:grid-cols-2">
              <table class="w-full min-w-[320px] overflow-hidden rounded-lg border border-slate-200 text-sm">
                <thead>
                  <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                    <th class="px-3 py-2">{{ headUser.name }}</th>
                    <th class="px-3 py-2">Cash</th>
                    <th class="px-3 py-2">RTGS</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Grams</th>
                    <th class="px-3 py-2">Purity</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="bg-emerald-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                    <td class="px-3 py-2">{{ headUser.rak_cash_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headUser.rak_rtgs_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{ (headUser.rak_cash_balance + headUser.rak_rtgs_balance).toLocaleString() }}
                    </td>
                    <td class="px-3 py-2">{{ headUser.grams_grand_total.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headUser.purity_grand_total.toLocaleString() }}</td>
                  </tr>
                  <tr class="bg-rose-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">CB</td>
                    <td class="px-3 py-2">{{ headClosingCash.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headUser.rak_rtgs_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{ (headClosingCash + headUser.rak_rtgs_balance).toLocaleString() }}
                    </td>
                    <td class="px-3 py-2 text-slate-400">—</td>
                    <td class="px-3 py-2 text-slate-400">—</td>
                  </tr>
                </tbody>
              </table>

              <table class="w-full min-w-[320px] overflow-hidden rounded-lg border border-slate-200 text-sm">
                <thead>
                  <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                    <th class="px-3 py-2">{{ counterpartyUser.name }}</th>
                    <th class="px-3 py-2">Cash</th>
                    <th class="px-3 py-2">RTGS</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Grams</th>
                    <th class="px-3 py-2">Purity</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="bg-emerald-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                    <td class="px-3 py-2">{{ counterpartyUser.rak_cash_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ counterpartyUser.rak_rtgs_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{
                        (counterpartyUser.rak_cash_balance + counterpartyUser.rak_rtgs_balance).toLocaleString()
                      }}
                    </td>
                    <td class="px-3 py-2">{{ counterpartyUser.grams_grand_total.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ counterpartyUser.purity_grand_total.toLocaleString() }}</td>
                  </tr>
                  <tr class="bg-rose-50/60 text-slate-400">
                    <td class="px-3 py-2 font-medium">CB</td>
                    <td class="px-3 py-2" colspan="5">Not updated by this entry</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <BaseSelect
            id="sg_item"
            :model-value="itemId"
            label="Item"
            required
            size="sm"
            placeholder="Choose item…"
            :options="itemOptions"
            :error="fieldErrors.item_id"
            @update:model-value="(v) => (itemId = v as number | null)"
          />

          <BaseInput
            id="sg_grams"
            :model-value="grams === null ? '' : String(grams)"
            label="Grams"
            type="number"
            step="0.001"
            required
            size="sm"
            :error="fieldErrors.grams"
            @update:model-value="(v) => (grams = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="sg_touch"
            :model-value="touch === null ? '' : String(touch)"
            label="Touch"
            type="number"
            step="0.01"
            required
            size="sm"
            :error="fieldErrors.touch"
            @update:model-value="(v) => (touch = v === '' ? null : Number(v))"
          />

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Purity</label>
            <input
              disabled
              :value="purity === null ? '' : purity"
              placeholder="Grams × touch ÷ 100"
              class="w-full cursor-not-allowed rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500"
            />
          </div>

          <BaseInput
            id="sg_per_gram_cash"
            :model-value="perGramCash === null ? '' : String(perGramCash)"
            label="Per Purity Cash"
            type="number"
            step="0.01"
            required
            size="sm"
            :error="fieldErrors.per_gram_cash"
            @update:model-value="(v) => (perGramCash = v === '' ? null : Number(v))"
          />

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Total Cash</label>
            <input
              disabled
              :value="totalCash.toLocaleString()"
              class="w-full cursor-not-allowed rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500"
            />
            <p class="mt-1 text-xs text-slate-400">Sum of the payment sources below.</p>
          </div>

          <BaseCheckbox v-model="amountTransferToHead" label="Amount transfer to head" />

          <div>
            <div class="mb-2 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-slate-900">Amount Sources</h3>
              <BaseButton variant="secondary" type="button" :icon="Plus" @click="addSourceRow">
                Add source
              </BaseButton>
            </div>
            <p v-if="fieldErrors.amount_sources" class="mb-2 text-sm text-red-600">
              {{ fieldErrors.amount_sources }}
            </p>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="w-10 px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      S.No.
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Source
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Bank
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Amount
                    </th>
                    <th class="w-10 px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(row, index) in amountSources" :key="index">
                    <td class="px-3 py-2 text-slate-500">{{ index + 1 }}</td>
                    <td class="px-3 py-2">
                      <BaseSelect
                        :model-value="row.source_type"
                        size="sm"
                        :options="sourceTypeOptions"
                        @update:model-value="(v) => (row.source_type = v as SaleGoldSourceType)"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <BaseSelect
                        :model-value="row.bank_id"
                        size="sm"
                        placeholder="Choose bank…"
                        :disabled="row.source_type !== 'BANK'"
                        :options="bankOptions"
                        :error="fieldErrors[`amount_sources.${index}.bank_id`]"
                        @update:model-value="(v) => onSourceBankChange(row, v as number | null)"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <BaseInput
                        :model-value="row.amount === null ? '' : String(row.amount)"
                        type="number"
                        step="0.01"
                        size="sm"
                        :error="fieldErrors[`amount_sources.${index}.amount`]"
                        @update:model-value="(v) => (row.amount = v === '' ? null : Number(v))"
                      />
                    </td>
                    <td class="px-3 py-2 text-right">
                      <button
                        type="button"
                        class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                        aria-label="Remove source"
                        :disabled="amountSources.length === 1"
                        @click="removeSourceRow(index)"
                      >
                        <Trash class="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
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
