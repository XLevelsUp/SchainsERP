<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { rolesApi } from '@/lib/rolesApi'
import { emptyUserFeatureFlags } from '@/lib/userFeatureFlags'
import { userCategoryOptions } from '@/lib/userCategory'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { isValidPhone, PHONE_ERROR, PHONE_LENGTH, phoneDigits } from '@/lib/phone'
import type { CategoryName, UserDetailFormValues } from '@/types'

/*
|--------------------------------------------------------------------------
| Create new Retailer — the legacy dialog's Name + Phone Number, plus
| Category: it used to be hardcoded to GRAMS here, and category now has no
| default anywhere — the head picks one (see lib/userCategory.ts).
|--------------------------------------------------------------------------
| There is no "Retailer" entity in this backend — same fact AddUserModal's
| own comment documents — a retailer is just a user_details row that later
| shows up in RetailerPickerPanel's list. But AddUserModal is the FULL
| admin create form (11 required fields including username/password/
| address/signature/code/proof/system_id), and reusing it here — which is
| what this screen used to do, under the title "Add Retailer" — is exactly
| backwards from the legacy screen's minimal two-field dialog.
|
| So this is a SEPARATE, purpose-built modal rather than AddUserModal with
| a different title: it collects only Name and Phone, and synthesizes every
| other field UserDetailController::store() actually requires
| (name/user_name/password/address/signature/code/phone_no/proff/role_id/
| system_id/mailing_name/category_name — checked against the live
| validation rules, not the doc). None of the synthesized values are ever
| shown anywhere; the retailer is addressed by name/phone from here on.
|
| system_id is stamped rather than a literal '-', because store() validates
| it as `unique:user_details,system_id`. (The old phone book's create route
| used '-' for every contact; since 2026-11-24 the phone book is its own
| table and no longer creates users.)
|
| role_id: resolved by name ("CUSTOMER") from GET /roles rather than
| hardcoding RoleSeeder's numeric id — this codebase has a running history
| of role-numbering bugs from exactly that shortcut (see PENDING_WORK.md
| ask #21), so this looks the id up live instead of assuming it never
| changes.
|--------------------------------------------------------------------------
*/

const emit = defineEmits<{ close: []; saved: [userId: number] }>()

const toast = useToastStore()
const auth = useAuthStore()

// Category starts on PURITY, as new customers do in the admin console
// (agreed 2026-11-23); the operator can change it.
const form = reactive({ name: '', phone_no: '', category_name: 'PURITY' as CategoryName | null })
const formError = ref('')
const isSaving = ref(false)

// Resolved once on mount rather than hardcoded — see the comment above.
const customerRoleId = ref<string | null>(null)
async function loadCustomerRoleId() {
  try {
    const roles = await rolesApi.list()
    const match = roles.find((r) => r.role.toUpperCase() === 'CUSTOMER')
    customerRoleId.value = match ? String(match.id) : null
  } catch {
    customerRoleId.value = null
  }
}
onMounted(loadCustomerRoleId)

function validate(): boolean {
  formError.value = ''
  if (!form.name.trim()) {
    formError.value = 'Name is required.'
    return false
  }
  if (!isValidPhone(form.phone_no)) {
    formError.value = PHONE_ERROR
    return false
  }
  if (!form.category_name) {
    formError.value = 'Category is required.'
    return false
  }
  return true
}

// Unique-per-call technical values the backend requires but this dialog
// never asks for. Never displayed anywhere — the retailer is found again
// by name/phone from RetailerPickerPanel, not by these.
function synthesizeFormValues(): UserDetailFormValues {
  const stamp = `${Date.now()}${Math.floor(Math.random() * 90 + 10)}`
  return {
    name: form.name.trim(),
    user_name: `retailer_${stamp}`,
    password: Math.random().toString(36).slice(2, 14),
    address: '-',
    signature: '-',
    code: '-',
    phone_no: form.phone_no.trim(),
    remarks: '',
    proff: '-',
    role_id: customerRoleId.value ?? '',
    system_id: `RETAILER-${stamp}`,
    mailing_name: form.name.trim(),
    customer_commants: '',
    category_name: form.category_name,
    is_active: true,
    is_delete: false,
    is_billable: false,
    ...emptyUserFeatureFlags(),
    item_mappings: [],
    // Mapped to whoever creates it: the Stock screen's pickers list only
    // users mapped to the signed-in user, so an unmapped retailer would
    // never show up in the Retailer dropdown it was just added from.
    head_mappings: auth.user ? [{ head_id: auth.user.user_id }] : [],
    cash_head_mappings: [],
  }
}

async function handleSubmit() {
  if (!validate() || isSaving.value) return

  if (!customerRoleId.value) {
    formError.value =
      'Could not resolve the Customer role — check the Roles screen has a "CUSTOMER" role.'
    return
  }

  isSaving.value = true
  try {
    const result = await userDetailsApi.create(synthesizeFormValues())
    toast.show('Retailer created.', 'success')
    emit('saved', result.user.user_id)
  } catch (err) {
    if (err instanceof ApiError) {
      // A phone-number collision is the one realistic failure here (the
      // other synthesized unique fields are effectively collision-proof) —
      // surface it in terms of what the operator typed, not "system_id".
      formError.value = err.errors
        ? (Object.values(err.errors)[0]?.[0] ?? err.message)
        : err.message
    } else {
      formError.value = 'Failed to create retailer.'
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal title="Create new Retailer" max-width="max-w-md" @close="emit('close')">
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <form class="flex flex-col gap-5" @submit.prevent="handleSubmit">
      <BaseInput v-model="form.name" label="Name" required maxlength="55" />
      <BaseInput
        v-model="form.phone_no"
        label="Phone Number"
        required
        inputmode="numeric"
        :maxlength="PHONE_LENGTH"
        placeholder="10 digits"
        :sanitize="phoneDigits"
      />
      <BaseSelect
        v-model="form.category_name"
        label="Category"
        required
        placeholder="Select a category…"
        :options="userCategoryOptions"
      />

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">
          Close
        </BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
