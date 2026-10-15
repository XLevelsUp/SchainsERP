<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ArrowLeftRight, Info } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DirectionTag from '@/components/ui/DirectionTag.vue'
import CashTxnEntryModal from '@/components/cash-txn/CashTxnEntryModal.vue'
import CashToGoldModal from '@/components/cash-txn/CashToGoldModal.vue'
import GoldToCashModal from '@/components/cash-txn/GoldToCashModal.vue'
import SaleGoldModal from '@/components/cash-txn/SaleGoldModal.vue'
import PurchaseGoldModal from '@/components/cash-txn/PurchaseGoldModal.vue'
import CashAutoEntryModal from '@/components/cash-txn/CashAutoEntryModal.vue'
import CashTxnHistoryTable from '@/components/cash-txn/CashTxnHistoryTable.vue'
import StockCashHistoryTable from '@/components/cash-txn/StockCashHistoryTable.vue'
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
| History tables use PR #17's GET .../in-history and .../out-history.
| Since PR #46 (2026-09-22, backend commit 60b3816 — PENDING_WORK.md #36,
| fixed) the backend accepts `head_id` alone, matching the legacy screen:
| Out = the head is the sender, In = the head is the recipient, with the
| picked User (if any) narrowing to that one counterparty on top. Before
| PR #46 both `head_id` and `cash_user_id` were required together, so the
| whole history section — and the Stock History panel below it — used to
| stay gated behind picking both Head and User; `canShowHistory` now only
| needs a Head. Coverage: out-history = EXPENSE, PURCHASE_GOLD,
| GOLD_TO_CASH; in-history = INCOME, AUTO_ENTRY, CASH_TO_GOLD, SALE_GOLD.
| Two things still don't show up there, both backend-side gaps rather than
| a frontend limitation:
|  - INTERNAL — no current endpoint accepts it as a transaction type, and
|    no service ever writes an INTERNAL_TRANSFER row for the history
|    filter to find. The quick-action button for it is hidden entirely
|    (user request) rather than shown disabled.
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

// Date range filter for the history tables below — applies only once a
// Head + User pair is selected. Both cash-txn-details history endpoints
// (in-history/out-history) already accept from_date/to_date server-side;
// stock-details/cash-transaction-history does not, so StockCashHistoryTable
// stays unfiltered by date (backend gap, not a frontend omission).
const historyFromDate = ref('')
const historyToDate = ref('')

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

// History reveals on Head alone, same as the legacy screen — the backend
// accepts a head-only query as of PR #46 (2026-09-22, backend commit
// 60b3816: PENDING_WORK.md #36, fixed). Picking a User still narrows it to
// that one counterparty; it is no longer required to see anything at all.
const canShowHistory = computed(() => headId.value !== null)

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

    <!--
      The two parties, each with its balance directly beneath it. Head sits
      left and the User right, with Roles grouped beside Users because it
      only narrows that picker. Tab order is unchanged: Head, Roles, Users.
    -->
    <BaseCard :padded="false" class="mb-6">
      <div
        class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1.6fr)] sm:items-start"
      >
        <div class="space-y-2">
          <BaseSelect
            id="head"
            :model-value="headId"
            label="Head"
            size="sm"
            placeholder="Select a head…"
            :options="headOptions"
            @update:model-value="(v) => onHeadChange(v as number | null)"
          />
          <p v-if="headUser && headBalance.loading" class="text-xs text-slate-500">Loading balance…</p>
          <dl v-else-if="headUser" class="flex flex-wrap gap-x-5 gap-y-1">
            <div class="flex items-baseline gap-1.5">
              <dt class="text-xs text-slate-500">Cash</dt>
              <dd class="font-figure text-sm font-medium tabular-nums text-ink-900">
                {{ headBalance.cash.toLocaleString() }}
              </dd>
            </div>
            <div class="flex items-baseline gap-1.5">
              <dt class="text-xs text-slate-500">RTGS</dt>
              <dd class="font-figure text-sm font-medium tabular-nums text-ink-900">
                {{ headBalance.rtgs.toLocaleString() }}
              </dd>
            </div>
          </dl>
        </div>

        <div class="hidden pt-7 text-slate-300 sm:block" aria-hidden="true">
          <ArrowLeftRight class="h-5 w-5" />
        </div>

        <div class="space-y-2">
          <div class="grid gap-3 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]">
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
          <p v-if="selectedUser && userBalance.loading" class="text-xs text-slate-500">Loading balance…</p>
          <dl v-else-if="selectedUser" class="flex flex-wrap gap-x-5 gap-y-1">
            <div class="flex items-baseline gap-1.5">
              <dt class="text-xs text-slate-500">Cash</dt>
              <dd class="font-figure text-sm font-medium tabular-nums text-ink-900">
                {{ userBalance.cash.toLocaleString() }}
              </dd>
            </div>
            <div class="flex items-baseline gap-1.5">
              <dt class="text-xs text-slate-500">RTGS</dt>
              <dd class="font-figure text-sm font-medium tabular-nums text-ink-900">
                {{ userBalance.rtgs.toLocaleString() }}
              </dd>
            </div>
          </dl>
        </div>
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
      <!--
        Same seven actions, same order, same modals. Colour now follows the
        cash direction (oxblood = cash leaves the head, verdigris = cash
        reaches it) instead of one colour per button, and each group names
        both parties, so the direction can be checked before opening a form.
      -->
      <section v-if="canQuickCreate" aria-labelledby="manual-entries-heading">
        <h2 id="manual-entries-heading" class="mb-2 text-sm font-semibold text-slate-900">
          Manual Entries
        </h2>
        <BaseCard :padded="false">
          <div class="grid divide-y divide-slate-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
            <div class="space-y-3 p-4">
              <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <DirectionTag direction="out">Pay</DirectionTag>
                <span class="truncate text-xs text-slate-500">
                  {{ headUser?.name }} <span aria-hidden="true">→</span><span class="sr-only">to</span>
                  {{ selectedUser?.name }}
                </span>
              </div>
              <div class="flex flex-wrap gap-2">
                <BaseButton variant="outflow" @click="openModal('out')">PAY</BaseButton>
                <BaseButton variant="outflow-soft" @click="openModal('purchase-gold')">Purchase Gold</BaseButton>
                <BaseButton variant="outflow-soft" @click="openModal('gold-to-cash')">Gold To Cash</BaseButton>
              </div>
            </div>

            <div class="space-y-3 p-4">
              <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <DirectionTag direction="in">Receive</DirectionTag>
                <span class="truncate text-xs text-slate-500">
                  {{ selectedUser?.name }} <span aria-hidden="true">→</span><span class="sr-only">to</span>
                  {{ headUser?.name }}
                </span>
              </div>
              <div class="flex flex-wrap gap-2">
                <BaseButton variant="inflow" @click="openModal('in')">RECEIVE</BaseButton>
                <BaseButton variant="inflow-soft" @click="openModal('cash-to-gold')">Cash To Gold</BaseButton>
                <BaseButton variant="inflow-soft" @click="openModal('sale-gold')">Sale Gold</BaseButton>
                <BaseButton variant="inflow-soft" @click="openModal('auto-entry')">Auto Entry</BaseButton>
              </div>
            </div>
          </div>
        </BaseCard>
      </section>

      <p
        v-else-if="headId !== null && userId !== null"
        class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600"
      >
        Pick a different User than the Head to record a transaction between them.
      </p>
      <p
        v-else-if="headId !== null"
        class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600"
      >
        Select a User above to record a new transaction with this head.
      </p>
      <p v-else class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600">
        Select a Head above to get started.
      </p>

      <section v-if="canShowHistory" class="mt-8" aria-labelledby="cash-history-heading">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 id="cash-history-heading" class="text-sm font-semibold text-slate-900">Cash history</h2>
            <p class="mt-0.5 text-xs text-slate-500">
              <template v-if="selectedUser">Between {{ headUser?.name }} and {{ selectedUser.name }}.</template>
              <template v-else>Everything {{ headUser?.name }} paid or received. Pick a User to narrow it.</template>
            </p>
          </div>
          <div class="grid w-full grid-cols-2 gap-2 sm:w-80">
            <BaseInput
              id="history-from-date"
              v-model="historyFromDate"
              label="From date"
              type="date"
              size="sm"
              clearable
            />
            <BaseInput
              id="history-to-date"
              v-model="historyToDate"
              label="To date"
              type="date"
              size="sm"
              clearable
            />
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <div>
            <h3 class="mb-2"><DirectionTag direction="out">Out history</DirectionTag></h3>
            <CashTxnHistoryTable
              direction="out"
              :head-id="headId!"
              :user-id="userId"
              :from-date="historyFromDate"
              :to-date="historyToDate"
              :refresh-key="historyRefreshKey"
            />
          </div>
          <div>
            <h3 class="mb-2"><DirectionTag direction="in">In history</DirectionTag></h3>
            <CashTxnHistoryTable
              direction="in"
              :head-id="headId!"
              :user-id="userId"
              :from-date="historyFromDate"
              :to-date="historyToDate"
              :refresh-key="historyRefreshKey"
            />
          </div>
        </div>
      </section>

      <p
        class="mt-6 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
      >
        <Info class="mt-px h-3.5 w-3.5 shrink-0 text-amber-700" aria-hidden="true" />
        <span>
          Purchase Gold's "Out Cash Converter" and Sale Gold's "In Cash Converter" are recorded
          successfully but don't appear in the history above — the backend's history filters don't
          include those two sub-types. This is a known backend gap, not a missing screen.
        </span>
      </p>

      <section v-if="canShowHistory" class="mt-8" aria-labelledby="stock-history-heading">
        <h2 id="stock-history-heading" class="text-sm font-semibold text-slate-900">Stock history</h2>
        <p class="mt-0.5 mb-3 text-xs text-slate-500">
          The gold side of Purchase Gold, Sale Gold, Cash To Gold and Gold To Cash. Not filtered by the
          dates above.
        </p>
        <StockCashHistoryTable :head-id="headId!" :user-id="userId" :refresh-key="historyRefreshKey" />
      </section>
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
