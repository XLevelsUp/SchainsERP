<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, RefreshCw, Trash } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import { goldToCashApi } from '@/lib/goldToCashApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { sourceTypeOptions } from '@/lib/cashTxnOptions'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type {
  BankDetail,
  CashToGoldAmountSourceInput,
  CashTxnSourceType,
  GoldToCashFormValues,
  GoldToCashRecord,
  UserDetailListItem,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Gold To Cash — customer gives gold, head gives cash (POST /gold-to-cash)
|--------------------------------------------------------------------------
| GoldToCashController also defines index()/show()/destroy(), but only
| store() is routed in routes/api.php, so this is create-only — no ledger
| view to build here, same situation as Cash Transactions/Sale Gold.
|
| amount_sources is always required here — unlike Cash To Gold, the head
| always pays out (amnt_transfer_to_head=1 is hardcoded server-side), so
| there's no "transfer to head" toggle in this form.
|--------------------------------------------------------------------------
*/

const toastStore = useToastStore()

const users = ref<UserDetailListItem[]>([])
const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')

function bankLabel(bank: BankDetail) {
  return bank.account_name || `#${bank.bank_id}`
}

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
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

/*
|--------------------------------------------------------------------------
| Form state
|--------------------------------------------------------------------------
*/

function makeEmptySource(): CashToGoldAmountSourceInput {
  return { source: 'CASH_ON_HAND', bank_id: null, amount: null }
}

function makeEmptyForm(): Omit<GoldToCashFormValues, 'total_grams' | 'total_cash'> {
  return {
    head_id: null,
    customer_id: null,
    total_gold: null,
    touch: null,
    per_gram_cash: null,
    remarks: '',
    retailer_id: null,
    amount_sources: [makeEmptySource()],
  }
}

const form = reactive(makeEmptyForm())
const images = ref<File[]>([])
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)
const lastResult = ref<GoldToCashRecord | null>(null)

// GoldToCashService::store() never cross-checks total_grams/total_cash
// against total_gold/touch/per_gram_cash — it just trusts whatever's sent.
// So both are computed here, never directly editable: total_grams from
// total_gold / (touch/100) (the inverse of Cash To Gold's purity formula,
// per StoreGoldToCashRequest's "total_gold = pure weight" / "total_grams =
// touch-adjusted weight" comments), and total_cash from total_gold *
// per_gram_cash (the exact formula GoldToCashService bakes into its audit
// remarks/billing entries) — guaranteeing the ledger is always internally
// consistent instead of risking a hand-typed mismatch going to the API.
const totalGrams = computed(() => {
  if (form.total_gold === null || !form.touch) return null
  return Number(((form.total_gold * 100) / form.touch).toFixed(4))
})
const totalCash = computed(() => {
  if (form.total_gold === null || form.per_gram_cash === null) return null
  return Number((form.total_gold * form.per_gram_cash).toFixed(2))
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
| Validation — mirrors StoreGoldToCashRequest::rules() exactly
|--------------------------------------------------------------------------
*/

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.head_id === null) fieldErrors.head_id = 'Head is required.'
  if (form.customer_id === null) fieldErrors.customer_id = 'Customer is required.'

  if (form.total_gold === null || form.total_gold < 0.001) {
    fieldErrors.total_gold = 'Total gold is required (min 0.001).'
  }
  if (form.touch === null || form.touch <= 0 || form.touch > 100) {
    fieldErrors.touch = 'Touch is required (>0–100).'
  }
  if (form.per_gram_cash === null || form.per_gram_cash < 0.01) {
    fieldErrors.per_gram_cash = 'Per-gram cash is required (min 0.01).'
  }
  if (totalGrams.value === null || totalGrams.value < 0.001) {
    fieldErrors.total_grams = 'Total grams works out to 0 — check total gold and touch.'
  }
  if (totalCash.value === null || totalCash.value < 0.01) {
    fieldErrors.total_cash = 'Total cash works out to 0 — check total gold and per-gram cash.'
  }

  // Mirrors StoreGoldToCashRequest's images.* rule (image|mimes:jpg,jpeg,png,webp|max:5120).
  const oversized = images.value.find((f) => f.size > 5 * 1024 * 1024)
  if (oversized) {
    fieldErrors.images = `${oversized.name} is over 5 MB — remove it or use a smaller image.`
  }

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
    const result = await goldToCashApi.create(
      {
        ...form,
        total_grams: totalGrams.value,
        total_cash: totalCash.value,
        amount_sources: form.amount_sources.map((s) => ({ ...s })),
      },
      images.value,
    )
    lastResult.value = result
    toastStore.show('Gold To Cash transaction saved successfully.', 'success')
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
      formError.value = 'Failed to save Gold To Cash transaction.'
      toastStore.show('Failed to save Gold To Cash transaction.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Gold To Cash"
      description="Customer hands over gold, head hands over cash. Splits payment across one or more sources."
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
        Saved Gold To Cash #{{ lastResult.cash_to_gold_id }}.
        <template v-if="lastResult.head">
          Head cash balance: {{ lastResult.head.cash_balance }} · Head grams total:
          {{ lastResult.head.grams_grand_total }}
        </template>
      </p>

      <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <section class="grid gap-3 sm:grid-cols-3">
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
          <BaseInput
            id="retailer_id"
            :model-value="form.retailer_id === null ? '' : String(form.retailer_id)"
            label="Retailer ID (optional)"
            type="number"
            size="sm"
            @update:model-value="(v) => (form.retailer_id = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="total_gold"
            :model-value="form.total_gold === null ? '' : String(form.total_gold)"
            label="Total gold (pure weight)"
            type="number"
            step="0.001"
            required
            size="sm"
            :error="fieldErrors.total_gold"
            @update:model-value="(v) => (form.total_gold = v === '' ? null : Number(v))"
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
            id="total_grams"
            :model-value="totalGrams === null ? '' : String(totalGrams)"
            label="Total grams (calculated = gold × 100 / touch)"
            type="number"
            readonly
            size="sm"
            :error="fieldErrors.total_grams"
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
            label="Total cash (calculated = gold × per-gram cash)"
            type="number"
            readonly
            size="sm"
            :error="fieldErrors.total_cash"
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
        <section class="border-t border-slate-200 pt-4">
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
            <span>Payment sources total: {{ sourceTotal.toFixed(2) }}</span>
            <span>Total cash: {{ (totalCash ?? 0).toFixed(2) }}</span>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving || isLoading">
            {{ isSaving ? 'Saving…' : 'Save Gold To Cash' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="resetForm">Clear</BaseButton>
        </div>
      </form>
    </BaseCard>
  </div>
</template>
