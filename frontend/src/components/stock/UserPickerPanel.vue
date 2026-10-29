<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { categoryShowsPurity } from '@/lib/userCategory'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/lib/api'
import type { UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Users picker — GET /user-details?module=stock (API doc #17-20)
|--------------------------------------------------------------------------
| Legacy "Stock Details" screen's left-side Users dropdown: picks a HEAD,
| EMPLOYEE, or CUSTOMER to scope the Transaction History table by (see
| TransactionHistoryPanel's employeeId prop) and drive CustomerContextPanel.
| No `type` filter is sent — unlike the doc's per-type examples, this
| picker needs every user in one list, matching the legacy screen showing
| HEAD/EMPLOYEE/CUSTOMER rows together.
|
| The option label reproduces the legacy format exactly:
|   "{name}({type}, {phone}) ==> P = {purity}, D = {last_txn_date} |
|    Order Tot : 0  P : 0"
| "Order Tot"/"P" are always 0 — there's no backend field for them yet
| (order_details table doesn't exist, same known gap as HeadStockSummary-
| Panel's "Active Orders" row).
|
| The signed-in operator is filtered out of the list: they are the head
| this screen already acts as (StockManagementView passes their user_id
| as headId to every panel/modal), so picking themselves here would scope
| Transaction History / CustomerContextPanel to a self-transaction that
| the panels below cannot produce. The API has no "exclude me" filter, so
| this is a client-side filter over the same GET /user-details?module=stock
| response.
|
| Phone Number Search is a plain client-side lookup over the already-
| loaded list (no dedicated search-by-phone endpoint exists) — pressing
| Enter or blurring with an exact phone match selects that user.
|--------------------------------------------------------------------------
*/

const selectedUserId = defineModel<number | null>({ default: null })

// Set by StockManagementView when a submit was attempted with no user (and,
// for panels that accept one, no retailer) selected — see that file's
// comment for the full mechanism. Shown on the dropdown itself rather than
// a toast, per the 2026-10 request to let people fill in every other field
// first and only find out about this one at submit time.
const props = defineProps<{ error?: string }>()

const auth = useAuthStore()

const users = ref<UserDetailListItem[]>([])
const isLoading = ref(false)
const loadError = ref('')
const phoneSearch = ref('')

function formatShortDate(value?: string | null) {
  if (!value) return '—'
  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return '—'
  const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ]
  return `${String(date.getDate()).padStart(2, '0')}-${months[date.getMonth()]}`
}

// P follows the user's category: a GRAMS-only user reads 0 here.
function formatUserLabel(user: UserDetailListItem) {
  const purity = categoryShowsPurity(user.category_name) ? (user.purity ?? '0.000') : '0.000'
  const date = formatShortDate(user.last_txn_date)
  return `${user.full_name}(${user.type ?? '—'}, ${user.phone_number}) ==> P = ${purity}, D = ${date} | Order Tot : 0  P : 0`
}

const selectableUsers = computed(() =>
  users.value.filter((user) => user.id !== auth.user?.user_id),
)

const userOptions = computed(() =>
  selectableUsers.value.map((user) => ({ value: user.id, label: formatUserLabel(user) })),
)

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    users.value = await userDetailsApi.list(undefined, 'stock')
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load users.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)

// The dropdown's reload button: same fetch as load(), but without the
// disabled/loading state or panel error, so the open picker stays usable.
async function reloadUsers() {
  users.value = await userDetailsApi.list(undefined, 'stock')
}

watch(selectedUserId, (id) => {
  const user = selectableUsers.value.find((u) => u.id === id)
  phoneSearch.value = user?.phone_number ?? ''
})

function searchByPhone() {
  const query = phoneSearch.value.trim()
  if (!query) return
  const match = selectableUsers.value.find((u) => u.phone_number === query)
  if (match) selectedUserId.value = match.id
}

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="space-y-3 p-4">
      <BaseSelect
        v-model="selectedUserId"
        label="Users"
        placeholder="Choose User"
        :options="userOptions"
        :reload="reloadUsers"
        :disabled="isLoading"
        :error="props.error"
      />
      <p v-if="loadError" class="text-sm text-red-700">{{ loadError }}</p>

      <BaseInput
        v-model="phoneSearch"
        label="Phone Number Search"
        placeholder="Search by phone…"
        @keydown.enter="searchByPhone"
        @blur="searchByPhone"
      />
    </div>
  </BaseCard>
</template>
