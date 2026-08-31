<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { rolesApi } from '@/lib/rolesApi'
import { ApiError } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { CategoryName, Role, UserDetailFormValues } from '@/types'

/*
|--------------------------------------------------------------------------
| Add User modal — quick-create, ported from UsersView.vue's create form
|--------------------------------------------------------------------------
| There's no separate "Retailer" entity/role in this backend — a retailer
| is just a regular user_details row that later shows up in a "Retailer"
| picker (e.g. StockOutPanel's retailer_id field). So the "Add Retailer"
| quick action on the Stock page reuses this exact same form/endpoint
| (POST /user-details) with a different title/badge for context, rather
| than inventing a backend-unsupported "retailer" concept.
|
| Mappings (item/head/cash-head) are intentionally left out here — they're
| optional on the backend (nullable|array) and this is meant to be fast;
| full user editing including mappings still lives in UsersView.vue.
|--------------------------------------------------------------------------
*/

const props = withDefaults(defineProps<{ title?: string }>(), { title: 'Add User' })
const emit = defineEmits<{ close: []; saved: [] }>()

const toastStore = useToastStore()

const roles = ref<Role[]>([])
async function loadRoles() {
  try {
    roles.value = await rolesApi.list()
  } catch {
    roles.value = []
  }
}
onMounted(loadRoles)

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
    item_mappings: [],
    head_mappings: [],
    cash_head_mappings: [],
  }
}

const form = reactive<UserDetailFormValues>(makeEmptyForm())
const formError = ref('')
const isSaving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

// Mirrors the max-length rules enforced by UserDetailController::store().
const requiredFields: { key: keyof UserDetailFormValues; label: string; maxLength: number }[] = [
  { key: 'name', label: 'Name', maxLength: 55 },
  { key: 'user_name', label: 'Username', maxLength: 55 },
  { key: 'address', label: 'Address', maxLength: 400 },
  { key: 'signature', label: 'Signature', maxLength: 45 },
  { key: 'code', label: 'Code', maxLength: 255 },
  { key: 'phone_no', label: 'Phone number', maxLength: 15 },
  { key: 'proff', label: 'Proof', maxLength: 155 },
  { key: 'system_id', label: 'System ID', maxLength: 255 },
  { key: 'mailing_name', label: 'Mailing name', maxLength: 255 },
]

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

  if (form.password.trim().length < 6) {
    fieldErrors.password = 'Password is required (min 6 characters).'
  }

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
  formError.value = ''
  try {
    await userDetailsApi.create({ ...form })
    toastStore.show('User created successfully.', 'success')
    emit('saved')
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
      formError.value = 'Failed to create user.'
      toastStore.show('Failed to create user.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal :title="props.title" max-width="max-w-3xl" @close="emit('close')">
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
      <section class="grid gap-3 sm:grid-cols-3">
        <BaseInput id="name" v-model="form.name" label="Name" required size="sm" maxlength="55" :error="fieldErrors.name" />
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
          label="Password"
          type="password"
          required
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
          label="Mailing name"
          required
          size="sm"
          maxlength="255"
          :error="fieldErrors.mailing_name"
        />
        <BaseInput
          id="signature"
          v-model="form.signature"
          label="Signature"
          required
          size="sm"
          maxlength="45"
          :error="fieldErrors.signature"
        />
        <BaseInput id="code" v-model="form.code" label="Code" required size="sm" maxlength="255" :error="fieldErrors.code" />
        <BaseInput
          id="proff"
          v-model="form.proff"
          label="Proof"
          required
          size="sm"
          maxlength="155"
          :error="fieldErrors.proff"
        />
        <BaseInput
          id="system_id"
          v-model="form.system_id"
          label="System ID"
          required
          size="sm"
          maxlength="255"
          :error="fieldErrors.system_id"
        />
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
      </section>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">Cancel</BaseButton>
        <BaseButton type="submit" :disabled="isSaving">{{ isSaving ? 'Creating…' : 'Create user' }}</BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
