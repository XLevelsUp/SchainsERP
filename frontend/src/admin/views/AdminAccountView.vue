<script setup lang="ts">
import { reactive, ref } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import { useToastStore } from '@/stores/toast'
import type { AdminUser } from '@/types'
import { adminAccountApi } from '../lib/adminAuthApi'
import { useAdminAuthStore } from '../stores/adminAuth'

/*
|--------------------------------------------------------------------------
| Admin console — My account (Admin\AdminAccountController)
|--------------------------------------------------------------------------
| The signed-in admin's two secrets. Each change needs the current value of
| the secret being changed, and the two must differ. A forgotten one is
| reset on the server: php artisan admin:reset-password <user> [--special].
|--------------------------------------------------------------------------
*/

const adminAuth = useAdminAuthStore()
const toastStore = useToastStore()

// Shown dates; refreshed from each successful change.
const account = ref<AdminUser | null>(adminAuth.user)

type Errors = Record<string, string>

function firstErrors(err: unknown, fallback: string): { errors: Errors; message: string } {
  if (err instanceof ApiError) {
    const errors: Errors = {}
    for (const [key, messages] of Object.entries(err.errors ?? {})) errors[key] = messages[0]
    return { errors, message: Object.keys(errors).length ? '' : err.message }
  }
  return { errors: {}, message: fallback }
}

// ---- login password -------------------------------------------------------
const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const passwordErrors = ref<Errors>({})
const passwordMessage = ref('')
const isSavingPassword = ref(false)

async function savePassword() {
  if (isSavingPassword.value) return
  passwordErrors.value = {}
  passwordMessage.value = ''
  if (passwordForm.password !== passwordForm.password_confirmation) {
    passwordErrors.value = { password_confirmation: "The two new passwords don't match." }
    return
  }
  isSavingPassword.value = true
  try {
    account.value = await adminAccountApi.changePassword({ ...passwordForm })
    Object.assign(passwordForm, { current_password: '', password: '', password_confirmation: '' })
    toastStore.show('Password changed.', 'success')
  } catch (err) {
    const { errors, message } = firstErrors(err, 'Could not change the password.')
    passwordErrors.value = errors
    passwordMessage.value = message
  } finally {
    isSavingPassword.value = false
  }
}

// ---- special password -----------------------------------------------------
const specialForm = reactive({
  current_special_password: '',
  special_password: '',
  special_password_confirmation: '',
})
const specialErrors = ref<Errors>({})
const specialMessage = ref('')
const isSavingSpecial = ref(false)

async function saveSpecialPassword() {
  if (isSavingSpecial.value) return
  specialErrors.value = {}
  specialMessage.value = ''
  if (specialForm.special_password !== specialForm.special_password_confirmation) {
    specialErrors.value = { special_password_confirmation: "The two new special passwords don't match." }
    return
  }
  isSavingSpecial.value = true
  try {
    account.value = await adminAccountApi.changeSpecialPassword({ ...specialForm })
    Object.assign(specialForm, {
      current_special_password: '',
      special_password: '',
      special_password_confirmation: '',
    })
    toastStore.show('Special password changed.', 'success')
  } catch (err) {
    const { errors, message } = firstErrors(err, 'Could not change the special password.')
    specialErrors.value = errors
    specialMessage.value = message
  } finally {
    isSavingSpecial.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="My account"
      :description="account ? `${account.name} · ${account.user_name}` : undefined"
    />

    <div class="grid gap-6 lg:grid-cols-2">
      <BaseCard>
        <h2 class="text-sm font-semibold text-slate-900">Login password</h2>
        <p class="mt-1 mb-4 text-xs text-slate-500">
          Used to sign in to the admin console. At least 8 characters.
          <template v-if="account?.password_changed_at">
            Last changed {{ formatDateTime(account.password_changed_at) }}.
          </template>
        </p>
        <form class="flex flex-col gap-4" @submit.prevent="savePassword">
          <BaseInput
            id="current_password"
            v-model="passwordForm.current_password"
            label="Current password"
            type="password"
            autocomplete="current-password"
            required
            :error="passwordErrors.current_password"
          />
          <BaseInput
            id="new_password"
            v-model="passwordForm.password"
            label="New password"
            type="password"
            autocomplete="new-password"
            required
            :error="passwordErrors.password"
          />
          <BaseInput
            id="new_password_confirmation"
            v-model="passwordForm.password_confirmation"
            label="Confirm new password"
            type="password"
            autocomplete="new-password"
            required
            :error="passwordErrors.password_confirmation"
          />
          <p v-if="passwordMessage" class="text-sm text-red-600">{{ passwordMessage }}</p>
          <div class="flex justify-end border-t border-slate-200 pt-4">
            <BaseButton type="submit" :disabled="isSavingPassword">
              {{ isSavingPassword ? 'Saving…' : 'Change password' }}
            </BaseButton>
          </div>
        </form>
      </BaseCard>

      <BaseCard>
        <h2 class="text-sm font-semibold text-slate-900">Special password</h2>
        <p class="mt-1 mb-4 text-xs text-slate-500">
          Asked again for sensitive changes: assigning heads and deleting users. At least 6
          characters, and different from the login password.
          <template v-if="account?.special_password_changed_at">
            Last changed {{ formatDateTime(account.special_password_changed_at) }}.
          </template>
        </p>
        <form class="flex flex-col gap-4" @submit.prevent="saveSpecialPassword">
          <BaseInput
            id="current_special_password"
            v-model="specialForm.current_special_password"
            label="Current special password"
            type="password"
            autocomplete="off"
            required
            :error="specialErrors.current_special_password"
          />
          <BaseInput
            id="new_special_password"
            v-model="specialForm.special_password"
            label="New special password"
            type="password"
            autocomplete="off"
            required
            :error="specialErrors.special_password"
          />
          <BaseInput
            id="new_special_password_confirmation"
            v-model="specialForm.special_password_confirmation"
            label="Confirm new special password"
            type="password"
            autocomplete="off"
            required
            :error="specialErrors.special_password_confirmation"
          />
          <p v-if="specialMessage" class="text-sm text-red-600">{{ specialMessage }}</p>
          <div class="flex justify-end border-t border-slate-200 pt-4">
            <BaseButton type="submit" :disabled="isSavingSpecial">
              {{ isSavingSpecial ? 'Saving…' : 'Change special password' }}
            </BaseButton>
          </div>
        </form>
      </BaseCard>
    </div>
  </div>
</template>
