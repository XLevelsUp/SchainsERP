<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import CashTxnEntryModal from '@/components/cash-txn/CashTxnEntryModal.vue'
import CashToGoldModal from '@/components/cash-txn/CashToGoldModal.vue'
import GoldToCashModal from '@/components/cash-txn/GoldToCashModal.vue'
import SaleGoldModal from '@/components/cash-txn/SaleGoldModal.vue'
import PurchaseGoldModal from '@/components/cash-txn/PurchaseGoldModal.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { rolesApi } from '@/lib/rolesApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { ApiError } from '@/lib/api'
import type { BankDetail, Item, Role, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Cash Management — Head/Role/User picker driving quick-action buttons
|--------------------------------------------------------------------------
| Rebuilds the legacy "Cash Management" screen's workflow: pick a Head,
| optionally narrow the User picker by Role, pick a User, then quick-action
| buttons appear and open an in-context modal.
|
| Two things from the legacy screen are NOT rebuildable against the current
| backend and are left out rather than faked:
|  - The Out/In ledger tables — cash_txn_details has no working GET/list
|    endpoint (index/show/update/destroy still reference sender_id-era
|    columns that don't exist; only postIncome/postExpense work). From
|    Date/To Date/Print are dropped for the same reason — nothing to filter
|    or print.
|  - INTERNAL and AUTO_ENTRY buttons — no current endpoint accepts either
|    as a transaction type. Shown disabled with an explanation instead of
|    silently vanishing, so it's clear this is a gap, not an oversight.
|--------------------------------------------------------------------------
*/

const loadError = ref('')
const isLoading = ref(false)

const heads = ref<UserDetailListItem[]>([])
const roles = ref<Role[]>([])
const filteredUsers = ref<UserDetailListItem[]>([])
const banks = ref<BankDetail[]>([])
const items = ref<Item[]>([])

const headId = ref<number | null>(null)
const roleFilter = ref<string>('')
const userId = ref<number | null>(null)

async function loadBaseData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [headsData, rolesData, allUsers, banksData, itemsData] = await Promise.all([
      userDetailsApi.list('HEAD'),
      rolesApi.list(),
      userDetailsApi.list(),
      bankDetailsApi.list(),
      itemsApi.list(),
    ])
    heads.value = headsData
    roles.value = rolesData
    filteredUsers.value = allUsers
    banks.value = banksData
    items.value = itemsData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load Cash Management data.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadBaseData)

async function onRoleFilterChange(role: string) {
  roleFilter.value = role
  userId.value = null
  try {
    filteredUsers.value = await userDetailsApi.list(role || undefined)
  } catch {
    // Keep the previous list rather than blanking the picker on a transient failure.
  }
}

const headOptions = computed(() => heads.value.map((h) => ({ value: h.id, label: h.name })))
const roleOptions = computed(() => [
  { value: '', label: 'All roles' },
  ...roles.value.map((r) => ({ value: r.role, label: r.role })),
])
const userOptions = computed(() => filteredUsers.value.map((u) => ({ value: u.id, label: u.name })))

const headUser = computed(() => heads.value.find((h) => h.id === headId.value) ?? null)
const selectedUser = computed(() => filteredUsers.value.find((u) => u.id === userId.value) ?? null)

/*
|--------------------------------------------------------------------------
| Balance badges — fetched on selection (list rows don't carry balances)
|--------------------------------------------------------------------------
*/

const headBalance = reactive({ loading: false, cash: 0, rtgs: 0 })
const userBalance = reactive({ loading: false, cash: 0, rtgs: 0 })

async function refreshHeadBalance() {
  if (headId.value === null) return
  headBalance.loading = true
  try {
    const { user } = await userDetailsApi.get(headId.value)
    headBalance.cash = user.rak_cash_balance
    headBalance.rtgs = user.rak_rtgs_balance
  } catch {
    // Balance badge is a convenience, not required for the flow to work.
  } finally {
    headBalance.loading = false
  }
}

async function refreshUserBalance() {
  if (userId.value === null) return
  userBalance.loading = true
  try {
    const { user } = await userDetailsApi.get(userId.value)
    userBalance.cash = user.rak_cash_balance
    userBalance.rtgs = user.rak_rtgs_balance
  } catch {
    // Balance badge is a convenience, not required for the flow to work.
  } finally {
    userBalance.loading = false
  }
}

watch(headId, refreshHeadBalance)
watch(userId, refreshUserBalance)

function onHeadChange(id: number | null) {
  headId.value = id
}

/*
|--------------------------------------------------------------------------
| Quick-action modals
|--------------------------------------------------------------------------
*/

type ActiveModal = 'out' | 'in' | 'cash-to-gold' | 'gold-to-cash' | 'sale-gold' | 'purchase-gold' | null

const activeModal = ref<ActiveModal>(null)
const canQuickCreate = computed(
  () => headId.value !== null && userId.value !== null && headId.value !== userId.value,
)

function openModal(modal: ActiveModal) {
  activeModal.value = modal
}
function closeModal() {
  activeModal.value = null
}
async function handleSaved() {
  activeModal.value = null
  await Promise.all([refreshHeadBalance(), refreshUserBalance()])
}
</script>

<template>
  <div>
    <PageHeader
      title="Cash Management"
      description="Pick a Head and a User to record cash, gold, or conversion transactions between them."
    />

    <BaseCard class="mb-6">
      <div class="grid gap-3 sm:grid-cols-3">
        <BaseSelect
          id="head"
          :model-value="headId"
          label="Head"
          size="sm"
          placeholder="Select a head…"
          :options="headOptions"
          @update:model-value="(v) => onHeadChange(v as number | null)"
        />
        <BaseSelect
          id="role"
          :model-value="roleFilter"
          label="Roles"
          size="sm"
          :options="roleOptions"
          @update:model-value="(v) => onRoleFilterChange(v as string)"
        />
        <BaseSelect
          id="user"
          :model-value="userId"
          label="Users"
          size="sm"
          placeholder="Choose user…"
          :options="userOptions"
          @update:model-value="(v) => (userId = v as number | null)"
        />
      </div>

      <div v-if="headUser || selectedUser" class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
        <p v-if="headUser">
          <template v-if="headBalance.loading">Loading balance…</template>
          <template v-else>
            Cash: {{ headBalance.cash.toLocaleString() }} · RTGS: {{ headBalance.rtgs.toLocaleString() }}
          </template>
        </p>
        <p></p>
        <p v-if="selectedUser">
          <template v-if="userBalance.loading">Loading balance…</template>
          <template v-else>
            Cash: {{ userBalance.cash.toLocaleString() }} · RTGS: {{ userBalance.rtgs.toLocaleString() }}
          </template>
        </p>
      </div>
    </BaseCard>

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
      Loading…
    </div>

    <template v-else>
      <div v-if="canQuickCreate" class="grid gap-6 sm:grid-cols-2">
        <div>
          <p class="mb-2 text-xs font-semibold tracking-wide text-red-700 uppercase">Out</p>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
              @click="openModal('out')"
            >
              OUT
            </button>
            <button
              type="button"
              class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
              @click="openModal('purchase-gold')"
            >
              Purchase Gold
            </button>
            <button
              type="button"
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700"
              @click="openModal('gold-to-cash')"
            >
              Gold To Cash
            </button>
            <button
              type="button"
              disabled
              title="No backend endpoint for INTERNAL transactions yet"
              class="cursor-not-allowed rounded-lg bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-500"
            >
              INTERNAL
            </button>
          </div>
        </div>

        <div>
          <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-700 uppercase">In</p>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
              @click="openModal('in')"
            >
              IN
            </button>
            <button
              type="button"
              class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
              @click="openModal('cash-to-gold')"
            >
              Cash To Gold
            </button>
            <button
              type="button"
              class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-purple-700"
              @click="openModal('sale-gold')"
            >
              Sale Gold
            </button>
            <button
              type="button"
              disabled
              title="No backend endpoint for AUTO_ENTRY transactions yet"
              class="cursor-not-allowed rounded-lg bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-500"
            >
              AUTO_ENTRY
            </button>
          </div>
        </div>
      </div>

      <p v-else-if="headId !== null && userId !== null" class="text-sm text-slate-500">
        Pick a different User than the Head to record a transaction between them.
      </p>
      <p v-else-if="headId !== null" class="text-sm text-slate-500">
        Select a User above to record a new transaction with this head.
      </p>
      <p v-else class="text-sm text-slate-500">Select a Head above to get started.</p>

      <p class="mt-6 text-xs text-slate-500">
        No transaction ledger here — the backend has no working list endpoint for cash
        transactions (only posting new ones). Use these buttons to record entries; there's
        nothing to browse afterward yet.
      </p>
    </template>

    <CashTxnEntryModal
      v-if="(activeModal === 'out' || activeModal === 'in') && headUser && selectedUser"
      :head-id="headUser.id"
      :head-name="headUser.name"
      :user-id="selectedUser.id"
      :user-name="selectedUser.name"
      :direction="activeModal"
      :banks="banks"
      @close="closeModal"
      @saved="handleSaved"
    />

    <CashToGoldModal
      v-if="activeModal === 'cash-to-gold' && headUser && selectedUser"
      :head-id="headUser.id"
      :head-name="headUser.name"
      :customer-id="selectedUser.id"
      :customer-name="selectedUser.name"
      :items="items"
      :banks="banks"
      @close="closeModal"
      @saved="handleSaved"
    />

    <GoldToCashModal
      v-if="activeModal === 'gold-to-cash' && headUser && selectedUser"
      :head-id="headUser.id"
      :head-name="headUser.name"
      :customer-id="selectedUser.id"
      :customer-name="selectedUser.name"
      :banks="banks"
      @close="closeModal"
      @saved="handleSaved"
    />

    <SaleGoldModal
      v-if="activeModal === 'sale-gold' && headUser && selectedUser"
      :head-id="headUser.id"
      :head-name="headUser.name"
      :customer-id="selectedUser.id"
      :customer-name="selectedUser.name"
      :items="items"
      :banks="banks"
      @close="closeModal"
      @saved="handleSaved"
    />

    <PurchaseGoldModal
      v-if="activeModal === 'purchase-gold' && headUser && selectedUser"
      :head-id="headUser.id"
      :head-name="headUser.name"
      :customer-id="selectedUser.id"
      :customer-name="selectedUser.name"
      :items="items"
      :banks="banks"
      @close="closeModal"
      @saved="handleSaved"
    />
  </div>
</template>
