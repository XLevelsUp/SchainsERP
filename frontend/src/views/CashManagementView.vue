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
import CashAutoEntryModal from '@/components/cash-txn/CashAutoEntryModal.vue'
import CashTxnHistoryTable from '@/components/cash-txn/CashTxnHistoryTable.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { rolesApi } from '@/lib/rolesApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { userOptionLabel } from '@/lib/userLabel'
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
| History tables (below the quick actions) use PR #17's GET .../in-history
| and .../out-history — scoped to this head/user pair via head_id +
| cash_user_id. Coverage: out-history = EXPENSE, PURCHASE_GOLD,
| GOLD_TO_CASH; in-history = INCOME, AUTO_ENTRY, CASH_TO_GOLD, SALE_GOLD.
| Two things still don't show up there, both backend-side gaps rather than
| a frontend limitation:
|  - INTERNAL — no current endpoint accepts it as a transaction type, and
|    no service ever writes an INTERNAL_TRANSFER row for the history
|    filter to find. Shown disabled with an explanation instead of
|    silently vanishing.
|  - Purchase Gold's "Out Cash Converter" and Sale Gold's "In Cash
|    Converter" sub-types are written with those literal type values, but
|    neither history filter includes them — those transactions are
|    recorded successfully but won't appear in the history below.
|
| AUTO_ENTRY is wired up (PR #16 added the endpoint) — see
| CashAutoEntryModal.vue for the balance-direction caveat (only the head's
| side moves).
|
| Head picker: NOT filtered by ?type=HEAD. Role numbering has proven
| unreliable across seeders — StockTestDataSeeder assigns "Head Admin"
| role_id=1, which RoleSeeder defines as CUSTOMER, not HEAD, so a
| role-filtered picker would hide the very user meant to be the head.
| Showing every user for both Head and User avoids depending on role_id
| being right; the "Roles" dropdown still exists purely as an optional
| narrower for the User picker, since that's the legacy UI shape.
|--------------------------------------------------------------------------
*/

const loadError = ref('')
const isLoading = ref(false)

const allUsers = ref<UserDetailListItem[]>([])
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
    const [usersData, rolesData, banksData, itemsData] = await Promise.all([
      userDetailsApi.list(undefined, 'cash'),
      rolesApi.list(),
      bankDetailsApi.list(),
      itemsApi.list(),
    ])
    allUsers.value = usersData
    roles.value = rolesData
    filteredUsers.value = usersData
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
    filteredUsers.value = await userDetailsApi.list(role || undefined, 'cash')
  } catch {
    // Keep the previous list rather than blanking the picker on a transient failure.
  }
}

const headOptions = computed(() =>
  allUsers.value.map((h) => ({ value: h.id, label: userOptionLabel(h) })),
)
const roleOptions = computed(() => [
  { value: '', label: 'All roles' },
  ...roles.value.map((r) => ({ value: r.role, label: r.role })),
])
const userOptions = computed(() =>
  filteredUsers.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)

const headUser = computed(() => allUsers.value.find((h) => h.id === headId.value) ?? null)
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

type ActiveModal =
  | 'out'
  | 'in'
  | 'cash-to-gold'
  | 'gold-to-cash'
  | 'sale-gold'
  | 'purchase-gold'
  | 'auto-entry'
  | null

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

// Bumped so CashTxnHistoryTable re-fetches page 1 and picks up whatever
// was just recorded, without needing a full page reload.
const historyRefreshKey = ref(0)

async function handleSaved() {
  activeModal.value = null
  historyRefreshKey.value += 1
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
      <p v-if="canQuickCreate" class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">
        Manual Entries
      </p>
      <div v-if="canQuickCreate" class="grid gap-6 sm:grid-cols-2">
        <div>
          <p class="mb-2 text-xs font-semibold tracking-wide text-red-700 uppercase">Pay</p>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
              @click="openModal('out')"
            >
              PAY
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
          <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-700 uppercase">Receive</p>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
              @click="openModal('in')"
            >
              RECEIVE
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
              class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
              @click="openModal('auto-entry')"
            >
              Auto Entry
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

      <div v-if="canQuickCreate" class="mt-8 grid gap-6 sm:grid-cols-2">
        <div>
          <p class="mb-2 text-xs font-semibold tracking-wide text-red-700 uppercase">Out History</p>
          <CashTxnHistoryTable
            direction="out"
            :head-id="headId!"
            :user-id="userId!"
            :refresh-key="historyRefreshKey"
          />
        </div>
        <div>
          <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-700 uppercase">In History</p>
          <CashTxnHistoryTable
            direction="in"
            :head-id="headId!"
            :user-id="userId!"
            :refresh-key="historyRefreshKey"
          />
        </div>
      </div>

      <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        INTERNAL is disabled — no backend endpoint accepts that transaction type yet. Purchase Gold's
        "Out Cash Converter" and Sale Gold's "In Cash Converter" are recorded successfully but don't
        appear in the history above — the backend's history filters don't include those two
        sub-types. Both are known backend gaps, not missing screens.
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

    <CashAutoEntryModal
      v-if="activeModal === 'auto-entry' && headUser && selectedUser"
      :head-id="headUser.id"
      :head-name="headUser.name"
      :cash-user-id="selectedUser.id"
      :cash-user-name="selectedUser.name"
      @close="closeModal"
      @saved="handleSaved"
    />
  </div>
</template>
