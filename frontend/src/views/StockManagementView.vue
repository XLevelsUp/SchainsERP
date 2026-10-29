<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Plus, RefreshCw, RotateCcw, UserPlus } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DirectionTag from '@/components/ui/DirectionTag.vue'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import AddUserModal from '@/components/user/AddUserModal.vue'
import HeadStockSummaryPanel from '@/components/stock/HeadStockSummaryPanel.vue'
import TransactionHistoryPanel from '@/components/stock/TransactionHistoryPanel.vue'
import ViewNotesPanel from '@/components/stock/ViewNotesPanel.vue'
import UserPickerPanel from '@/components/stock/UserPickerPanel.vue'
import CustomerContextPanel from '@/components/stock/CustomerContextPanel.vue'
import RetailerPickerPanel from '@/components/stock/RetailerPickerPanel.vue'
import StockOutPanel from '@/components/stock/StockOutPanel.vue'
import StockInPanel from '@/components/stock/StockInPanel.vue'
import NumericWastageOutPanel from '@/components/stock/NumericWastageOutPanel.vue'
import ItemChangeModal from '@/components/stock/ItemChangeModal.vue'
import ItemConversionModal from '@/components/stock/ItemConversionModal.vue'
import GmsOutModal from '@/components/stock/GmsOutModal.vue'
import GmsInModal from '@/components/stock/GmsInModal.vue'
import NumericWastageInModal from '@/components/stock/NumericWastageInModal.vue'
import CashOutModal from '@/components/stock/CashOutModal.vue'
// Reused rather than rebuilt: it already handles both directions and every
// payment method, and Cash In here is the same transaction Cash Management
// records — see the project's component-first rule.
import CashTxnEntryModal from '@/components/cash-txn/CashTxnEntryModal.vue'
import type { BankDetail, Item, UserDetailListItem } from '@/types'

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
| Both of the long-standing gaps here closed in PR #42 — re-verified
| against the backend source on 2026-09-15 rather than trusted:
|  - Item Change / Item Conversion can now attach a real stock_in_id for
|    ANY item, not just Metal, via GET /stock-details/available-lots. Both
|    modals open the shared lot picker; see ItemChangeModal's comment.
|  - POST /v1/stock/hide is reachable and HideStockRequest now exists
|    (app/Http/Requests/HideStockRequest.php, rules: stock_ids array of
|    ids that must exist). Wired to the selection checkboxes in
|    TransactionHistoryPanel. The earlier note here said the class was
|    missing and every call 500'd; that was true when written and is not
|    any more.
|  - POST /v1/stock/cash-out likewise went from "implemented with no
|    route" to routed — CashOutModal below.
|
| "Add User" (header action) opens AddUserModal, the full admin create
| form. "Add Retailer" is deliberately NOT a header action any more — it
| used to open the same AddUserModal under a different title, an 11-field
| form for what the legacy screen treats as a two-field quick-add. It now
| lives on RetailerPickerPanel itself (see that file and AddRetailerModal's
| comment), positioned the same place the legacy screen puts it: directly
| above the Retailer dropdown, not in the page header.
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
| selectedUserId (UserPickerPanel, left column) scopes TransactionHistory-
| Panel (as head_id). CustomerContextPanel (Customer Touch/photo/comments/
| Deliver, right column, legacy screen's lower-middle block) is scoped to
| activeCustomerId instead — selectedUserId if one is picked, otherwise
| selectedRetailerId — since a retailer is just another user_details row
| and the panel works identically for either; see its own comment for why.
| Customer Deliver has no backing endpoint yet (order_details table
| doesn't exist); the comments textarea saves for real via
| PUT /user-details/{id}/update-cc — see CustomerContextPanel's comment.
|
| Stock Out/In ("NEW OUT"/"NEW IN") are StockOutPanel/StockInPanel, not
| modals — the legacy screen never opens a dialog for these: clicking a
| button appends a row to that action's own dense inline grid, and rows
| just sit there ("stored in session") until the ONE shared Submit button
| below fires every panel that has pending rows at once. Each panel still
| posts to its own distinct endpoint separately (POST /stock/out,
| /stock/in) — this is NOT merged into a single combined request (e.g.
| the Auto Entry endpoint), just triggered together. handleSubmitAll
| calls each panel's exposed submit() via Promise.allSettled and only
| refreshes Head Stocks/Transaction History once afterward if at least
| one actually succeeded. The shared OUT/TOTAL/IN bar sums each panel's
| exposed `totals` computed.
|
| given_by/given_to come from page context (the logged-in head +
| selectedUserId) rather than per-row pickers on any of the panels —
| see StockOutPanel's comment for the full contract each one implements
| (addRow/submit/clear/hasRows/totals).
|
| NumericWastageOutPanel rounds out the OUT column the same way, still
| part of entryPanels/handleSubmitAll. Numeric Wastage In is a modal (see
| below) — only Out stays inline/queued; not mentioned = not changed.
|
| Item Change, Item Conversion, GMS Out, GMS In, and Numeric Wastage In
| ARE modals (ItemChangeModal/ItemConversionModal/GmsOutModal/
| GmsInModal/NumericWastageInModal), opened via activeModal same as Add
| User/Add Retailer — NOT part of entryPanels/the inline grid/shared
| Submit. Each is a standalone, deliberate action with its own Submit
| button and its own immediate POST, unlike the quick-entry-queue actions
| above (this was an explicit requirement, not a default — the legacy
| screen's own "Create new Gms Entry"/"Add Numeric Wastages" etc. dialogs
| are modals). Scoped to selectedUserId (+ headId for GMS/Numeric
| Wastage's given_to/given_by) via props, same as their inline-grid
| counterparts were, just presented as a dialog instead of a
| page-embedded table — deliberately NOT an independent user picker per
| modal like the legacy screenshots show, since the page already has one
| source of truth for "who" (UserPickerPanel). ItemChangeModal still
| carries forward the stock_in_id backend gap (see its own comment) —
| not fixed here, just not silently dropped either. GmsInModal's comment
| notes a "Customer Touch" field seen in the legacy dialog that isn't
| part of GmsInRequest's validated payload — left out, not invented.
| NumericWastageInModal similarly drops the legacy screenshot's disabled
| "Waste" preset dropdown — no API backs it, carried forward from the
| panel it replaces.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()
const toastStore = useToastStore()

const items = ref<Item[]>([])
const users = ref<UserDetailListItem[]>([])
const banks = ref<BankDetail[]>([])
const isLoading = ref(false)
const loadError = ref('')
const selectedUserId = ref<number | null>(null)

// Retailer is the alternative to picking a real user — RetailerPickerPanel
// only shows while selectedUserId is null. But UserPickerPanel is always
// visible, so a user can be picked at any time regardless of a prior
// retailer selection; without this, a stale retailer id would keep riding
// along into a StockOutPanel submit for a completely unrelated user.
const selectedRetailerId = ref<number | null>(null)
watch(selectedUserId, (id) => {
  if (id !== null) selectedRetailerId.value = null
})

// Whichever party is actually in play — a real user, or a retailer picked
// instead. Feeds two different things that both treat a retailer as just
// another user_details row: StockOutRequest's required given_to (retailer_id
// is a separate, optional flag on top of it, not a substitute), and
// CustomerContextPanel's photo/comments/touch/deliver, which work
// identically for either.
const activeCustomerId = computed(() => selectedUserId.value ?? selectedRetailerId.value)

/*
|--------------------------------------------------------------------------
| What shows depends on whether a user is picked, and what role they are
|--------------------------------------------------------------------------
| Taken from the legacy screens (five reference screenshots, 2026-10-11),
| which gate this screen in two separate ways. Keeping them separate here
| because they genuinely are separate — conflating them is the easy mistake:
|
|  1. A USER IS PICKED (any role) unlocks the second half of each action
|     row. With nothing picked the legacy screen shows five OUT actions and
|     three IN; picking anyone — customer, employee, invest alike — reveals
|     the rest. So this is NOT role-driven, despite looking like it.
|
|  2. THE ROLE drives the right-hand panel only: who gets a deliver button
|     and which one.
|
| Matched on the role NAME, not its id. `type` is the role string the
| backend already sends on each user row (UserDetailController:62), and
| names survive the id drift between environments that has bitten this
| project repeatedly. It also means INVEST starts working the day the
| backend adds it, with no change here — see the ask in BACKEND_TODO.md.
|--------------------------------------------------------------------------
*/

const selectedUser = computed(() =>
  activeCustomerId.value === null
    ? null
    : (users.value.find((u) => u.id === activeCustomerId.value) ?? null),
)

/** Role name, upper-cased. Null when nothing is picked. */
const selectedUserRole = computed(() => selectedUser.value?.type?.trim().toUpperCase() ?? null)

/** Gate 1: the extra actions the legacy screen reveals once anyone is picked. */
const hasUserSelected = computed(() => activeCustomerId.value !== null)

// Gate 2 lives in CustomerContextPanel, which is the only thing it affects —
// `selectedUserRole` is handed to it as a prop. Every role that is not
// CUSTOMER or EMPLOYEE gets the INVEST treatment there: comments only, no
// deliver action. That is what the legacy INVEST screen shows, and a safe
// default for HEAD/PURCHASE/SALARY, none of which the screenshots covered.

/*
|--------------------------------------------------------------------------
| "Select a user" surfaced as a field error, not a toast (2026-10 request)
|--------------------------------------------------------------------------
| Every entry panel/modal below used to block the operator BEFORE they
| could type anything — addRow() (or, for the modals, the open button
| itself in effect, since their own submit ran the same check) refused to
| proceed and toast.show()'d "select a user first." That meant filling in
| grams/touch/item/etc. was only possible after picking a user, which is
| backwards from how an operator actually works: pick the item and amount
| while deciding who it's for, not the other way round.
|
| Now every panel/modal lets you fill in everything regardless, and the
| check happens once, at submit — each one's submit()/handleSubmit() still
| refuses to POST without its required id, but instead of a toast (which
| scrolls away and doesn't point at anything) it emits `missingUser`, which
| sets this shared message and shows it as a red border + message directly
| on UserPickerPanel's dropdown (`:error` — the same mechanism BaseSelect
| already uses for every other field-level error on this page, just aimed
| at a dropdown that lives outside the panel raising it instead of a field
| inside it).
|
| One message for every panel rather than one ref each: they all fail for
| the same reason (no user picked — StockOutPanel's retailer alternative is
| the only variation, handled by clearing on retailer selection too, right
| below), so a shared string is simpler than eight near-identical refs and
| avoids the message flickering between slightly different wordings
| depending on which panel happened to fail last.
|
| Cleared the instant either a user or a retailer is actually chosen — not
| on next submit — so fixing the problem visibly fixes the field, matching
| how BaseSelect's own field errors behave elsewhere (CashOutModal,
| AddRetailerModal, etc. all clear on the next valid input, not on retry).
|--------------------------------------------------------------------------
*/
const userSelectionError = ref('')
watch([selectedUserId, selectedRetailerId], ([userId, retailerId]) => {
  if (userId !== null || retailerId !== null) userSelectionError.value = ''
})
function flagMissingUserSelection() {
  userSelectionError.value = 'Select a user before submitting.'
}
const isSubmittingAll = ref(false)

const headStockPanelRef = ref<InstanceType<typeof HeadStockSummaryPanel> | null>(null)
const txnHistoryPanelRef = ref<InstanceType<typeof TransactionHistoryPanel> | null>(null)
const stockOutPanelRef = ref<InstanceType<typeof StockOutPanel> | null>(null)
const stockInPanelRef = ref<InstanceType<typeof StockInPanel> | null>(null)
const numericWastageOutPanelRef = ref<InstanceType<typeof NumericWastageOutPanel> | null>(null)

const entryPanels = computed(() =>
  [
    stockOutPanelRef.value,
    stockInPanelRef.value,
    numericWastageOutPanelRef.value,
  ].filter((p): p is NonNullable<typeof p> => p !== null),
)

const zeroTotals = { grams: 0, purity: 0, wastage: 0 }
function sumTotals(...parts: Array<{ grams: number; purity: number; wastage: number }>) {
  return parts.reduce(
    (acc, t) => ({ grams: acc.grams + t.grams, purity: acc.purity + t.purity, wastage: acc.wastage + t.wastage }),
    { grams: 0, purity: 0, wastage: 0 },
  )
}
const outTotals = computed(() =>
  sumTotals(
    stockOutPanelRef.value?.totals ?? zeroTotals,
    numericWastageOutPanelRef.value?.totals ?? zeroTotals,
  ),
)
const inTotals = computed(() => sumTotals(stockInPanelRef.value?.totals ?? zeroTotals))
const grandTotals = computed(() => sumTotals(outTotals.value, inTotals.value))
function formatTotal(value: number) {
  return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0'
}

/*
| The balance bar's beam: pending OUT purity vs pending IN purity, drawn
| out from a centre tick — the heavier side reaches the end of its half.
| Presentation only: it reads the same outTotals/inTotals the figures
| beside it print, and feeds nothing back.
*/
const beam = computed(() => {
  const out = Math.abs(outTotals.value.purity)
  const inn = Math.abs(inTotals.value.purity)
  const max = Math.max(out, inn)
  if (!Number.isFinite(max) || max === 0) return { out: 0, in: 0 }
  return { out: (out / max) * 50, in: (inn / max) * 50 }
})

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    // Banks are only needed by the Cash In modal (CashTxnEntryModal takes a
    // bank list for the non-cash payment methods). Fetched with the rest
    // rather than on open so the modal never shows an empty bank dropdown
    // while a request is in flight — it is a small, rarely-changing table.
    const [itemsData, usersData, banksData] = await Promise.all([
      itemsApi.list(),
      userDetailsApi.list(undefined, 'stock'),
      bankDetailsApi.list(),
    ])
    items.value = itemsData
    users.value = usersData
    banks.value = banksData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load items/users.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadData)

// Dropdown reload buttons: each re-fetches one master with the same call
// loadData() makes, leaving isLoading/loadError and every selection alone.
async function reloadItems() {
  items.value = await itemsApi.list()
}
async function reloadUsers() {
  users.value = await userDetailsApi.list(undefined, 'stock')
}
async function reloadBanks() {
  banks.value = await bankDetailsApi.list()
}

type ActiveModal =
  | 'add-user'
  | 'item-change'
  | 'item-conversion'
  | 'gms-out'
  | 'gms-in'
  | 'numeric-wastage-in'
  | 'cash-out'
  | 'cash-in'
  | null

const activeModal = ref<ActiveModal>(null)

// The legacy screen's top-left "Clear" button — a full page reload, not
// handleClearAll's targeted row-clear below. Deliberately a hard reload
// rather than resetting refs one by one: the legacy button's job was
// "give me a clean slate," and a reload is the one way to guarantee that
// covers every picker/panel on this page, including ones a future panel
// might add, without this handler having to know about all of them.
function handleClearPage() {
  window.location.reload()
}

function openModal(modal: ActiveModal) {
  activeModal.value = modal
}
function closeModal() {
  activeModal.value = null
}
// Add User / Add Retailer create a user_details row that the "Given
// by/to"/"Retailer" pickers in the panels below need to see immediately,
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
function handleAddNumericWastageOutRow() {
  numericWastageOutPanelRef.value?.addRow()
}
function handleStockChanged() {
  headStockPanelRef.value?.refresh()
  txnHistoryPanelRef.value?.refresh()
}
// Item Change / Item Conversion / GMS Out submit standalone (see the
// header comment) — close the modal and refresh the same way a queued
// panel submit does once it actually saves.
function handleModalSaved() {
  activeModal.value = null
  handleStockChanged()
}

// Cash Out writes a cash_txn_details row, not stock, so the stock panels
// have nothing new to show — only the cash figure in the summary moves.
function handleCashOutSaved() {
  activeModal.value = null
  headStockPanelRef.value?.refresh()
}

// Not the same "Clear" as the page-header button above (handleClearPage) —
// this one only empties the pending rows in the entry panels (Stock Out/In/
// GMS/Numeric Wastage), leaving pickers and loaded data alone. Two buttons,
// two different scopes, matching the legacy screen's own split between a
// page-level Clear and this row-level one.
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
      </template>
    </PageHeader>

    <div
      v-if="loadError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </div>

    <!--
      Rows 1–2 (Clear/ViewNotesPanel, HeadStockSummaryPanel/TransactionHistory
      Panel) are direct children of this grid, not nested in per-column divs —
      that's the actual alignment mechanism. Two independent `space-y-6`
      columns only guarantee the COLUMNS start at the same height; nothing
      then keeps a later row aligned if the elements ahead of it in each
      column happen to differ in height, which is exactly what matching
      Tailwind padding by eye was trying (and failing) to paper over. Grid
      row tracks don't have that problem: a row's height is the max of
      whatever sits in it, and every column's NEXT row starts after that
      same shared boundary — Clear and ViewNotesPanel can be any height at
      all and HeadStockSummaryPanel/TransactionHistoryPanel still start
      level, by construction rather than coincidence.

      `items-start` so Clear and ViewNotesPanel keep their own natural
      height (top-aligned in the row) instead of stretching to match each
      other — stretching isn't needed for the guarantee above and would
      make Clear's box oddly tall for no visual reason.

      Below row 2, UserPickerPanel vs. RetailerPickerPanel/CustomerContext
      Panel go back to ordinary per-column `space-y-6` wrappers on purpose:
      the right side can render one or two panels there depending on
      whether a retailer is picked, the left always renders exactly one —
      row-for-row grid alignment does not apply once the two columns can
      hold different numbers of items.
    -->
    <!--
      grid-cols-[minmax(0,1fr)] below lg: without an explicit track the
      single implicit column grew to the history table's full width, and
      every panel ran off the right edge of a phone screen.
    -->
    <div
      class="mb-6 grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-[minmax(0,340px)_minmax(0,1fr)]"
    >
      <!--
        Named "Clear page", not "Clear": the balance bar below has its own
        Clear that only empties queued rows, and the two used to share a label.
      -->
      <BaseButton
        variant="secondary"
        :icon="RotateCcw"
        class="justify-self-start"
        title="Reload the page and reset every picker and panel"
        @click="handleClearPage"
      >
        Clear page
      </BaseButton>
      <ViewNotesPanel :users="users" :reload-users="reloadUsers" />

      <HeadStockSummaryPanel ref="headStockPanelRef" />
      <TransactionHistoryPanel
        ref="txnHistoryPanelRef"
        :items="items"
        :reload-items="reloadItems"
        :employee-id="selectedUserId"
        :users="users"
      />

      <div class="space-y-6">
        <UserPickerPanel v-model="selectedUserId" :error="userSelectionError" />
      </div>
      <div class="space-y-6">
        <RetailerPickerPanel
          v-if="!selectedUserId"
          v-model="selectedRetailerId"
          :users="users"
          :reload-users="reloadUsers"
          @users-changed="loadData"
        />
        <CustomerContextPanel
          v-if="activeCustomerId !== null"
          :active-id="activeCustomerId"
          :role="selectedUserRole"
        />
      </div>
    </div>

    <div
      v-if="isLoading"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading items and users…
    </div>

    <!--
      Colour follows direction, not action: every OUT action is oxblood,
      every IN action verdigris (main.css). The filled button is each
      side's main action; a "+" marks the actions that queue a row on this
      page, as opposed to the ones that open their own dialog.
    -->
    <div v-else class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-2">
      <section class="space-y-3" aria-labelledby="stock-out-heading">
        <h2 id="stock-out-heading"><DirectionTag direction="out" /></h2>
        <div class="flex flex-wrap gap-2">
          <BaseButton variant="outflow" :icon="Plus" @click="handleAddStockOutRow">Stock Out</BaseButton>
          <BaseButton variant="outflow-soft" @click="openModal('item-change')">Item Change</BaseButton>
          <BaseButton variant="outflow-soft" @click="openModal('item-conversion')">Item Conversion</BaseButton>
          <BaseButton variant="outflow-soft" @click="openModal('gms-out')">GMS Out</BaseButton>
          <BaseButton variant="outflow-soft" :icon="Plus" @click="handleAddNumericWastageOutRow">
            Numeric Wastage Out
          </BaseButton>
          <!--
            Everything from here on appears only once a user is picked, per
            the legacy screens. Reply Out has no endpoint on our backend at
            all, so it renders disabled rather than being left out — the row
            then matches the legacy layout, and the gap is visible instead of
            silently missing. Tracked in BACKEND_TODO.md.
          -->
          <template v-if="hasUserSelected">
            <BaseButton
              variant="outflow-soft"
              disabled
              title="Reply Out has no backend endpoint yet — flagged to the backend team."
            >
              Reply Out
            </BaseButton>
            <BaseButton variant="outflow-soft" @click="openModal('cash-out')">Cash Out</BaseButton>
          </template>
        </div>
        <p
          v-if="!stockOutPanelRef?.hasRows() && !numericWastageOutPanelRef?.hasRows()"
          class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-xs text-slate-500"
        >
          Rows from Stock Out and Numeric Wastage Out queue here until you submit.
        </p>
        <StockOutPanel
          ref="stockOutPanelRef"
          :items="items"
          :reload-items="reloadItems"
          :head-id="auth.user?.user_id ?? null"
          :given-to="activeCustomerId"
          :retailer-id="selectedRetailerId"
          @saved="handleStockChanged"
          @missing-user="flagMissingUserSelection"
        />
        <NumericWastageOutPanel
          ref="numericWastageOutPanelRef"
          :items="items"
          :reload-items="reloadItems"
          :head-id="auth.user?.user_id ?? null"
          :given-to="selectedUserId"
          @saved="handleStockChanged"
          @missing-user="flagMissingUserSelection"
        />
      </section>

      <section class="space-y-3" aria-labelledby="stock-in-heading">
        <h2 id="stock-in-heading"><DirectionTag direction="in" /></h2>
        <div class="flex flex-wrap gap-2">
          <BaseButton variant="inflow" :icon="Plus" @click="handleAddStockInRow">Stock In</BaseButton>
          <BaseButton variant="inflow-soft" @click="openModal('gms-in')">GMS In</BaseButton>
          <BaseButton variant="inflow-soft" @click="openModal('numeric-wastage-in')">Numeric Wastage In</BaseButton>
          <!-- Mirrors the Out row: revealed by a user selection. -->
          <template v-if="hasUserSelected">
            <BaseButton
              variant="inflow-soft"
              disabled
              title="Reply In has no backend endpoint yet — flagged to the backend team."
            >
              Reply In
            </BaseButton>
            <BaseButton variant="inflow-soft" @click="openModal('cash-in')">Cash In</BaseButton>
          </template>
        </div>
        <p
          v-if="!stockInPanelRef?.hasRows()"
          class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-xs text-slate-500"
        >
          Rows from Stock In queue here until you submit.
        </p>
        <StockInPanel
          ref="stockInPanelRef"
          :items="items"
          :reload-items="reloadItems"
          :head-id="auth.user?.user_id ?? null"
          :given-by="selectedUserId"
          @saved="handleStockChanged"
          @missing-user="flagMissingUserSelection"
        />
      </section>
    </div>

    <!--
      Balance bar. Same figures, same Clear/Submit handlers as before — what
      changed is placement: from md up it pins to the bottom of the scroll
      area while rows are queued, so the totals stay readable and Submit
      stays reachable however long the grids get. Out | Total | In keeps the
      page's left-out/right-in order; the beam on top compares pending
      purity either side of the centre (see `beam`).

      From md up the row is a 1fr | figures | 1fr grid so the figures sit
      centred in the bar with the buttons in the right column; below md it
      wraps and centres instead.

      -mx-6/-mb-6 cancel AppShell's <main> p-6 so the bar runs edge to edge
      and sits flush on the bottom. -bottom-6 likewise: sticky offsets are
      measured inside the scroller's padding, so bottom-0 would float the
      bar 24px up with rows showing through underneath.
    -->
    <div
      v-if="entryPanels.some((p) => p.hasRows())"
      class="z-20 -mx-6 mt-6 -mb-6 border-t border-slate-200 bg-white px-6 py-3 shadow-[0_-2px_6px_-2px_rgb(15_23_42/0.08)] md:sticky md:-bottom-6"
    >
      <div
        class="flex flex-wrap items-center justify-center gap-x-10 gap-y-3 md:grid md:grid-cols-[1fr_minmax(0,36rem)_1fr] md:gap-x-6"
      >
        <div
          class="grid min-w-0 flex-1 grid-cols-[auto_repeat(3,minmax(4.5rem,1fr))] items-baseline gap-x-6 gap-y-0.5 sm:max-w-xl md:col-start-2"
        >
          <div
            class="relative col-span-3 col-start-2 mb-2 h-[3px] rounded-full bg-slate-100"
            role="img"
            :aria-label="`Pending purity — out ${formatTotal(outTotals.purity)}, in ${formatTotal(inTotals.purity)}`"
          >
            <span
              class="absolute inset-y-0 right-1/2 rounded-l-full bg-outflow-600 motion-safe:transition-[width] motion-safe:duration-300"
              :style="{ width: `${beam.out}%` }"
            />
            <span
              class="absolute inset-y-0 left-1/2 rounded-r-full bg-inflow-600 motion-safe:transition-[width] motion-safe:duration-300"
              :style="{ width: `${beam.in}%` }"
            />
            <span class="absolute -top-1 -bottom-1 left-1/2 w-px -translate-x-1/2 bg-ink-900" />
          </div>

          <span></span>
          <DirectionTag direction="out" class="justify-end" />
          <span
            class="text-right font-figure text-xs font-semibold tracking-[0.08em] text-slate-500 uppercase"
          >
            Total
          </span>
          <DirectionTag direction="in" class="justify-end" />

          <span class="text-xs text-slate-500">Grams</span>
          <span class="text-right font-figure text-sm tabular-nums text-slate-900">{{ formatTotal(outTotals.grams) }}</span>
          <span class="text-right font-figure text-sm font-semibold tabular-nums text-ink-900">{{ formatTotal(grandTotals.grams) }}</span>
          <span class="text-right font-figure text-sm tabular-nums text-slate-900">{{ formatTotal(inTotals.grams) }}</span>

          <span class="text-xs text-slate-500">Purity</span>
          <span class="text-right font-figure text-sm tabular-nums text-gold-700">{{ formatTotal(outTotals.purity) }}</span>
          <span class="text-right font-figure text-sm font-semibold tabular-nums text-gold-700">{{ formatTotal(grandTotals.purity) }}</span>
          <span class="text-right font-figure text-sm tabular-nums text-gold-700">{{ formatTotal(inTotals.purity) }}</span>

          <span class="text-xs text-slate-500">Wastage</span>
          <span class="text-right font-figure text-sm tabular-nums text-slate-600">{{ formatTotal(outTotals.wastage) }}</span>
          <span class="text-right font-figure text-sm font-semibold tabular-nums text-slate-700">{{ formatTotal(grandTotals.wastage) }}</span>
          <span class="text-right font-figure text-sm tabular-nums text-slate-600">{{ formatTotal(inTotals.wastage) }}</span>
        </div>

        <div class="flex gap-2 md:col-start-3 md:justify-self-end">
          <BaseButton variant="secondary" :disabled="isSubmittingAll" @click="handleClearAll">
            Clear rows
          </BaseButton>
          <BaseButton variant="ink" :disabled="isSubmittingAll" @click="handleSubmitAll">
            {{ isSubmittingAll ? 'Submitting…' : 'Submit' }}
          </BaseButton>
        </div>
      </div>
    </div>

    <AddUserModal
      v-if="activeModal === 'add-user'"
      title="Add User"
      @close="closeModal"
      @saved="handleUserSaved"
    />
    <ItemChangeModal
      v-if="activeModal === 'item-change'"
      :items="items"
      :reload-items="reloadItems"
      :user-id="selectedUserId"
      @close="closeModal"
      @saved="handleModalSaved"
      @missing-user="flagMissingUserSelection"
    />
    <ItemConversionModal
      v-if="activeModal === 'item-conversion'"
      :items="items"
      :reload-items="reloadItems"
      :user-id="selectedUserId"
      @close="closeModal"
      @saved="handleModalSaved"
      @missing-user="flagMissingUserSelection"
    />
    <GmsOutModal
      v-if="activeModal === 'gms-out'"
      :items="items"
      :reload-items="reloadItems"
      :head-id="auth.user?.user_id ?? null"
      :given-to="selectedUserId"
      @close="closeModal"
      @saved="handleModalSaved"
      @missing-user="flagMissingUserSelection"
    />
    <GmsInModal
      v-if="activeModal === 'gms-in'"
      :items="items"
      :reload-items="reloadItems"
      :head-id="auth.user?.user_id ?? null"
      :given-by="selectedUserId"
      @close="closeModal"
      @saved="handleModalSaved"
      @missing-user="flagMissingUserSelection"
    />
    <NumericWastageInModal
      v-if="activeModal === 'numeric-wastage-in'"
      :items="items"
      :reload-items="reloadItems"
      :head-id="auth.user?.user_id ?? null"
      :given-by="selectedUserId"
      @close="closeModal"
      @saved="handleModalSaved"
      @missing-user="flagMissingUserSelection"
    />
    <CashOutModal
      v-if="activeModal === 'cash-out'"
      :users="users"
      :reload-users="reloadUsers"
      @close="closeModal"
      @saved="handleCashOutSaved"
    />
    <!--
      Cash In reuses Cash Management's entry modal. Only rendered with a user
      actually picked — the button that opens it is itself behind
      hasUserSelected, and this modal needs a concrete counterparty, not a
      nullable one.
    -->
    <CashTxnEntryModal
      v-if="activeModal === 'cash-in' && selectedUser && auth.user"
      :head-id="auth.user.user_id"
      :head-name="auth.user.name"
      :user-id="selectedUser.id"
      :user-name="selectedUser.full_name || selectedUser.name"
      direction="in"
      :banks="banks"
      :reload-banks="reloadBanks"
      @close="closeModal"
      @saved="handleCashOutSaved"
    />
  </div>
</template>
