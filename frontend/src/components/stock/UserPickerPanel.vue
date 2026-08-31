<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
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
| Phone Number Search is a plain client-side lookup over the already-
| loaded list (no dedicated search-by-phone endpoint exists) — pressing
| Enter or blurring with an exact phone match selects that user.
|--------------------------------------------------------------------------
*/

const selectedUserId = defineModel<number | null>({ default: null })

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

function formatUserLabel(user: UserDetailListItem) {
  const purity = user.purity ?? '0.000'
  const date = formatShortDate(user.last_txn_date)
  return `${user.full_name}(${user.type ?? '—'}, ${user.phone_number}) ==> P = ${purity}, D = ${date} | Order Tot : 0  P : 0`
}

const userOptions = computed(() =>
  users.value.map((user) => ({ value: user.id, label: formatUserLabel(user) })),
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

watch(selectedUserId, (id) => {
  const user = users.value.find((u) => u.id === id)
  phoneSearch.value = user?.phone_number ?? ''
})

function searchByPhone() {
  const query = phoneSearch.value.trim()
  if (!query) return
  const match = users.value.find((u) => u.phone_number === query)
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
        :disabled="isLoading"
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
