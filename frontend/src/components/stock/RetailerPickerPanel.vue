<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Plus } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AddRetailerModal from '@/components/stock/AddRetailerModal.vue'
import { useAuthStore } from '@/stores/auth'
import type { UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Retailer picker — Stock Management screen, Users-or-Retailer alternative
|--------------------------------------------------------------------------
| Sits next to UserPickerPanel as the OTHER way to pick who a transaction
| involves: "+Add Retailer", a Retailer dropdown, and a Phone No field that
| doubles as a lookup, mirroring UserPickerPanel's own Phone Number Search.
|
| Visible only while no user is picked in UserPickerPanel, and hides the
| instant one is — this was previously a permanent fixture inside
| CustomerContextPanel (gated behind is_need_to_retailer_shown and shown
| only AFTER a user was picked, the opposite of the legacy screen's own
| behaviour), and disconnected from the actual transaction: StockOutPanel
| hardcoded retailer_id: null regardless of what was selected there. Both
| are fixed here — StockManagementView now renders this panel exactly when
| UserPickerPanel is hidden, and passes the selection through to
| StockOutPanel for real.
|
| A retailer is just a regular user_details row (see AddRetailerModal's
| comment) — this list is the same GET /user-details?module=stock response
| UserPickerPanel already loads, so no separate fetch here. The signed-in
| operator is excluded for the same reason UserPickerPanel excludes them:
| picking yourself as the counterparty isn't a transaction the panels below
| can produce.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ users: UserDetailListItem[] }>()
const emit = defineEmits<{ usersChanged: [] }>()

const auth = useAuthStore()
const selectedRetailerId = defineModel<number | null>({ default: null })

const selectableRetailers = computed(() =>
  props.users.filter((u) => u.id !== auth.user?.user_id),
)
const retailerOptions = computed(() =>
  selectableRetailers.value.map((u) => ({ value: u.id, label: u.full_name })),
)

const phoneSearch = ref('')

watch(selectedRetailerId, (id) => {
  const retailer = selectableRetailers.value.find((u) => u.id === id)
  phoneSearch.value = retailer?.phone_number ?? ''
})

function searchByPhone() {
  const query = phoneSearch.value.trim()
  if (!query) return
  const match = selectableRetailers.value.find((u) => u.phone_number === query)
  if (match) selectedRetailerId.value = match.id
}

const showAddModal = ref(false)

function handleRetailerSaved(userId: number) {
  showAddModal.value = false
  // The new row only exists in the parent's `users` list once it reloads —
  // select it optimistically now so Save doesn't feel like it did nothing,
  // then correct to the refreshed list's row (same id) once it arrives.
  selectedRetailerId.value = userId
  emit('usersChanged')
}
</script>

<template>
  <BaseCard :padded="false">
    <div class="space-y-3 p-4">
      <BaseButton variant="secondary" :icon="Plus" @click="showAddModal = true">
        Add Retailer
      </BaseButton>
      <BaseSelect
        v-model="selectedRetailerId"
        label="Retailer"
        placeholder="Select Retailer…"
        :options="retailerOptions"
      />
      <BaseInput
        v-model="phoneSearch"
        label="Phone No"
        placeholder="Search by phone…"
        @keydown.enter="searchByPhone"
        @blur="searchByPhone"
      />
    </div>

    <AddRetailerModal
      v-if="showAddModal"
      @close="showAddModal = false"
      @saved="handleRetailerSaved"
    />
  </BaseCard>
</template>
