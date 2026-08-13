<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, RefreshCw, Trash } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import { saleGoldApi } from '@/lib/saleGoldApi'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { sourceTypeOptions } from '@/lib/cashTxnOptions'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type {
  BankDetail,
  CashTxnSourceType,
  Item,
  SaleGoldAmountSourceInput,
  SaleGoldFormValues,
  SaleGoldRecord,
  SaleGoldType,
  UserDetailListItem,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Sale Gold — head sells gold to a customer for cash (POST /sale-gold)
|--------------------------------------------------------------------------
| PR #15 replaced the old apiResource (index/store/show, with update/
| destroy 500ing) with a single POST route backed by SaleGoldService — this
| is now create-only like Cash To Gold/Gold To Cash, so there's no ledger
| table here anymore either. `type` now drives real accounting behavior
| server-side (which side's balances move, stock IN vs OUT), not just a
| label — exposed here as a real selector rather than hardcoding SALE_GOLD.
|--------------------------------------------------------------------------
*/

const toastStore = useToastStore()

const typeOptions: { value: SaleGoldType; label: string }[] = [
  { value: 'SALE_GOLD', label: 'Sale Gold (head sells to customer)' },
  { value: 'SALE_GOLD_CASH', label: 'Sale Gold Cash' },
  { value: 'IN_CASH_CONVERTER', label: 'In Cash Converter' },
]

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])
const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')

function bankLabel(bank: BankDetail) {
  return bank.account_name || `#${bank.bank_id}`
}

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))
const bankOptions = computed(() => banks.value.map((b) => ({ value: b.bank_id, label: bankLabel(b) })))

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [usersData, itemsData, banksData] = await Promise.all([
      userDetailsApi.list(undefined, 'cash'),
      itemsApi.list(),
      bankDetailsApi.list(),
    ])
    users.value = usersData
    items.value = itemsData
    banks.value = banksData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load users/items/banks.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

/*
|--------------------------------------------------------------------------
| Form state
|--------------------------------------------------------------------------
*/

function makeEmptySource(): SaleGoldAmountSourceInput {
  return { source: 'CASH_ON_HAND', bank_id: null, amount: null }
}

function makeEmptyForm(): Omit<SaleGoldFormValues, 'purity' | 'total_cash'> {
  return {
    type: 'SALE_GOLD',
    head_id: null,
    customer_id: null,
    per_gram_cash: null,
    total_grams: null,
    touch: null,
    item_id: null,
    amnt_transfer_to_head: true,
    remarks: '',
    retailer_id: null,
    stock_in_id: null,
    amount_sources: [makeEmptySource()],
  }
}

const form = reactive(makeEmptyForm())
const images = ref<File[]>([])
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)
const lastResult = ref<SaleGoldRecord | null>(null)

// SaleGoldService never cross-checks purity/total_cash against grams/touch/
// per_gram_cash — it just trusts whatever's sent, same as Cash To Gold/Gold
// To Cash. So both are computed here, never directly editable: purity from
// grams * touch/100 (pure gold weight), total_cash from purity *
// per_gram_cash — the same formula both of those services use.
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

function resetForm() {
  Object.assign(form, makeEmptyForm())
  images.value = []
  clearFieldErrors()
  formError.value = ''
  lastResult.value = null
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

/*
|--------------------------------------------------------------------------
| Validation — mirrors StoreSaleGoldRequest::rules() exactly
|--------------------------------------------------------------------------
*/

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.head_id === null) fieldErrors.head_id = 'Head is required.'
  if (form.customer_id === null) fieldErrors.customer_id = 'Customer is required.'
  if (form.item_id === null) fieldErrors.item_id = 'Item is required.'

  if (form.total_grams === null || form.total_grams < 0) {
    fieldErrors.total_grams = 'Total grams is required.'
  }
  if (form.touch === null || form.touch < 0 || form.touch > 100) {
    fieldErrors.touch = 'Touch is required (0–100).'
  }
  if (form.per_gram_cash === null || form.per_gram_cash < 0) {
    fieldErrors.per_gram_cash = 'Per-gram cash is required.'
  }
  if (purity.value === null) {
    fieldErrors.purity = 'Purity could not be calculated — check total grams and touch.'
  }
  if (totalCash.value === null) {
    fieldErrors.total_cash = 'Total cash could not be calculated — check purity and per-gram cash.'
  }

  // Mirrors StoreSaleGoldRequest's images.* rule (image|mimes:jpg,jpeg,png,webp|max:5120).
  const oversized = images.value.find((f) => f.size > 5 * 1024 * 1024)
  if (oversized) {
    fieldErrors.images = `${oversized.name} is over 5 MB — remove it or use a smaller image.`
  }

  if (form.amnt_transfer_to_head) {
    if (form.amount_sources.length === 0) {
      fieldErrors.amount_sources = 'Add at least one payment source.'
    }
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
    const result = await saleGoldApi.create(
      {
        ...form,
        purity: purity.value,
        total_cash: totalCash.value,
        amount_sources: form.amount_sources.map((s) => ({ ...s })),
      },
      images.value,
    )
    lastResult.value = result
    toastStore.show('Sale Gold transaction saved successfully.', 'success')
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
      formError.value = 'Failed to save Sale Gold transaction.'
      toastStore.show('Failed to save Sale Gold transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Sale Gold"
      description="Head sells gold to a customer for cash, split across one or more payment sources."
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
        Saved Sale Gold #{{ lastResult.cash_to_gold_id }}.
        <template v-if="lastResult.head">
          Head cash balance: {{ lastResult.head.cash_balance }} · Head grams total:
          {{ lastResult.head.grams_grand_total }}
        </template>
      </p>

      <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <section class="grid gap-3 sm:grid-cols-3">
          <BaseSelect
            id="type"
            :model-value="form.type"
            label="Type"
            required
            size="sm"
            :options="typeOptions"
            @update:model-value="(v) => (form.type = v as SaleGoldType)"
          />
          <BaseSelect
            id="head_id"
            :model-value="form.head_id"
            label="Head"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.head_id"
            @update:model-value="(v) => (form.head_id = v as number | null)"
          />
          <BaseSelect
            id="customer_id"
            :model-value="form.customer_id"
            label="Customer"
            required
            size="sm"
            placeholder="Select a user…"
            :options="userOptions"
            :error="fieldErrors.customer_id"
            @update:model-value="(v) => (form.customer_id = v as number | null)"
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
            step="0.001"
            required
            size="sm"
            :error="fieldErrors.touch"
            @update:model-value="(v) => (form.touch = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="purity"
            :model-value="purity === null ? '' : String(purity)"
            label="Purity (calculated = grams × touch / 100)"
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
            step="0.001"
            required
            size="sm"
            :error="fieldErrors.per_gram_cash"
            @update:model-value="(v) => (form.per_gram_cash = v === '' ? null : Number(v))"
          />
          <BaseInput
            id="total_cash"
            :model-value="totalCash === null ? '' : String(totalCash)"
            label="Total cash (calculated = purity × per-gram cash)"
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
            step="1"
            size="sm"
            @update:model-value="(v) => (form.retailer_id = v === '' ? null : Number(v))"
          />
          <BaseInput
            id="stock_in_id"
            :model-value="form.stock_in_id === null ? '' : String(form.stock_in_id)"
            label="Stock In ID (optional)"
            type="number"
            step="1"
            size="sm"
            placeholder="No stock lookup yet"
            @update:model-value="(v) => (form.stock_in_id = v === '' ? null : Number(v))"
          />

          <BaseTextarea
            id="remarks"
            v-model="form.remarks"
            label="Remarks"
            size="sm"
            :rows="2"
            class="sm:col-span-3"
          />
        </section>

        <!-- Receipt images -->
        <section class="border-t border-slate-200 pt-4">
          <BaseFileInput
            id="images"
            v-model="images"
            label="Receipt images (optional)"
            hint="JPG, PNG, or WEBP, up to 5 MB each."
          />
          <p v-if="fieldErrors.images" class="mt-2 text-sm text-red-600">{{ fieldErrors.images }}</p>
        </section>

        <!-- Payment sources -->
        <section v-if="form.amnt_transfer_to_head" class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Payment sources</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addSource">
              Add source
            </BaseButton>
          </div>

          <p v-if="fieldErrors.amount_sources" class="mb-2 text-sm text-red-600">
            {{ fieldErrors.amount_sources }}
          </p>

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
            <span>Payment sources total: {{ sourceTotal.toFixed(2) }}</span>
            <span>Total cash: {{ (totalCash ?? 0).toFixed(2) }}</span>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving || isLoading">
            {{ isSaving ? 'Saving…' : 'Save Sale Gold' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="resetForm">Clear</BaseButton>
        </div>
      </form>
    </BaseCard>
  </div>
</template>
