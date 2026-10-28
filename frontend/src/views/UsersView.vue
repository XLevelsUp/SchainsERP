<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Search, Plus, Pencil, Trash2, X, RefreshCw, Trash, ChevronDown } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseMultiSelect from '@/components/ui/BaseMultiSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { rolesApi } from '@/lib/rolesApi'
import { userOptionLabel } from '@/lib/userLabel'
import { ApiError } from '@/lib/api'
import {
  emptyUserFeatureFlags,
  USER_FEATURE_FLAG_GROUPS,
  USER_FEATURE_FLAG_KEYS,
} from '@/lib/userFeatureFlags'
import type { UserFeatureFlagGroup } from '@/lib/userFeatureFlags'
import { useToastStore } from '@/stores/toast'
import type {
  DataTableColumn,
  UserDetailFormValues,
  UserDetailListItem,
  CategoryName,
  Item,
  Role,
} from '@/types'

// GET /user-details (list) returns a flattened summary row (PR #15) — no
// user_name, no address/signature/etc, so it's only good for the table and
// the head/cash-head pickers. Editing fetches the full record separately
// via GET /user-details/{id}, which still returns everything.
const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])
const roles = ref<Role[]>([])
const isLoading = ref(false)
const loadError = ref('')
const searchQuery = ref('')
const toastStore = useToastStore()

const columns: DataTableColumn<UserDetailListItem>[] = [
  { key: 'name', label: 'Name' },
  { key: 'phone_number', label: 'Phone' },
  { key: 'category_name', label: 'Category' },
  { key: 'is_active', label: 'Status' },
  { key: 'id', label: '' },
]

async function loadData() {
  isLoading.value = true
  loadError.value = ''
  try {
    const [usersData, itemsData] = await Promise.all([userDetailsApi.list(), itemsApi.list()])
    users.value = usersData
    items.value = itemsData
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load users.'
  } finally {
    isLoading.value = false
  }
}

// Fetched separately so a broken/missing roles endpoint can't take the
// whole user list down with it (Promise.all fails the batch on any single
// rejection) — it just leaves the Role dropdown empty.
async function loadRoles() {
  try {
    roles.value = await rolesApi.list()
  } catch {
    roles.value = []
  }
}

onMounted(loadData)
onMounted(loadRoles)

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return users.value
  return users.value.filter((u) => u.name.toLowerCase().includes(query))
})

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)

const categories: CategoryName[] = ['GRAMS', 'PURITY', 'BOTH']

function makeEmptyForm(): UserDetailFormValues {
  return {
    name: '',
    user_name: '',
    password: '',
    address: '',
    signature: '',
    code: '',
    phone_no: '',
    remarks: '',
    proff: '',
    role_id: '',
    system_id: '',
    mailing_name: '',
    customer_commants: '',
    category_name: 'GRAMS',
    is_active: true,
    is_delete: false,
    is_billable: false,
    ...emptyUserFeatureFlags(),
    item_mappings: [],
    head_mappings: [],
    cash_head_mappings: [],
    aadhar_image: null,
  }
}

const isFormOpen = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<UserDetailFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const isLoadingEditUser = ref(false)
// Saved proof image of the user being edited (show()'s aadhar_image_url),
// shown until a replacement is picked.
const currentProofUrl = ref<string | null>(null)

// Proof is one image; BaseFileInput's model is a File[] list.
const proofFiles = computed<File[]>({
  get: () => (form.aadhar_image ? [form.aadhar_image] : []),
  set: (files) => {
    form.aadhar_image = files[0] ?? null
  },
})

// ----- feature flag helpers (declared after `form`, which they all read) --

// Collapsed by default: the flags are an occasional administrative concern,
// while name/role/mappings are the everyday path through this form.
const showFeatureFlags = ref(false)

const enabledFlagCount = computed(() => USER_FEATURE_FLAG_KEYS.filter((k) => form[k]).length)

function isGroupAllOn(group: UserFeatureFlagGroup): boolean {
  return group.flags.every((f) => form[f.key])
}

// One click to set or clear a whole group — with 40 toggles, doing this by
// hand is where mistakes come from.
function toggleGroup(group: UserFeatureFlagGroup) {
  const next = !isGroupAllOn(group)
  for (const flag of group.flags) form[flag.key] = next
}

function resetForm(values: UserDetailFormValues) {
  Object.assign(form, values)
}

function openCreateForm() {
  editingId.value = null
  currentProofUrl.value = null
  resetForm(makeEmptyForm())
  formError.value = ''
  clearFieldErrors()
  isFormOpen.value = true
}

// The table/picker row (UserDetailListItem) no longer carries address/
// signature/code/role_id/etc — those only exist on the full record, so
// editing now needs its own GET /user-details/{id} instead of reusing the
// list row like it used to.
async function openEditForm(row: UserDetailListItem) {
  editingId.value = row.id
  formError.value = ''
  clearFieldErrors()
  isFormOpen.value = true
  isLoadingEditUser.value = true
  currentProofUrl.value = null
  try {
    const { user, aadhar_image_url } = await userDetailsApi.get(row.id)
    currentProofUrl.value = aadhar_image_url
    resetForm({
      ...makeEmptyForm(),
      name: user.name,
      user_name: user.user_name,
      address: user.address,
      signature: user.signature,
      // code / system_id / proff have no inputs any more, but are still
      // loaded so update() writes the stored values back unchanged rather
      // than toPayload() replacing them with placeholders.
      code: user.code,
      phone_no: user.phone_no,
      remarks: user.remarks ?? '',
      proff: user.proff,
      // UserDetail casts role_id to integer, but the Role picker's options
      // are String(role.id) — without this the select can't match and shows
      // empty on edit.
      role_id: String(user.role_id),
      system_id: user.system_id,
      mailing_name: user.mailing_name,
      customer_commants: user.customer_commants ?? '',
      category_name: user.category_name,
      is_active: user.is_active,
      is_delete: user.is_delete,
      is_billable: user.is_billable,
      // Feature flags come back from show() on the full model. Start from the
      // all-false defaults and overlay whatever the response carries, so a
      // column the API omits stays false rather than becoming undefined and
      // failing the Record<..., boolean> contract.
      ...emptyUserFeatureFlags(),
      ...Object.fromEntries(
        USER_FEATURE_FLAG_KEYS.filter((k) => typeof user[k] === 'boolean').map((k) => [
          k,
          user[k] as boolean,
        ]),
      ),
      // show() eager-loads all three mapping relations; map them back into
      // the same input shapes the create form uses.
      item_mappings: (user.item_mappings ?? []).map((m) => ({
        item_id: m.item_id,
        item_grams_total: m.item_grams_total ?? '0',
        item_purity_total: m.item_purity_total ?? '0',
        is_primary: m.is_primary ? 1 : 0,
      })),
      head_mappings: (user.head_employee_mappings ?? []).map((m) => ({ head_id: m.head_id })),
      cash_head_mappings: (user.cash_head_employee_mappings ?? []).map((m) => ({
        head_id: m.head_id,
      })),
    })
  } catch (err) {
    isFormOpen.value = false
    toastStore.show(
      err instanceof ApiError ? err.message : 'Failed to load user details.',
      'error',
    )
  } finally {
    isLoadingEditUser.value = false
  }
}

function closeForm() {
  isFormOpen.value = false
}

// ----- mapping row helpers -----
function addItemMapping() {
  form.item_mappings.push({
    item_id: null,
    item_grams_total: '0',
    item_purity_total: '0',
    is_primary: 0,
  })
}
function removeItemMapping(index: number) {
  form.item_mappings.splice(index, 1)
}
// "Reports to" is a multi-select over head ids; form.head_mappings keeps its
// { head_id } row shape so toPayload() and the request body are unchanged.
const headIds = computed<number[]>({
  get: () =>
    form.head_mappings.map((m) => m.head_id).filter((id): id is number => id !== null),
  set: (ids) => {
    form.head_mappings = ids.map((head_id) => ({ head_id }))
  },
})
function addCashHeadMapping() {
  form.cash_head_mappings.push({ head_id: null })
}
function removeCashHeadMapping(index: number) {
  form.cash_head_mappings.splice(index, 1)
}

// Mirrors the max-length rules enforced by UserDetailController::store()/update().
// Signature and mailing name are optional on this form — toPayload() fills
// blanks with the placeholders store() needs — so they're length-checked
// in validate() instead.
const requiredFields: { key: keyof UserDetailFormValues; label: string; maxLength: number }[] = [
  { key: 'name', label: 'Name', maxLength: 55 },
  { key: 'user_name', label: 'Username', maxLength: 55 },
  { key: 'address', label: 'Address', maxLength: 400 },
  { key: 'phone_no', label: 'Phone number', maxLength: 15 },
]

// store()/update(): aadhar_image is image|mimes:jpg,jpeg,png,webp|max:5120 (KB).
const MAX_PROOF_BYTES = 5120 * 1024

const fieldErrors = reactive<Record<string, string>>({})

// The multi-select can't hold an empty row, so the only head errors left are
// server-side ones keyed per row (head_mappings.0.head_id) — surface the
// first under the single control.
const headMappingsError = computed(
  () => Object.entries(fieldErrors).find(([key]) => key.startsWith('head_mappings'))?.[1],
)

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function validate(): boolean {
  clearFieldErrors()
  formError.value = ''

  for (const f of requiredFields) {
    const value = String(form[f.key] ?? '').trim()
    if (!value) {
      fieldErrors[f.key] = `${f.label} is required.`
    } else if (value.length > f.maxLength) {
      fieldErrors[f.key] = `${f.label} must be ${f.maxLength} characters or fewer.`
    }
  }

  if (!String(form.role_id).trim()) {
    fieldErrors.role_id = 'Role is required.'
  }

  if (form.phone_no.trim() && !/^\d{6,15}$/.test(form.phone_no.trim())) {
    fieldErrors.phone_no = 'Phone number must be 6-15 digits.'
  }

  if (form.signature.length > 45) {
    fieldErrors.signature = 'Signature must be 45 characters or fewer.'
  }
  if (form.mailing_name.length > 255) {
    fieldErrors.mailing_name = 'Mailing name must be 255 characters or fewer.'
  }
  if (form.remarks.length > 255) {
    fieldErrors.remarks = 'Remarks must be 255 characters or fewer.'
  }
  if (form.customer_commants.length > 1500) {
    fieldErrors.customer_commants = 'Customer comments must be 1500 characters or fewer.'
  }
  if (form.aadhar_image && form.aadhar_image.size > MAX_PROOF_BYTES) {
    fieldErrors.aadhar_image = 'Proof image must be 5 MB or smaller.'
  }

  if (editingId.value === null && form.password.trim().length < 6) {
    fieldErrors.password = 'Password is required (min 6 characters).'
  }

  form.item_mappings.forEach((mapping, index) => {
    if (mapping.item_id === null) {
      fieldErrors[`item_mappings.${index}`] = `Select an item for row ${index + 1} or remove it.`
    }
  })
  form.cash_head_mappings.forEach((mapping, index) => {
    if (mapping.head_id === null) {
      fieldErrors[`cash_head_mappings.${index}`] =
        `Select a cash head for row ${index + 1} or remove it.`
    }
  })

  const firstError = Object.values(fieldErrors)[0]
  if (firstError) {
    formError.value = firstError
    toastStore.show(firstError, 'error')
    return false
  }
  return true
}

async function handleSubmit() {
  if (!validate()) return

  isSaving.value = true
  try {
    if (editingId.value !== null) {
      await userDetailsApi.update(editingId.value, { ...form })
    } else {
      await userDetailsApi.create({ ...form })
    }
    isFormOpen.value = false
    toastStore.show(
      editingId.value !== null ? 'User updated successfully.' : 'User created successfully.',
      'success',
    )
    await loadData()
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      if (err.errors) {
        for (const [key, messages] of Object.entries(err.errors)) {
          fieldErrors[key] = messages[0]
        }
        toastStore.show(Object.values(err.errors)[0]?.[0] ?? err.message, 'error')
      } else {
        toastStore.show(err.message, 'error')
      }
    } else {
      formError.value = 'Failed to save user.'
      toastStore.show('Failed to save user.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}

const deletingId = ref<number | null>(null)

async function handleDelete(user: UserDetailListItem) {
  deletingId.value = user.id
  try {
    await userDetailsApi.remove(user.id)
    await loadData()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to delete user.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="Users" description="Manage users and their item / head assignments.">
      <template #actions>
        <BaseButton variant="secondary" :icon="RefreshCw" @click="loadData">Refresh</BaseButton>
        <BaseButton :icon="Plus" @click="openCreateForm">New user</BaseButton>
      </template>
    </PageHeader>

    <BaseCard v-if="isFormOpen" class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">
          {{ editingId !== null ? 'Edit user' : 'New user' }}
        </h2>
        <button
          type="button"
          class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close form"
          @click="closeForm"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <p
        v-if="formError"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ formError }}
      </p>

      <p v-if="isLoadingEditUser" class="py-6 text-center text-sm text-slate-500">
        Loading user…
      </p>

      <form v-else class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <!-- Core details -->
        <section class="grid gap-3 sm:grid-cols-3">
          <BaseInput
            id="name"
            v-model="form.name"
            label="Name"
            required
            size="sm"
            maxlength="55"
            :error="fieldErrors.name"
          />
          <BaseInput
            id="user_name"
            v-model="form.user_name"
            label="Username"
            required
            size="sm"
            maxlength="55"
            :error="fieldErrors.user_name"
          />
          <BaseInput
            id="password"
            v-model="form.password"
            :label="editingId !== null ? 'Password (leave blank to keep)' : 'Password'"
            type="password"
            :required="editingId === null"
            size="sm"
            :error="fieldErrors.password"
          />
          <BaseInput
            id="phone_no"
            v-model="form.phone_no"
            label="Phone number"
            required
            size="sm"
            maxlength="15"
            :error="fieldErrors.phone_no"
          />
          <BaseInput
            id="address"
            v-model="form.address"
            label="Address"
            required
            size="sm"
            maxlength="400"
            :error="fieldErrors.address"
          />
          <BaseInput
            id="mailing_name"
            v-model="form.mailing_name"
            label="Mailing name (optional)"
            placeholder="Defaults to name"
            size="sm"
            maxlength="255"
            :error="fieldErrors.mailing_name"
          />
          <BaseInput
            id="signature"
            v-model="form.signature"
            label="Signature (optional)"
            size="sm"
            maxlength="45"
            :error="fieldErrors.signature"
          />
          <div class="flex flex-col gap-1">
            <BaseFileInput
              id="aadhar_image"
              v-model="proofFiles"
              label="Proof image (optional)"
              hint="JPG, PNG or WEBP, up to 5 MB."
              compact
              :multiple="false"
            />
            <a
              v-if="currentProofUrl && !form.aadhar_image"
              :href="currentProofUrl"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-brand-600"
            >
              <img
                :src="currentProofUrl"
                alt="Saved proof"
                class="h-9 w-9 rounded-md border border-slate-200 object-cover"
              />
              Saved proof — view
            </a>
            <p v-if="fieldErrors.aadhar_image" class="text-sm text-red-600">
              {{ fieldErrors.aadhar_image }}
            </p>
          </div>

          <BaseSelect
            id="role_id"
            v-model="form.role_id"
            label="Role"
            required
            size="sm"
            placeholder="Select a role…"
            :options="roles.map((role) => ({ value: String(role.id), label: role.role }))"
            :error="fieldErrors.role_id"
          />

          <BaseSelect
            id="category_name"
            v-model="form.category_name"
            label="Category"
            required
            size="sm"
            :options="categories.map((cat) => ({ value: cat, label: cat }))"
          />

          <BaseInput
            id="remarks"
            v-model="form.remarks"
            label="Remarks (optional)"
            size="sm"
            maxlength="255"
            :error="fieldErrors.remarks"
          />
          <BaseInput
            id="customer_commants"
            v-model="form.customer_commants"
            label="Customer comments (optional)"
            size="sm"
            maxlength="1500"
            :error="fieldErrors.customer_commants"
          />
        </section>

        <section class="flex flex-wrap gap-6">
          <BaseCheckbox v-model="form.is_active" label="Active" />
          <BaseCheckbox v-model="form.is_billable" label="Billable" />
          <BaseCheckbox v-model="form.is_delete" label="Marked deleted" />
        </section>

        <!--
          Feature flags. 40 booleans, grouped rather than listed flat — see
          lib/userFeatureFlags.ts for why these 40 and not the 44 columns.
          Collapsed by default so the everyday path (name, role, mappings) is
          not buried under a wall of checkboxes.
        -->
        <section class="border-t border-slate-200 pt-4">
          <button
            type="button"
            class="flex w-full items-center justify-between text-left"
            @click="showFeatureFlags = !showFeatureFlags"
          >
            <span>
              <span class="text-sm font-semibold text-slate-900">Permissions &amp; features</span>
              <span class="ml-2 text-xs text-slate-500">
                {{ enabledFlagCount }} of {{ USER_FEATURE_FLAG_KEYS.length }} enabled
              </span>
            </span>
            <ChevronDown
              class="h-4 w-4 text-slate-400 transition-transform"
              :class="showFeatureFlags ? 'rotate-180' : ''"
            />
          </button>

          <div v-if="showFeatureFlags" class="mt-4 flex flex-col gap-5">
            <p class="text-xs text-slate-500">
              Controls which screens and options this user sees. These are stored per user on
              <code>user_details</code>; the app does not gate navigation on them yet, so today
              they record intent for the legacy screens.
            </p>

            <div
              v-for="group in USER_FEATURE_FLAG_GROUPS"
              :key="group.title"
              class="rounded-lg border border-slate-200 p-3"
            >
              <div class="mb-2 flex items-baseline justify-between gap-3">
                <h4 class="text-sm font-medium text-slate-900">{{ group.title }}</h4>
                <button
                  type="button"
                  class="text-xs font-medium text-brand-600 hover:text-brand-700"
                  @click="toggleGroup(group)"
                >
                  {{ isGroupAllOn(group) ? 'Clear all' : 'Select all' }}
                </button>
              </div>
              <p class="mb-3 text-xs text-slate-500">{{ group.description }}</p>
              <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <BaseCheckbox
                  v-for="flag in group.flags"
                  :key="flag.key"
                  v-model="form[flag.key]"
                  :label="flag.label"
                />
              </div>
            </div>
          </div>
        </section>

        <!--
          Backend gap: UserDetailController::update() never reads
          item_mappings / head_mappings / cash_head_mappings (only store()
          does), so edits to the three sections below are dropped on save.
          See BACKEND_TODO.md.
        -->
        <p
          v-if="editingId !== null"
          class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
        >
          Assigned items, heads and cash heads below are loaded from the saved record, but
          <strong>Save changes</strong> does not update them yet — the server's user update ignores
          mappings. To change who this user reports to, use
          <RouterLink to="/head-employee-mappings" class="font-medium underline">
            Head Employee Mappings</RouterLink
          >.
        </p>

        <!-- Item mappings -->
        <section class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Assigned items</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addItemMapping">
              Add item
            </BaseButton>
          </div>
          <p v-if="form.item_mappings.length === 0" class="text-sm text-slate-500">
            No items assigned.
          </p>
          <div
            v-for="(mapping, index) in form.item_mappings"
            :key="index"
            class="mb-3 grid items-end gap-3 sm:grid-cols-[1fr_1fr_1fr_auto_auto]"
          >
            <BaseSelect
              v-model="mapping.item_id"
              label="Item"
              size="sm"
              placeholder="Select…"
              :options="items.map((item) => ({ value: item.item_id, label: item.item_name }))"
              :error="fieldErrors[`item_mappings.${index}`]"
            />
            <BaseInput
              v-model="mapping.item_grams_total"
              label="Grams total"
              type="number"
              size="sm"
            />
            <BaseInput
              v-model="mapping.item_purity_total"
              label="Purity total"
              type="number"
              size="sm"
            />
            <BaseCheckbox
              class="pb-2"
              label="Primary"
              :model-value="mapping.is_primary === 1"
              @update:model-value="(v) => (mapping.is_primary = v ? 1 : 0)"
            />
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove item"
              @click="removeItemMapping(index)"
            >
              <Trash class="h-4 w-4" />
            </button>
          </div>
        </section>

        <!-- Head mappings -->
        <section class="border-t border-slate-200 pt-4">
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Reports to (heads)</h3>
          <BaseMultiSelect
            id="head_ids"
            v-model="headIds"
            label="Heads (users)"
            size="sm"
            placeholder="No heads assigned — select one or more…"
            :options="userOptions"
            :error="headMappingsError"
          />
        </section>

        <!-- Cash head mappings -->
        <section class="border-t border-slate-200 pt-4">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Cash heads</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addCashHeadMapping">
              Add cash head
            </BaseButton>
          </div>
          <p v-if="form.cash_head_mappings.length === 0" class="text-sm text-slate-500">
            No cash heads assigned.
          </p>
          <div
            v-for="(mapping, index) in form.cash_head_mappings"
            :key="index"
            class="mb-3 flex items-end gap-3"
          >
            <BaseSelect
              v-model="mapping.head_id"
              label="Cash head (user)"
              size="sm"
              class="flex-1"
              placeholder="Select a user…"
              :options="userOptions"
              :error="fieldErrors[`cash_head_mappings.${index}`]"
            />
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove cash head"
              @click="removeCashHeadMapping(index)"
            >
              <Trash class="h-4 w-4" />
            </button>
          </div>
        </section>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
          <BaseButton type="submit" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId !== null ? 'Save changes' : 'Create user' }}
          </BaseButton>
          <BaseButton variant="secondary" type="button" @click="closeForm">Cancel</BaseButton>
        </div>
      </form>
    </BaseCard>

    <div class="mb-4 flex items-center gap-2">
      <BaseInput
        v-model="searchQuery"
        type="search"
        :icon="Search"
        placeholder="Search users…"
        aria-label="Search users"
        class="w-full max-w-xs"
      />
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
      Loading users…
    </div>

    <DataTable
      v-else
      :columns="columns"
      :rows="filteredUsers"
      empty-message="No users yet. Add your first user to get started."
    >
      <template #is_active="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #id="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Edit user"
            @click="openEditForm(row as UserDetailListItem)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <ConfirmPopover
            :message="`Delete ${(row as UserDetailListItem).name}? This also removes their mappings.`"
            @confirm="handleDelete(row as UserDetailListItem)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                aria-label="Delete user"
                :disabled="deletingId === (row as UserDetailListItem).id"
                @click="toggle"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </ConfirmPopover>
        </div>
      </template>
    </DataTable>
  </div>
</template>