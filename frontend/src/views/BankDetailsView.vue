<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type { BankDetail, BankDetailFormValues, DataTableColumn } from '@/types'

const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')

const columns: DataTableColumn<BankDetail>[] = [
  { key: 'account_name', label: 'Bank' },
  { key: 'ledger_balance', label: 'Ledger balance' },
  { key: 'is_active', label: 'Status' },
  { key: 'created_at', label: 'Added' },
  { key: 'bank_id', label: '' },
]

async function loadBanks() {
  isLoading.value = true
  loadError.value = ''
  try {
    banks.value = await bankDetailsApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load banks.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadBanks)

const filteredBanks = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return banks.value
  return banks.value.filter((b) => b.account_name.toLowerCase().includes(query))
})

const emptyForm: BankDetailFormValues = {
  account_name: '',
  ledger_balance: 0,
  is_active: true,
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<BankDetailFormValues>({ ...emptyForm })
const formError = ref('')
const isSaving = ref(false)

function openCreateForm() {
  editingId.value = null
  Object.assign(form, emptyForm)
  formError.value = ''
  isFormOpen.value = true
}

function openEditForm(bank: BankDetail) {
  editingId.value = bank.bank_id
  Object.assign(form, {
    account_name: bank.account_name,
    ledger_balance: bank.ledger_balance,
    is_active: bank.is_active,
  })
  formError.value = ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
}

async function handleSubmit() {
  if (!form.account_name.trim()) {
    formError.value = 'Bank name is required.'
    return
  }

  isSaving.value = true
  formError.value = ''
  try {
    if (editingId.value !== null) {
      await bankDetailsApi.update(editingId.value, { ...form })
    } else {
      await bankDetailsApi.create({ ...form })
    }
    isFormOpen.value = false
    await loadBanks()
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Failed to save bank.'
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(bank: BankDetail) {
  deletingId.value = bank.bank_id
  try {
    await bankDetailsApi.remove(bank.bank_id)
    await loadBanks()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete bank.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Bank Details" description="Manage bank accounts used in cash transactions.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadBanks">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New bank</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit bank' : 'New bank' }}
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

      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="handleSubmit">
        <BaseInput
          id="account_name"
          v-model="form.account_name"
          label="Bank name"
          placeholder="HDFC Bank"
          required
          maxlength="150"
          :error="formError"
        />
        <BaseInput
          id="ledger_balance"
          :model-value="form.ledger_balance === null ? '' : String(form.ledger_balance)"
          label="Ledger balance"
          type="number"
          step="0.001"
          @update:model-value="(v) => (form.ledger_balance = v === '' ? null : Number(v))"
        />

        <BaseCheckbox v-model="form.is_active" label="Active" class="sm:col-span-2" />

        <div class="flex items-center gap-3 sm:col-span-2">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create bank' }}
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
        placeholder="Search banks…"
        aria-label="Search banks"
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
      Loading banks…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredBanks"
      empty-message="No banks yet. Add your first one to get started."
    >
      <template #ledger_balance="{ value }">{{ Number(value).toLocaleString() }}</template>

      <template #is_active="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #created_at="{ value }">{{ value ? formatDateTime(value as string) : '—' }}</template>

      <template #bank_id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit bank"
            @click="openEditForm(row as BankDetail)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete ${(row as BankDetail).account_name}?`"
            @confirm="handleDelete(row as BankDetail)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete bank"
                :disabled="deletingId === (row as BankDetail).bank_id"
                @click="toggle"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </ConfirmPopover>
        </div>
      </template>
    </DataTable>
  </div>
</template>
