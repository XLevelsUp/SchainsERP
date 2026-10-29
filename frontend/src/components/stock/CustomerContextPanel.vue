<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CustomerDeliveryModal from '@/components/stock/CustomerDeliveryModal.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { customerTouchApi } from '@/lib/customerTouchApi'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { CustomerTouch, UserDetail } from '@/types'

/*
|--------------------------------------------------------------------------
| Customer context — extra fields that appear once a PARTY is picked,
| reproducing the legacy screen's Customer Touch/photo/comments/Deliver
| block. "Party" is deliberately either a user (UserPickerPanel) or a
| retailer (RetailerPickerPanel) — the legacy screenshot this was built
| from shows the Retailer picker and an (empty) Customer Comments box
| visible at the same time, with no user chosen. That is not a coincidence:
| a retailer is just a regular user_details row (see AddRetailerModal's
| comment), so GET /user-details/{id} and PUT .../update-cc work on one
| exactly the same as on a real user. StockManagementView computes
| activeId as selectedUserId ?? selectedRetailerId and only renders this
| panel once one of them is non-null.
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
| Retailer PICKING (the "+Add Retailer"/dropdown/phone trio) used to live
| here too, gated behind is_need_to_retailer_shown, and disconnected from
| the real transaction besides — StockOutPanel hardcoded retailer_id: null
| no matter what was selected. That part moved out to
| RetailerPickerPanel.vue and is wired through to StockOutPanel for real.
| is_need_to_retailer_shown itself is untouched — still a real column,
| still editable on UsersView's permissions section — this only removes
| the one (broken) reader of it that lived here. What stayed, per the
| header note above: once a retailer is the active party, this panel still
| shows for it — same photo/comments/touch/deliver a real user gets.
|
| Customer Touch: GET /customer-touch (already-existing customerTouchApi,
| same picklist used by CustomerTouchView admin screen) — a standalone
| dropdown, not tied to a saved value on the user record yet.
|
| Photo + Comments: pulled from GET /user-details/{id} (customer_commants,
| profile_image_url are only on the show() response, not the list one —
| see UserDetail's comment). Comments now persist through the dedicated
| PUT /user-details/{id}/update-cc endpoint added in PR #32; before that
| the only way to write the field was update()'s full-record payload, so
| the box was editable but deliberately not saved. There's no "_shown"
| flag for the photo specifically, so it's always rendered.
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

/*
| `role` is the selected user's role NAME (UserDetailController sends it as
| `type` on each user row), upper-cased by the caller, or null when nothing
| is picked. It decides which deliver action this panel offers — the legacy
| screens give CUSTOMER and EMPLOYEE different ones and INVEST none at all.
|
| Passed in rather than re-derived here: StockManagementView already holds
| the users list and the selection, so looking it up again would mean a
| second source of truth for the same fact.
*/
const props = defineProps<{ activeId: number; role?: string | null }>()

const toast = useToastStore()

const detail = ref<UserDetail | null>(null)
const profileImageUrl = ref<string | null>(null)
const isLoading = ref(false)
const loadError = ref('')

const touchOptions = ref<CustomerTouch[]>([])
const selectedTouchId = ref<number | null>(null)
const comments = ref('')
// Last value known to be on the server, so Save can stay disabled until
// the operator has actually changed something.
const savedComments = ref('')
const isSavingComments = ref(false)
const commentsError = ref('')
const showDeliveryModal = ref(false)

const isCustomer = computed(() => props.role === 'CUSTOMER')
const isEmployee = computed(() => props.role === 'EMPLOYEE')

/*
| Customer Touch needs BOTH conditions, which the reference screenshots make
| plain: two different CUSTOMER accounts, one showing the dropdown and one
| not — so the per-user flag still governs — while the EMPLOYEE account
| never shows it at all, so the role governs too.
*/
const showTouch = computed(
  () => isCustomer.value && (detail.value?.is_customer_touch_need_shown ?? false),
)

// Comments stay flag-only: every role in the reference screenshots shows the
// box, including INVEST, which shows nothing else.
const showComments = computed(() => detail.value?.is_customer_cmts_need_to_shown ?? false)

async function loadDetail() {
  isLoading.value = true
  loadError.value = ''
  try {
    const result = await userDetailsApi.get(props.activeId)
    detail.value = result.user
    profileImageUrl.value = result.profile_image_url
    comments.value = result.user.customer_commants ?? ''
    savedComments.value = comments.value
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load customer details.'
  } finally {
    isLoading.value = false
  }
}

// Also the Customer Touch dropdown's reload button, which surfaces a failure.
async function reloadTouchOptions() {
  touchOptions.value = await customerTouchApi.list()
}
async function loadTouchOptions() {
  try {
    await reloadTouchOptions()
  } catch {
    // Non-critical — the picker just stays empty if this fails.
  }
}

// UpdateCcRequest: nullable|string|max:1500.
const COMMENTS_MAX = 1500

const commentsDirty = computed(() => comments.value !== savedComments.value)

async function saveComments() {
  commentsError.value = ''

  if (comments.value.length > COMMENTS_MAX) {
    commentsError.value = `Customer comments must be ${COMMENTS_MAX} characters or fewer.`
    return
  }
  if (isSavingComments.value) return

  isSavingComments.value = true
  try {
    // Send null for an emptied box so the column is cleared rather than set
    // to an empty string — matches how create/update build this field.
    const next = comments.value.trim().length > 0 ? comments.value : null
    const result = await userDetailsApi.updateCustomerComments(props.activeId, next)

    // Trust the echoed value over the local one.
    comments.value = result.customer_commants ?? ''
    savedComments.value = comments.value
    if (detail.value) detail.value.customer_commants = result.customer_commants

    toast.show('Customer comments saved.', 'success')
  } catch (err) {
    commentsError.value =
      err instanceof ApiError ? err.message : 'Failed to save customer comments.'
  } finally {
    isSavingComments.value = false
  }
}

watch(
  () => props.activeId,
  () => {
    selectedTouchId.value = null
    commentsError.value = ''
    loadDetail()
  },
  { immediate: true },
)
onMounted(loadTouchOptions)
</script>

<template>
  <BaseCard :padded="false">
    <div v-if="loadError" class="p-4 text-sm text-red-700">{{ loadError }}</div>

    <div v-else class="space-y-6 p-4">
      <div class="grid gap-6 sm:grid-cols-[1fr_auto_1fr]">
        <div class="space-y-3">
          <BaseSelect
            v-if="showTouch"
            v-model="selectedTouchId"
            label="Customer Touch"
            placeholder="Select Customer Touch…"
            :options="touchOptions.map((t) => ({ value: t.item_id, label: t.item_name }))"
            :reload="reloadTouchOptions"
          />
          <!--
            Which deliver action appears is role-driven, straight from the
            legacy screens: CUSTOMER gets Customer Deliver, EMPLOYEE gets
            Assign to Employee + Employee Deliver, and every other role
            (INVEST in the reference, plus HEAD/PURCHASE/SALARY here) gets
            none — the comments box below is all they have.

            The two employee actions have no backend endpoint of any kind,
            so they render disabled rather than being omitted: the panel then
            matches the legacy layout and the gap is visible. Tracked in
            BACKEND_TODO.md.
          -->
          <BaseButton
            v-if="isCustomer"
            variant="inflow-soft"
            :disabled="isLoading"
            @click="showDeliveryModal = true"
          >
            Customer Deliver
          </BaseButton>

          <template v-if="isEmployee">
            <BaseButton
              variant="inflow-soft"
              class="w-full"
              disabled
              title="Assign to Employee has no backend endpoint yet — flagged to the backend team."
            >
              Assign to Employee
            </BaseButton>
            <BaseButton
              variant="inflow-soft"
              class="w-full"
              disabled
              title="Employee Deliver has no backend endpoint yet — flagged to the backend team."
            >
              Employee Deliver
            </BaseButton>
          </template>
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

        <div v-if="showComments" class="space-y-2">
          <BaseTextarea
            v-model="comments"
            label="Customer Comments"
            :rows="4"
            :error="commentsError"
          />
          <div class="flex items-center justify-between gap-3">
            <span
              class="font-figure text-xs tabular-nums"
              :class="comments.length > COMMENTS_MAX ? 'text-red-600' : 'text-slate-400'"
            >
              {{ comments.length }} / {{ COMMENTS_MAX }}
            </span>
            <BaseButton
              variant="secondary"
              type="button"
              :disabled="!commentsDirty || isSavingComments || isLoading"
              @click="saveComments"
            >
              {{ isSavingComments ? 'Saving…' : 'Save comments' }}
            </BaseButton>
          </div>
        </div>
      </div>
    </div>

    <CustomerDeliveryModal
      v-if="showDeliveryModal"
      :customer-name="detail?.name ?? 'Customer'"
      @close="showDeliveryModal = false"
    />
  </BaseCard>
</template>
