<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RefreshCw, UserPlus, Store } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import ItemChangeModal from '@/components/stock-txn/ItemChangeModal.vue'
import ItemConversionModal from '@/components/stock-txn/ItemConversionModal.vue'
import NumericWastageOutModal from '@/components/stock-txn/NumericWastageOutModal.vue'
import NumericWastageInModal from '@/components/stock-txn/NumericWastageInModal.vue'
import AddUserModal from '@/components/user/AddUserModal.vue'
import HeadStockSummaryPanel from '@/components/stock/HeadStockSummaryPanel.vue'
import TransactionHistoryPanel from '@/components/stock/TransactionHistoryPanel.vue'
import UserPickerPanel from '@/components/stock/UserPickerPanel.vue'
import CustomerContextPanel from '@/components/stock/CustomerContextPanel.vue'
import StockOutPanel from '@/components/stock/StockOutPanel.vue'
import StockInPanel from '@/components/stock/StockInPanel.vue'
import GmsOutPanel from '@/components/stock/GmsOutPanel.vue'
import GmsInPanel from '@/components/stock/GmsInPanel.vue'
import type { Item, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Stock Management — consolidated OUT/IN quick-action page
|--------------------------------------------------------------------------
| Rebuilds the legacy "Stock" screen's two-column OUT/IN button layout,
| following the same activeModal-ref + modal-per-action pattern as
| CashManagementView.vue. Unlike Cash Management, there's no page-level
| Head/User gate here — each stock form already collects its own
| given_by/given_to (or user_id, for Item Change/Conversion) internally,
| so all 8 actions are available as soon as items/users have loaded.
|
| Replaces the former separate routes: Stock Out, Stock In, GMS Out,
| GMS In, Numeric Wastage In (now modals here), plus two screens that
| had no frontend before at all: Item Change, Item Conversion, and
| Numeric Wastage Out.
|
| Known gaps (backend, not fixed here — see project rules):
|  - Item Change / Item Conversion need stock_in_id (an existing stock
|    lot); no endpoint lists a user's lots yet, so it's a plain numeric
|    input for now.
|  - POST /v1/stock/hide (route added in PR #31, "postHide" controller
|    method) is BROKEN: it type-hints HideStockRequest, but that class
|    doesn't exist anywhere in schainbackend — every call 500s with
|    "Class HideStockRequest not found". Do NOT wire a "Hide" action to
|    this endpoint until backend adds the missing FormRequest. Flagged
|    to the backend team; re-check on next pull.
|
| "Add User" / "Add Retailer" (header actions) both open AddUserModal —
| there's no separate Retailer entity in the backend, just a user that
| later shows up in a "Retailer" picker (see AddUserModal.vue's comment).
|
| The legacy screen's item-wise stock summary panel (METAL/GOLD/FITEM/
| STONE totals with grams/%/purity, Cash, Active Orders) is now
| HeadStockSummaryPanel, and the Transaction History table is now
| TransactionHistoryPanel — both below, backed by GET
| /stock-details/head-stocks and GET /stock-details/history respectively
| (neither route existed when this view was first built — recheck the
| backend on every pull, per project rules; POST /stock/numeric-waste-in
| used by NumericWastageInModal similarly went from unreachable to
| registered between pulls).
|
| selectedUserId (UserPickerPanel, left column) scopes both
| TransactionHistoryPanel (via employee_id) and CustomerContextPanel
| (Customer Touch/photo/comments/Deliver, right column, legacy screen's
| lower-middle block) — the latter only renders once a user is picked.
| Customer Deliver has no backing endpoint yet (order_details table
| doesn't exist) and the comments textarea isn't wired to save — see
| CustomerContextPanel's own comment.
|
| Stock Out/In and GMS Out/In ("NEW OUT"/"NEW IN"/"GMS OUT"/"GMS IN") are
| StockOutPanel/StockInPanel/GmsOutPanel/GmsInPanel, not modals — the
| legacy screen never opens a dialog for these: clicking a button appends
| a row to that action's own dense inline grid (OUT column: Stock Out
| above GMS Out; IN column: Stock In above GMS In), and rows just sit
| there ("stored in session") until the ONE shared Submit button below
| fires every panel that has pending rows at once. Each panel still posts
| to its own distinct endpoint separately (POST /stock/out, /stock/in,
| /stock/gms-out, /stock/gms-in) — this is NOT merged into a single
| combined request (e.g. the Auto Entry endpoint), just triggered
| together. handleSubmitAll calls each panel's exposed submit() via
| Promise.allSettled and only refreshes Head Stocks/Transaction History
| once afterward if at least one actually succeeded. The shared OUT/
| TOTAL/IN bar sums each panel's exposed `totals` computed.
|
| given_by/given_to come from page context (the logged-in head +
| selectedUserId) rather than per-row pickers on any of the four panels —
| see StockOutPanel's comment for the full contract each one implements
| (addRow/submit/clear/hasRows/totals).
|
| Item Change/Conversion and Numeric Wastage Out/In still use the old
| modal pattern for now.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()
const toastStore = useToastStore()

const items = ref<Item[]>([])
const users = ref<UserDetailListItem[]>([])
const isLoading = ref(false)
const loadError = ref('')
const selectedUserId = ref<number | null>(null)
const isSubmittingAll = ref(false)

const headStockPanelRef = ref<InstanceType<typeof HeadStockSummaryPanel> | null>(null)
const txnHistoryPanelRef = ref<InstanceType<typeof TransactionHistoryPanel> | null>(null)
const stockOutPanelRef = ref<InstanceType<typeof StockOutPanel> | null>(null)
const stockInPanelRef = ref<InstanceType<typeof StockInPanel> | null>(null)
const gmsOutPanelRef = ref<InstanceType<typeof GmsOutPanel> | null>(null)
const gmsInPanelRef = ref<InstanceType<typeof GmsInPanel> | null>(null)

const entryPanels = computed(() =>
  [stockOutPanelRef.value, stockInPanelRef.value, gmsOutPanelRef.value, gmsInPanelRef.value].filter(
    (p): p is NonNullable<typeof p> => p !== null,
  ),
)

const zeroTotals = { grams: 0, purity: 0, wastage: 0 }
function sumTotals(...parts: Array<{ grams: number; purity: number; wastage: number }>) {
  return parts.reduce(
    (acc, t) => ({ grams: acc.grams + t.grams, purity: acc.purity + t.purity, wastage: acc.wastage + t.wastage }),
    { grams: 0, purity: 0, wastage: 0 },
  )
}
const outTotals = computed(() =>
  sumTotals(stockOutPanelRef.value?.totals ?? zeroTotals, gmsOutPanelRef.value?.totals ?? zeroTotals),
)
const inTotals = computed(() =>
  sumTotals(stockInPanelRef.value?.totals ?? zeroTotals, gmsInPanelRef.value?.totals ?? zeroTotals),
)
const grandTotals = computed(() => sumTotals(outTotals.value, inTotals.value))
function formatTotal(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [itemsData, usersData] = await Promise.all([
      itemsApi.list(),
      userDetailsApi.list(undefined, 'stock'),
    ])
    items.value = itemsData
    users.value = usersData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load items/users.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

type ActiveModal =
  | 'item-change'
  | 'item-conversion'
  | 'numeric-wastage-out'
  | 'numeric-wastage-in'
  | 'add-user'
  | 'add-retailer'
  | null

const activeModal = ref<ActiveModal>(null)

function openModal(modal: ActiveModal) {
  activeModal.value = modal
}
function closeModal() {
  activeModal.value = null
}
function handleSaved() {
  activeModal.value = null
}
// Add User / Add Retailer create a user_details row that the "Given
// by/to"/"Retailer" pickers in the other modals need to see immediately,
// so reload the shared users list instead of just closing.
async function handleUserSaved() {
  activeModal.value = null
  await loadData()
}

function handleAddStockOutRow() {
  stockOutPanelRef.value?.addRow()
}
function handleAddStockInRow() {
  stockInPanelRef.value?.addRow()
}
function handleAddGmsOutRow() {
  gmsOutPanelRef.value?.addRow()
}
function handleAddGmsInRow() {
  gmsInPanelRef.value?.addRow()
}
function handleStockChanged() {
  headStockPanelRef.value?.refresh()
  txnHistoryPanelRef.value?.refresh()
}

function handleClearAll() {
  entryPanels.value.forEach((p) => p.clear())
}

async function handleSubmitAll() {
  const pending = entryPanels.value.filter((p) => p.hasRows())
  if (pending.length === 0) {
    toastStore.show('No entries to submit — add at least one row first.', 'error')
    return
  }

  isSubmittingAll.value = true
  const results = await Promise.allSettled(pending.map((p) => p.submit()))
  isSubmittingAll.value = false

  const anySucceeded = results.some((r) => r.status === 'fulfilled' && r.value === true)
  if (anySucceeded) handleStockChanged()
}
</script>

<template>
  <div>
    <PageHeader
      title="Stock"
      description="Record stock, GMS, item change/conversion, and numeric wastage movements."
    >
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton variant="secondary" :icon="UserPlus" @click="openModal('add-user')">Add User</BaseButton>
        <BaseButton variant="secondary" :icon="Store" @click="openModal('add-retailer')">Add Retailer</BaseButton>
      </template>
    </PageHeader>

    <div
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-[minmax(0,340px)_1fr]">
      <div class="space-y-6">
        <HeadStockSummaryPanel ref="headStockPanelRef" />
        <UserPickerPanel v-model="selectedUserId" />
      </div>
      <div class="space-y-6">
        <TransactionHistoryPanel ref="txnHistoryPanelRef" :items="items" :employee-id="selectedUserId" />
        <CustomerContextPanel
          v-if="selectedUserId"
          :user-id="selectedUserId"
          :users="users"
          @users-changed="loadData"
        />
      </div>
    </div>

    <div
      v-if="isLoading"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading…
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-2">
      <div class="space-y-3">
        <p class="text-xs font-semibold tracking-wide text-red-700 uppercase">Out</p>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
            @click="handleAddStockOutRow"
          >
            Stock Out
          </button>
          <button
            type="button"
            class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-slate-700"
            @click="openModal('item-change')"
          >
            Item Change
          </button>
          <button
            type="button"
            class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-slate-700"
            @click="openModal('item-conversion')"
          >
            Item Conversion
          </button>
          <button
            type="button"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
            @click="handleAddGmsOutRow"
          >
            GMS Out
          </button>
          <button
            type="button"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
            @click="openModal('numeric-wastage-out')"
          >
            Numeric Wastage Out
          </button>
        </div>
        <StockOutPanel
          ref="stockOutPanelRef"
          :items="items"
          :head-id="auth.user?.user_id ?? null"
          :given-to="selectedUserId"
          @saved="handleStockChanged"
        />
        <GmsOutPanel
          ref="gmsOutPanelRef"
          :items="items"
          :head-id="auth.user?.user_id ?? null"
          :given-to="selectedUserId"
          @saved="handleStockChanged"
        />
      </div>

      <div class="space-y-3">
        <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">In</p>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
            @click="handleAddStockInRow"
          >
            Stock In
          </button>
          <button
            type="button"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
            @click="handleAddGmsInRow"
          >
            GMS In
          </button>
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
            @click="openModal('numeric-wastage-in')"
          >
            Numeric Wastage In
          </button>
        </div>
        <StockInPanel
          ref="stockInPanelRef"
          :items="items"
          :head-id="auth.user?.user_id ?? null"
          :given-by="selectedUserId"
          @saved="handleStockChanged"
        />
        <GmsInPanel
          ref="gmsInPanelRef"
          :items="items"
          :head-id="auth.user?.user_id ?? null"
          :given-by="selectedUserId"
          @saved="handleStockChanged"
        />
      </div>
    </div>

    <div
      v-if="entryPanels.some((p) => p.hasRows())"
      class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-4 py-3"
    >
      <div class="flex flex-wrap gap-8 text-sm">
        <div>
          <p class="text-xs font-semibold tracking-wide text-red-700 uppercase">Out</p>
          <p class="text-slate-700">Grams: <span class="font-semibold tabular-nums">{{ formatTotal(outTotals.grams) }}</span></p>
          <p class="text-slate-700">Purity: <span class="font-semibold tabular-nums text-amber-700">{{ formatTotal(outTotals.purity) }}</span></p>
          <p class="text-slate-700">Wastage: <span class="font-semibold tabular-nums text-red-700">{{ formatTotal(outTotals.wastage) }}</span></p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Total</p>
          <p class="text-slate-700">Grams: <span class="font-semibold tabular-nums">{{ formatTotal(grandTotals.grams) }}</span></p>
          <p class="text-slate-700">Purity: <span class="font-semibold tabular-nums text-amber-700">{{ formatTotal(grandTotals.purity) }}</span></p>
          <p class="text-slate-700">Wastage: <span class="font-semibold tabular-nums text-red-700">{{ formatTotal(grandTotals.wastage) }}</span></p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">In</p>
          <p class="text-slate-700">Grams: <span class="font-semibold tabular-nums">{{ formatTotal(inTotals.grams) }}</span></p>
          <p class="text-slate-700">Purity: <span class="font-semibold tabular-nums text-amber-700">{{ formatTotal(inTotals.purity) }}</span></p>
          <p class="text-slate-700">Wastage: <span class="font-semibold tabular-nums text-red-700">{{ formatTotal(inTotals.wastage) }}</span></p>
        </div>
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="isSubmittingAll"
          @click="handleClearAll"
        >
          Clear
        </button>
        <button
          type="button"
          class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="isSubmittingAll"
          @click="handleSubmitAll"
        >
          {{ isSubmittingAll ? 'Submitting…' : 'Submit' }}
        </button>
      </div>
    </div>

    <ItemChangeModal
      v-if="activeModal === 'item-change'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
    <ItemConversionModal
      v-if="activeModal === 'item-conversion'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
    <NumericWastageOutModal
      v-if="activeModal === 'numeric-wastage-out'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
    <NumericWastageInModal
      v-if="activeModal === 'numeric-wastage-in'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
    <AddUserModal
      v-if="activeModal === 'add-user'"
      title="Add User"
      @close="closeModal"
      @saved="handleUserSaved"
    />
    <AddUserModal
      v-if="activeModal === 'add-retailer'"
      title="Add Retailer"
      @close="closeModal"
      @saved="handleUserSaved"
    />
  </div>
</template>
