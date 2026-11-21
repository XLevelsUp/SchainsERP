<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Check, X } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import type {
  CustomerTouch,
  Item,
  Role,
  UserDetail,
  UserDetailListItem,
  UserFormProfile,
  UserFormProfileCatalog,
  UserFormSection,
} from '@/types'
import { userDetailsApi } from '../../lib/resources'
import { FULL_USER_FORM_PROFILE, flagLabel } from '../../lib/userForm'
import { profileForRole } from '../../lib/userFormProfiles'

// Read-only view of a user (the list's eye button), laid out by the same
// role profile as the form: only the sections the role has.
const props = defineProps<{
  userId: number
  roles: Role[]
  items: Item[]
  heads: UserDetailListItem[]
  users: UserDetailListItem[]
  touches: CustomerTouch[]
  catalog: UserFormProfileCatalog | null
}>()

const emit = defineEmits<{ close: []; edit: [] }>()

const user = ref<UserDetail | null>(null)
const imageUrl = ref<string | null>(null)
const logoUrl = ref<string | null>(null)
const aadharFrontUrl = ref<string | null>(null)
const aadharBackUrl = ref<string | null>(null)
const isLoading = ref(true)
const loadError = ref('')

onMounted(async () => {
  try {
    const result = await userDetailsApi.get(props.userId)
    user.value = result.user
    imageUrl.value = result.profile_image_url
    logoUrl.value = result.image_url ?? null
    aadharFrontUrl.value = result.aadhar_image_url
    aadharBackUrl.value = result.aadhar_back_image_url ?? null
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the user.'
  } finally {
    isLoading.value = false
  }
})

const profile = computed<UserFormProfile | null>(() => {
  if (!user.value) return null
  if (!props.catalog) return FULL_USER_FORM_PROFILE
  return profileForRole(props.catalog, user.value.role_id)
})

const has = (section: UserFormSection) => profile.value?.sections.includes(section) ?? false

const roleName = computed(
  () => props.roles.find((r) => String(r.id) === String(user.value?.role_id))?.role ?? '—',
)

const nameOfUser = (id: number) =>
  props.users.find((u) => u.id === id)?.name ?? props.heads.find((u) => u.id === id)?.name ?? `#${id}`
const nameOfItem = (id: number) => props.items.find((i) => i.item_id === id)?.item_name ?? `#${id}`
const nameOfTouch = (id: number) => props.touches.find((t) => t.item_id === id)?.item_name ?? `#${id}`

const enabledFlags = computed(() =>
  (profile.value?.flags ?? []).filter((key) => user.value && Boolean(user.value[key])),
)

// "Salary person" shows with the salary when the role has that section.
const switchFlags = computed(() =>
  (profile.value?.flags ?? []).filter((k) => !(k === 'is_salary_person' && has('salary'))),
)

const grams = (value: number | string | null | undefined) => Number(value || 0).toFixed(3)
const money = (value: number | string | null | undefined) =>
  Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>

<template>
  <BaseModal :title="user?.name ?? 'User'" :badge="user ? roleName : undefined" max-width="max-w-3xl" @close="emit('close')">
    <p v-if="isLoading" class="py-6 text-center text-sm text-slate-500">Loading…</p>
    <p v-else-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </p>

    <template v-else-if="user">
      <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
        <div>
          <dt class="text-xs text-slate-500">User name</dt>
          <dd class="font-medium text-slate-900">{{ user.user_name }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Phone no</dt>
          <dd class="text-slate-900 tabular-nums">{{ user.phone_no }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Status</dt>
          <dd :class="user.is_active ? 'text-emerald-700' : 'text-slate-500'">
            {{ user.is_active ? 'Active' : 'Inactive' }}
          </dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Category</dt>
          <dd class="text-slate-900">{{ user.category_name }}</dd>
        </div>
        <div v-if="has('joining_date')">
          <dt class="text-xs text-slate-500">Joining date</dt>
          <dd class="text-slate-900">{{ user.joining_date ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Added</dt>
          <dd class="text-slate-900">{{ formatDateTime(user.added_at) }}</dd>
        </div>
      </dl>

      <div v-if="has('head_users')">
        <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Head users</h3>
        <p class="text-sm text-slate-900">
          {{ (user.head_user_mappings ?? []).map((m) => nameOfUser(m.employee_id)).join(', ') || 'None' }}
        </p>
      </div>

      <dl v-if="has('proof') || has('salary') || has('report_password')" class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
        <div v-if="has('proof')">
          <dt class="text-xs text-slate-500">Aadhar number</dt>
          <dd class="text-slate-900 tabular-nums">{{ user.aadhar_number || '—' }}</dd>
        </div>
        <div v-if="has('salary')">
          <dt class="text-xs text-slate-500">Salary person</dt>
          <dd class="text-slate-900">
            {{ user.is_salary_person ? `Yes · ₹${money(user.per_day_salary)} a day` : 'No' }}
          </dd>
        </div>
        <div v-if="has('report_password')">
          <dt class="text-xs text-slate-500">Report password</dt>
          <dd class="text-slate-900">{{ user.has_report_password ? 'Set' : 'Not set' }}</dd>
        </div>
      </dl>

      <div v-if="has('heads')">
        <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Head lists</h3>
        <p class="text-sm text-slate-900">
          {{
            (user.head_employee_mappings ?? []).map((m) => nameOfUser(m.head_id)).join(', ') || 'None'
          }}
        </p>
      </div>

      <div v-if="has('cash_heads')">
        <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Cash heads</h3>
        <p class="text-sm text-slate-900">
          {{
            (user.cash_head_employee_mappings ?? []).map((m) => nameOfUser(m.head_id)).join(', ') ||
            'None'
          }}
        </p>
      </div>

      <div v-if="has('customer_comments') && user.customer_commants">
        <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Customer comments</h3>
        <p class="text-sm whitespace-pre-line text-slate-900">{{ user.customer_commants }}</p>
      </div>

      <div v-if="has('items')">
        <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Items</h3>
        <p v-if="!(user.item_mappings ?? []).length" class="text-sm text-slate-500">None.</p>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
              <th class="py-1.5 font-medium">Item</th>
              <th class="py-1.5 font-medium">Primary</th>
              <th class="py-1.5 text-right font-medium">Grams (g)</th>
              <th class="py-1.5 text-right font-medium">Purity (g)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in user.item_mappings" :key="row.id" class="border-b border-slate-100">
              <td class="py-1.5 text-slate-900">{{ nameOfItem(row.item_id) }}</td>
              <td class="py-1.5">
                <Check v-if="row.is_primary" class="h-4 w-4 text-emerald-600" aria-label="Primary" />
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="py-1.5 text-right tabular-nums">{{ grams(row.item_grams_total) }}</td>
              <td class="py-1.5 text-right tabular-nums">{{ grams(row.item_purity_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <dl v-if="has('balances')" class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <dt class="text-xs text-slate-500">Grams grand total (g)</dt>
          <dd class="text-right text-sm font-semibold tabular-nums">{{ grams(user.grams_grand_total) }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <dt class="text-xs text-slate-500">Purity grand total (g)</dt>
          <dd class="text-right text-sm font-semibold tabular-nums">{{ grams(user.purity_grand_total) }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <dt class="text-xs text-slate-500">Cash balance (₹)</dt>
          <dd class="text-right text-sm font-semibold tabular-nums">{{ money(user.rak_cash_balance) }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <dt class="text-xs text-slate-500">RTGS balance (₹)</dt>
          <dd class="text-right text-sm font-semibold tabular-nums">{{ money(user.rak_rtgs_balance) }}</dd>
        </div>
      </dl>

      <div v-if="has('customer_touches')">
        <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Customer touches</h3>
        <p v-if="!(user.customer_touch_mappings ?? []).length" class="text-sm text-slate-500">None.</p>
        <div v-else class="flex flex-wrap gap-2">
          <span
            v-for="m in user.customer_touch_mappings"
            :key="m.id"
            class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium"
            :class="m.is_active ? 'bg-slate-100 text-slate-700' : 'bg-slate-50 text-slate-400 line-through'"
          >
            {{ nameOfTouch(m.customer_touch_id) }}
          </span>
        </div>
      </div>

      <div v-if="has('flags')">
        <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Permissions</h3>
        <ul class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
          <li v-for="key in switchFlags" :key="key" class="flex items-center gap-2">
            <Check v-if="enabledFlags.includes(key)" class="h-4 w-4 shrink-0 text-emerald-600" aria-label="On" />
            <X v-else class="h-4 w-4 shrink-0 text-slate-300" aria-label="Off" />
            <span :class="enabledFlags.includes(key) ? 'text-slate-900' : 'text-slate-500'">{{ flagLabel(key) }}</span>
          </li>
        </ul>
      </div>

      <p class="flex flex-wrap gap-4 text-sm">
        <a v-if="has('image') && logoUrl" :href="logoUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">Image</a>
        <a v-if="has('profile_image') && imageUrl" :href="imageUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">Profile picture</a>
        <a v-if="has('proof') && aadharFrontUrl" :href="aadharFrontUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">Aadhar front</a>
        <a v-if="has('proof') && aadharBackUrl" :href="aadharBackUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">Aadhar back</a>
      </p>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" @click="emit('close')">Close</BaseButton>
        <BaseButton @click="emit('edit')">Edit</BaseButton>
      </div>
    </template>
  </BaseModal>
</template>
