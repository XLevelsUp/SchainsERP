<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, X, RefreshCw, Trash } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { saleGoldApi } from '@/lib/saleGoldApi'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import { formatDateTime } from '@/lib/date'
import type {
  BankDetail,
  DataTableColumn,
  Item,
  SaleGoldAmountSourceInput,
  SaleGoldFormValues,
  SaleGoldRecord,
  SaleGoldSourceType,
  UserDetail,
} from '@/types'

const toastStore = useToastStore()

const sales = ref<SaleGoldRecord[]>([])
const items = ref<Item[]>([])
const users = ref<UserDetail[]>([])
const banks = ref<BankDetail[]>([])
// There's no endpoint left to derive this from — the cash_txn_details
// index/list endpoint was dropped in PR #13 (see cashTxnDetailsApi.ts).
// opening_account_balance is sent to the backend as-is, so this now
// always starts at 0 and must be entered manually until SaleGoldController
// exposes a real balance lookup.
const latestAccountBalance = ref(0)
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const sourceTypeOptions: { value: SaleGoldSourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]

function userLabel(user: UserDetail) {
  return `${user.name} (${user.user_name})`
}
const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.user_id, label: userLabel(u) })),
)
const itemOptions = computed(() => items.value.map((i) => ({ value: i.item_id, label: i.item_name })))
const bankOptions = computed(() => banks.value.map((b) => ({ value: b.bank_id, label: b.bank_name })))

function userName(id: number | null) {
  if (id === null) return '—'
  return users.value.find((u) => u.user_id === id)?.name ?? `#${id}`
}
function itemName(id: number) {
  return items.value.find((i) => i.item_id === id)?.item_name ?? `#${id}`
}

/*
|--------------------------------------------------------------------------
| List + search
|--------------------------------------------------------------------------
*/

const columns: DataTableColumn<SaleGoldRecord>[] = [
  { key: 'item_id', label: 'Item' },
  { key: 'head_id', label: 'Head' },
  { key: 'customer_id', label: 'Customer' },
  { key: 'total_grams', label: 'Grams' },
  { key: 'touch', label: 'Touch' },
  { key: 'purity', label: 'Purity' },
  { key: 'total_cash', label: 'Total cash' },
  { key: 'amountSources', label: 'Sources' },
  { key: 'added_at', label: 'Added' },
]

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [salesData, itemsData, usersData, banksData] = await Promise.all([
      saleGoldApi.list(),
      itemsApi.list(),
      userDetailsApi.list(),
      bankDetailsApi.list(),
    ])
    sales.value = salesData
    items.value = itemsData
    users.value = usersData
    banks.value = banksData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load sale gold records.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

const filteredSales = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return sales.value
  return sales.value.filter((s) => {
    const haystack = [
      itemName(s.item_id),
      userName(s.head_id),
      s.customer_id !== null ? userName(s.customer_id) : '',
      s.remarks ?? '',
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

function makeEmptyForm(): SaleGoldFormValues {
  return {
    head_id: null,
    customer_id: null,
    item_id: null,
    stock_id: null,
    grams: null,
    touch: null,
    purity: null,
    per_gram_cash: null,
    total_cash: null,
    amount_transfer_to_head: true,
    remarks: '',
    opening_account_balance: latestAccountBalance.value,
    opening_user_balance: 0,
    amount_sources: [makeEmptySource()],
  }
}

const isFormOpen = ref(false)
const form = reactive<SaleGoldFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function openCreateForm() {
  Object.assign(form, makeEmptyForm())
  formError.value = ''
  clearFieldErrors()
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

function onHeadChange(userId: number | null) {
  form.head_id = userId
  const user = users.value.find((u) => u.user_id === userId)
  if (user) form.opening_user_balance = user.rak_cash_balance
}

// grams * touch / 100 — a starting suggestion only; purity stays a
// required, independently-editable field (the backend never derives it).
function suggestPurity() {
  if (form.grams !== null && form.touch !== null) {
    form.purity = Number(((form.grams * form.touch) / 100).toFixed(4))
  }
}

function addSource() {
  form.amount_sources.push(makeEmptySource())
}
function removeSource(index: number) {
  form.amount_sources.splice(index, 1)
}
function onSourceBankChange(source: SaleGoldAmountSourceInput, bankId: number | null) {
  source.bank_id = bankId
  const bank = banks.value.find((b) => b.bank_id === bankId)
  source.bank_name = bank?.bank_name ?? ''
  source.opening_bank_account_balance = bank?.current_balance ?? null
}

const sourceTotal = computed(() =>
  form.amount_sources.reduce((sum, s) => sum + (s.amount ?? 0), 0),
)
const sourceTotalMatches = computed(
  () => Math.round(sourceTotal.value * 100) === Math.round((form.total_cash ?? 0) * 100),
)

/*
|--------------------------------------------------------------------------
| Validation — mirrors SaleGoldController::store() exactly
|--------------------------------------------------------------------------
*/

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  if (form.head_id === null) fieldErrors.head_id = 'Head is required.'
  if (form.item_id === null) fieldErrors.item_id = 'Item is required.'

  if (form.grams === null || form.grams < 0.001) {
    fieldErrors.grams = 'Grams is required (min 0.001).'
  }
  if (form.touch === null || form.touch < 0) {
    fieldErrors.touch = 'Touch is required.'
  }
  if (form.purity === null || form.purity < 0) {
    fieldErrors.purity = 'Purity is required.'
  }
  if (form.per_gram_cash === null || form.per_gram_cash < 0) {
    fieldErrors.per_gram_cash = 'Per-gram cash is required.'
  }
  if (form.total_cash === null || form.total_cash < 0.01) {
    fieldErrors.total_cash = 'Total cash is required (min 0.01).'
  }
  if (form.opening_account_balance === null) {
    fieldErrors.opening_account_balance = 'Opening account balance is required.'
  }
  if (form.opening_user_balance === null) {
    fieldErrors.opening_user_balance = 'Opening user balance is required.'
  }

  if (form.amount_sources.length === 0) {
    fieldErrors.amount_sources = 'Add at least one payment source.'
  }

  form.amount_sources.forEach((source, index) => {
    if (source.amount === null || source.amount < 0.01) {
      fieldErrors[`amount_sources.${index}.amount`] = `Row ${index + 1}: amount is required (min 0.01).`
    }
    if (source.source_type === 'BANK') {
      if (source.bank_id === null) {
        fieldErrors[`amount_sources.${index}.bank_id`] = `Row ${index + 1}: select a bank.`
      }
    }
  })

  if (!sourceTotalMatches.value) {
    fieldErrors.amount_sources_total =
      `Payment sources total (${sourceTotal.value.toFixed(2)}) must equal total cash (${(form.total_cash ?? 0).toFixed(2)}).`
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
    await saleGoldApi.create({ ...form, amount_sources: form.amount_sources.map((s) => ({ ...s })) })
    isFormOpen.value = false
    toastStore.show('Sale gold entry created successfully.', 'success')
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
      formError.value = 'Failed to create sale gold entry.'
      toastStore.show('Failed to create sale gold entry.', 'error')
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
      description="Record gold sold for cash, split across one or more payment sources."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New sale</BaseButton>
      </template>
    </PageHeader>

    <p class="mb-4 text-xs text-slate-500">
      Records here can be created and viewed, but not edited or deleted — the backend doesn't
      support that yet.
    </p>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">New sale</h2>
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
        <!-- Gold details -->
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
            @update:model-value="(v) => onHeadChange(v as number | null)"
          />
          <BaseSelect
            id="customer_id"
            :model-value="form.customer_id"
            label="Customer (optional)"
            size="sm"
            placeholder="None"
            :options="userOptions"
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
                suggestPurity()
              }
            "
          />

          <BaseInput
            id="grams"
            :model-value="form.grams === null ? '' : String(form.grams)"
            label="Grams"
            type="number"
            step="0.001"
            required
            size="sm"
            :error="fieldErrors.grams"
            @update:model-value="
              (v) => {
                form.grams = v === '' ? null : Number(v)
                suggestPurity()
              }
            "
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
            @update:model-value="
              (v) => {
                form.touch = v === '' ? null : Number(v)
                suggestPurity()
              }
            "
          />
          <BaseInput
            id="purity"
            :model-value="form.purity === null ? '' : String(form.purity)"
            label="Purity"
            type="number"
            step="0.0001"
            required
            size="sm"
            :error="fieldErrors.purity"
            @update:model-value="(v) => (form.purity = v === '' ? null : Number(v))"
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
            :model-value="form.total_cash === null ? '' : String(form.total_cash)"
            label="Total cash"
            type="number"
            step="0.01"
            required
            size="sm"
            :error="fieldErrors.total_cash"
            @update:model-value="(v) => (form.total_cash = v === '' ? null : Number(v))"
          />
          <BaseCheckbox
            v-model="form.amount_transfer_to_head"
            label="Transfer amount to head"
            class="self-end pb-2"
          />

          <BaseInput
            id="stock_id"
            :model-value="form.stock_id === null ? '' : String(form.stock_id)"
            label="Stock ID (optional)"
            type="number"
            size="sm"
            placeholder="No stock lookup yet"
            @update:model-value="(v) => (form.stock_id = v === '' ? null : Number(v))"
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

        <!-- Opening balances -->
        <section class="border-t border-slate-200 pt-4">
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Opening balances</h3>
          <div class="grid gap-3 sm:grid-cols-3">
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
          </div>
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
              :id="`source_type_${index}`"
              :model-value="source.source_type"
              label="Source"
              size="sm"
              :options="sourceTypeOptions"
              @update:model-value="(v) => (source.source_type = v as SaleGoldSourceType)"
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

            <template v-if="source.source_type === 'BANK'">
              <BaseSelect
                :id="`source_bank_${index}`"
                :model-value="source.bank_id"
                label="Bank"
                size="sm"
                placeholder="Select a bank…"
                :options="bankOptions"
                :error="fieldErrors[`amount_sources.${index}.bank_id`]"
                @update:model-value="(v) => onSourceBankChange(source, v as number | null)"
              />
              <BaseInput
                :id="`source_bank_ob_${index}`"
                :model-value="
                  source.opening_bank_account_balance === null
                    ? ''
                    : String(source.opening_bank_account_balance)
                "
                label="Opening bank balance"
                type="number"
                step="0.01"
                size="sm"
                @update:model-value="
                  (v) => (source.opening_bank_account_balance = v === '' ? null : Number(v))
                "
              />
              <BaseInput
                :id="`source_bank_user_ob_${index}`"
                :model-value="
                  source.opening_bank_user_balance === null
                    ? ''
                    : String(source.opening_bank_user_balance)
                "
                label="Opening bank user balance"
                type="number"
                step="0.01"
                size="sm"
                @update:model-value="
                  (v) => (source.opening_bank_user_balance = v === '' ? null : Number(v))
                "
              />
            </template>
          </div>

          <div
            class="flex items-center justify-between rounded-lg px-3 py-2 text-sm"
            :class="sourceTotalMatches ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
          >
            <span>Payment sources total: {{ sourceTotal.toFixed(2) }}</span>
            <span>Total cash: {{ (form.total_cash ?? 0).toFixed(2) }}</span>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : 'Create sale' }}
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
        placeholder="Search sales…"
        aria-label="Search sales"
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
      Loading sale gold records…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredSales"
      empty-message="No sale gold entries yet. Record your first sale to get started."
    >
      <template #item_id="{ value }">{{ itemName(value as number) }}</template>
      <template #head_id="{ value }">{{ userName(value as number) }}</template>
      <template #customer_id="{ value }">{{ userName(value as number | null) }}</template>
      <template #total_grams="{ value }">{{ Number(value).toFixed(3) }}</template>
      <template #touch="{ value }">{{ Number(value).toFixed(2) }}</template>
      <template #purity="{ value }">{{ Number(value).toFixed(4) }}</template>
      <template #total_cash="{ value }">{{ Number(value).toLocaleString() }}</template>

      <template #amountSources="{ row }">
        <div class="flex flex-wrap gap-1">
          <span
            v-for="source in (row as SaleGoldRecord).amountSources"
            :key="source.id"
            class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
          >
            {{ source.souce_type === 'BANK' ? 'Bank' : 'Cash' }}: {{ Number(source.amount).toLocaleString() }}
          </span>
        </div>
      </template>

      <template #added_at="{ value }">{{ formatDateTime(value as string) }}</template>
    </DataTable>
  </div>
</template>
