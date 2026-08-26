<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RefreshCw, UserPlus, Store, AlertTriangle } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { itemsApi } from '@/lib/itemsApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { ApiError } from '@/lib/api'
import StockOutModal from '@/components/stock-txn/StockOutModal.vue'
import StockInModal from '@/components/stock-txn/StockInModal.vue'
import ItemChangeModal from '@/components/stock-txn/ItemChangeModal.vue'
import ItemConversionModal from '@/components/stock-txn/ItemConversionModal.vue'
import GmsOutModal from '@/components/stock-txn/GmsOutModal.vue'
import GmsInModal from '@/components/stock-txn/GmsInModal.vue'
import NumericWastageOutModal from '@/components/stock-txn/NumericWastageOutModal.vue'
import NumericWastageInModal from '@/components/stock-txn/NumericWastageInModal.vue'
import AddUserModal from '@/components/user/AddUserModal.vue'
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
|  - Numeric Wastage In calls POST /stock/numeric-waste-in, which isn't
|    registered in routes/api.php (postNumericWasteIn exists on the
|    controller/service but is unreachable). Numeric Wastage Out's route
|    (POST /stock/numeric-waste) is registered and works.
|  - Item Change / Item Conversion need stock_in_id (an existing stock
|    lot); no endpoint lists a user's lots yet, so it's a plain numeric
|    input for now.
|
| "Add User" / "Add Retailer" (header actions) both open AddUserModal —
| there's no separate Retailer entity in the backend, just a user that
| later shows up in a "Retailer" picker (see AddUserModal.vue's comment).
|
| The legacy screen's item-wise stock summary panel (METAL/GOLD/FITEM/
| STONE totals with grams/%/purity, Cash, Active Orders) is NOT
| implemented — no backend endpoint returns that per-item balance rollup
| (the closest existing one, GET /stock-details/available-metals, is
| scoped to a single item literally named "Metal" and one user, not a
| general summary). Needs a new backend endpoint before it can be built.
|--------------------------------------------------------------------------
*/

const items = ref<Item[]>([])
const users = ref<UserDetailListItem[]>([])
const isLoading = ref(false)
const loadError = ref('')

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
  | 'stock-out'
  | 'stock-in'
  | 'item-change'
  | 'item-conversion'
  | 'gms-out'
  | 'gms-in'
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
      class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>
        <strong>Pending backend:</strong> Transaction history and the item-wise stock summary
        (shown on the legacy screen) aren't listed here — the general stock history endpoint is
        commented out on the backend, and no endpoint returns the per-item balance rollup yet.
        Entries submitted below still save correctly.
      </span>
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
      Loading…
    </div>

    <div v-else class="grid gap-6 sm:grid-cols-2">
      <div>
        <p class="mb-2 text-xs font-semibold tracking-wide text-red-700 uppercase">Out</p>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
            @click="openModal('stock-out')"
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
            @click="openModal('gms-out')"
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
      </div>

      <div>
        <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-700 uppercase">In</p>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
            @click="openModal('stock-in')"
          >
            Stock In
          </button>
          <button
            type="button"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-600"
            @click="openModal('gms-in')"
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
      </div>
    </div>

    <StockOutModal
      v-if="activeModal === 'stock-out'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
    <StockInModal
      v-if="activeModal === 'stock-in'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
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
    <GmsOutModal
      v-if="activeModal === 'gms-out'"
      :items="items"
      :users="users"
      @close="closeModal"
      @saved="handleSaved"
    />
    <GmsInModal
      v-if="activeModal === 'gms-in'"
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
