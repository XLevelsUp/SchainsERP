<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Plus, Trash } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import { cashToGoldApi } from '@/lib/cashToGoldApi'
import { sourceTypeOptions } from '@/lib/cashTxnOptions'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { BankDetail, CashToGoldAmountSourceInput, CashTxnSourceType, Item } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash To Gold modal — customer gives cash, head gives gold
|--------------------------------------------------------------------------
| Same POST /cash-to-gold flow as the standalone Cash To Gold page, minus
| the head/customer pickers — those are already fixed by whatever the Cash
| Management hub had selected when this was opened.
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

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))
const bankOptions = computed(() =>
  props.banks.map((b) => ({ value: b.bank_id, label: b.account_name || `#${b.bank_id}` })),
)

function makeEmptySource(): CashToGoldAmountSourceInput {
  return { source: 'CASH_ON_HAND', bank_id: null, amount: null }
}

const form = reactive({
  item_id: null as number | null,
  total_grams: null as number | null,
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

const purity = computed(() => {
  if (form.total_grams === null || form.touch === null) return null
  return Number(((form.total_grams * form.touch) / 100).toFixed(4))
})
const totalCash = computed(() => {
  if (purity.value === null || form.per_gram_cash === null) return null
  return Number((purity.value * form.per_gram_cash).toFixed(2))
})

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
  () => Math.round(sourceTotal.value * 100) === Math.round((totalCash.value ?? 0) * 100),
)

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.item_id === null) fieldErrors.item_id = 'Item is required.'
  if (form.total_grams === null || form.total_grams < 0.001) {
    fieldErrors.total_grams = 'Total grams is required (min 0.001).'
  }
  if (form.touch === null || form.touch < 0 || form.touch > 100) {
    fieldErrors.touch = 'Touch is required (0–100).'
  }
  if (form.per_gram_cash === null || form.per_gram_cash < 0.01) {
    fieldErrors.per_gram_cash = 'Per-gram cash is required (min 0.01).'
  }
  if (purity.value === null || purity.value < 0.001) {
    fieldErrors.purity = 'Purity works out to 0 — check total grams and touch.'
  }
  if (totalCash.value === null || totalCash.value < 0.01) {
    fieldErrors.total_cash = 'Total cash works out to 0 — check purity and per-gram cash.'
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
        `Payment sources total (${sourceTotal.value.toFixed(2)}) must equal total cash (${(totalCash.value ?? 0).toFixed(2)}).`
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
    await cashToGoldApi.create(
      {
        head_id: props.headId,
        customer_id: props.customerId,
        item_id: form.item_id,
        total_grams: form.total_grams,
        touch: form.touch,
        per_gram_cash: form.per_gram_cash,
        purity: purity.value,
        total_cash: totalCash.value,
        amnt_transfer_to_head: form.amnt_transfer_to_head,
        remarks: form.remarks,
        retailer_id: form.retailer_id,
        amount_sources: form.amount_sources.map((s) => ({ ...s })),
      },
      images.value,
    )
    toastStore.show('Cash To Gold transaction saved successfully.', 'success')
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
      formError.value = 'Failed to save Cash To Gold transaction.'
      toastStore.show('Failed to save Cash To Gold transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal
    title="Cash To Gold"
    :badge="`${customerName} to ${headName}`"
    badge-class="bg-red-600"
    @close="emit('close')"
  >
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <form class="flex flex-col gap-5" @submit.prevent="handleSubmit">
      <div class="grid gap-3 sm:grid-cols-3">
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
          id="total_grams"
          :model-value="form.total_grams === null ? '' : String(form.total_grams)"
          label="Total grams"
          type="number"
          step="0.001"
          required
          size="sm"
          :error="fieldErrors.total_grams"
          @update:model-value="(v) => (form.total_grams = v === '' ? null : Number(v))"
        />
        <BaseInput
          id="touch"
          :model-value="form.touch === null ? '' : String(form.touch)"
          label="Touch"
          type="number"
          step="0.01"
          required
          size="sm"
          :error="fieldErrors.touch"
          @update:model-value="(v) => (form.touch = v === '' ? null : Number(v))"
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
          id="per_gram_cash"
          :model-value="form.per_gram_cash === null ? '' : String(form.per_gram_cash)"
          label="Per-gram cash"
          type="number"
          step="0.01"
          required
          size="sm"
          :error="fieldErrors.per_gram_cash"
          @update:model-value="(v) => (form.per_gram_cash = v === '' ? null : Number(v))"
        />
        <BaseInput
          id="total_cash"
          :model-value="totalCash === null ? '' : String(totalCash)"
          label="Total cash (calculated)"
          type="number"
          readonly
          size="sm"
          :error="fieldErrors.total_cash"
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
          size="sm"
          @update:model-value="(v) => (form.retailer_id = v === '' ? null : Number(v))"
        />
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
            step="0.01"
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
          <span>Total cash: {{ (totalCash ?? 0).toFixed(2) }}</span>
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
