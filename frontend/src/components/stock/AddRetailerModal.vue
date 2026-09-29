<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { rolesApi } from '@/lib/rolesApi'
import { emptyUserFeatureFlags } from '@/lib/userFeatureFlags'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { UserDetailFormValues } from '@/types'

/*
|--------------------------------------------------------------------------
| Create new Retailer — matches the legacy dialog exactly: Name + Phone
| Number, nothing else.
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
| Same pattern PhoneBookController::store() already uses server-side for
| its own "just needs a name and phone" contacts (auto user_name, hardcoded
| signature/code/proff) — but PhoneBook hardcodes system_id to a literal
| '-' for every contact, which would violate store()'s
| `unique:user_details,system_id` on the second retailer ever created
| (PhoneBook bypasses that validator with its own weaker one; this modal
| goes through the real one, since userDetailsApi.create() is
| POST /user-details, so it has to satisfy it for real).
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

const form = reactive({ name: '', phone_no: '' })
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
  const phone = form.phone_no.trim()
  if (!/^\d{6,15}$/.test(phone)) {
    formError.value = 'Phone number must be 6-15 digits.'
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
    category_name: 'GRAMS',
    is_active: true,
    is_delete: false,
    is_billable: false,
    ...emptyUserFeatureFlags(),
    item_mappings: [],
    head_mappings: [],
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
      <BaseInput v-model="form.phone_no" label="Phone Number" required maxlength="15" />

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
