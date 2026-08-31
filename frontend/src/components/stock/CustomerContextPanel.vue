<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AddUserModal from '@/components/user/AddUserModal.vue'
import CustomerDeliveryModal from '@/components/stock/CustomerDeliveryModal.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { customerTouchApi } from '@/lib/customerTouchApi'
import { ApiError } from '@/lib/api'
import type { CustomerTouch, UserDetail, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| Customer context — extra fields that appear once a user is picked in
| UserPickerPanel, reproducing the legacy screen's Customer Touch/photo/
| comments/Retailer/Deliver block.
|--------------------------------------------------------------------------
| user_details has ~30 "is_..._shown" boolean columns (is_customer_touch_
| need_shown, is_customer_cmts_need_to_shown, is_need_to_retailer_shown,
| etc. — see the migration) that gate which of these sections a given user
| sees, matching what you saw differ between the SAKTHI CHAINS and
| RETAILER screenshots. GET /user-details/{id} already returns them, so
| they're read here — but UserDetailController::store()/update() only
| validate/persist a different, unrelated subset of booleans (is_active,
| is_billable, is_gold_cal_enabled, etc.). None of the "_shown" display
| flags can be written through the API yet, so there's currently no way
| to build a working settings page to edit them — see chat for the
| backend ask this needs.
|
| Retailer picker: same "just a regular user" pattern StockOutPanel's
| retailer_id field already uses (see AddUserModal's comment) — reuses
| the same users list, not a separate entity.
|
| Customer Touch: GET /customer-touch (already-existing customerTouchApi,
| same picklist used by CustomerTouchView admin screen) — a standalone
| dropdown, not tied to a saved value on the user record yet.
|
| Photo + Comments: pulled from GET /user-details/{id} (customer_commants,
| profile_image_url are only on the show() response, not the list one —
| see UserDetail's comment). The comments textarea is left editable to
| match the legacy look, but there's no visible save action for it alone
| on the reference screen, so edits here are NOT persisted. There's no
| "_shown" flag for the photo specifically, so it's always rendered.
|
| Customer Deliver opens CustomerDeliveryModal (delivery schedule/
| tracking per customer) — confirmed there's genuinely no backend support
| for it at all (not just the "order_details doesn't exist" comment this
| used to say — see that modal's own comment for the full check: a stub
| OrderDetail model with no migration/controller/route behind it). The
| modal shows an empty state with the backend gap flagged clearly rather
| than a toast, so it's actually usable UI once the API exists.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ userId: number; users: UserDetailListItem[] }>()
const emit = defineEmits<{ usersChanged: [] }>()

const detail = ref<UserDetail | null>(null)
const profileImageUrl = ref<string | null>(null)
const isLoading = ref(false)
const loadError = ref('')

const touchOptions = ref<CustomerTouch[]>([])
const selectedTouchId = ref<number | null>(null)
const comments = ref('')
const retailerId = ref<number | null>(null)
const retailerPhone = ref('')
const showAddRetailerModal = ref(false)
const showDeliveryModal = ref(false)

const showTouch = computed(() => detail.value?.is_customer_touch_need_shown ?? false)
const showComments = computed(() => detail.value?.is_customer_cmts_need_to_shown ?? false)
const showRetailer = computed(() => detail.value?.is_need_to_retailer_shown ?? false)

const retailerOptions = computed(() =>
  props.users
    .filter((u) => u.id !== props.userId)
    .map((u) => ({ value: u.id, label: u.full_name })),
)

async function loadDetail() {
  isLoading.value = true
  loadError.value = ''
  try {
    const result = await userDetailsApi.get(props.userId)
    detail.value = result.user
    profileImageUrl.value = result.profile_image_url
    comments.value = result.user.customer_commants ?? ''
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load customer details.'
  } finally {
    isLoading.value = false
  }
}

async function loadTouchOptions() {
  try {
    touchOptions.value = await customerTouchApi.list()
  } catch {
    // Non-critical — the picker just stays empty if this fails.
  }
}

watch(
  () => props.userId,
  () => {
    selectedTouchId.value = null
    retailerId.value = null
    retailerPhone.value = ''
    loadDetail()
  },
  { immediate: true },
)
onMounted(loadTouchOptions)

function handleRetailerAdded() {
  showAddRetailerModal.value = false
  emit('usersChanged')
}
</script>

<template>
  <BaseCard :padded="false">
    <div v-if="loadError" class="p-4 text-sm text-red-700">{{ loadError }}</div>

    <div v-else class="space-y-6 p-4">
      <div v-if="showRetailer" class="space-y-3 border-b border-slate-100 pb-6">
        <BaseButton variant="secondary" @click="showAddRetailerModal = true">
          + Add Retailer
        </BaseButton>
        <BaseSelect
          v-model="retailerId"
          label="Retailer"
          placeholder="Select Retailer…"
          :options="retailerOptions"
        />
        <BaseInput v-model="retailerPhone" label="Phone No" />
      </div>

      <div class="grid gap-6 sm:grid-cols-[1fr_auto_1fr]">
        <div class="space-y-3">
          <BaseSelect
            v-if="showTouch"
            v-model="selectedTouchId"
            label="Customer Touch"
            placeholder="Select Customer Touch…"
            :options="touchOptions.map((t) => ({ value: t.item_id, label: t.item_name }))"
          />
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isLoading"
            @click="showDeliveryModal = true"
          >
            Customer Deliver
          </button>
        </div>

        <div
          class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
        >
          <img
            v-if="profileImageUrl"
            :src="profileImageUrl"
            alt="Customer photo"
            class="h-full w-full object-cover"
          />
          <span v-else class="text-xs text-slate-400">No photo</span>
        </div>

        <BaseTextarea v-if="showComments" v-model="comments" label="Customer Comments" :rows="4" />
      </div>
    </div>

    <AddUserModal
      v-if="showAddRetailerModal"
      title="Add Retailer"
      @close="showAddRetailerModal = false"
      @saved="handleRetailerAdded"
    />
    <CustomerDeliveryModal
      v-if="showDeliveryModal"
      :customer-name="detail?.name ?? 'Customer'"
      @close="showDeliveryModal = false"
    />
  </BaseCard>
</template>
