<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ShieldCheck } from 'lucide-vue-next'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/lib/api'
import type { LoginPayload } from '@/lib/authApi'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

/*
| The sign-in form shared by the app (LoginView) and the admin console
| (AdminLoginView). Each passes its own store's login and where it lives;
| the form, validation and error handling are the same.
*/
const props = withDefaults(
  defineProps<{
    title: string
    description: string
    signIn: (payload: LoginPayload) => Promise<void>
    // Where to go after signing in when there's no usable ?redirect.
    homePath: string
    // ?redirect is only followed inside this path, so the admin sign-in
    // never sends someone into the app or the reverse.
    redirectScope?: string
    variant?: 'app' | 'admin'
  }>(),
  { redirectScope: '/', variant: 'app' },
)

const router = useRouter()
const route = useRoute()

const form = reactive({
  user_name: '',
  password: '',
})

const errors = reactive({
  user_name: '',
  password: '',
})

const formError = ref('')
// A 403 (deactivated account, or no admin access) is not a typo the operator
// can correct by trying again, so it reads as a notice rather than a form
// error. Anything else — wrong credentials, server down — stays a plain error.
const isNotice = ref(false)
const isSubmitting = ref(false)

function validate(): boolean {
  errors.user_name = ''
  errors.password = ''

  if (!form.user_name) {
    errors.user_name = 'Username is required.'
  }

  if (!form.password) {
    errors.password = 'Password is required.'
  }

  return !errors.user_name && !errors.password
}

// Only follow an in-app absolute path back, inside this realm. Anything else
// — a protocol-relative "//host", a full URL someone put in the query
// string, or the other realm's pages — falls back to the home page.
function safeRedirect(value: unknown): string {
  if (typeof value !== 'string') return props.homePath
  if (!value.startsWith('/') || value.startsWith('//')) return props.homePath
  if (props.redirectScope !== '/' && value !== props.redirectScope && !value.startsWith(`${props.redirectScope}/`)) {
    return props.homePath
  }
  return value
}

async function handleSubmit() {
  formError.value = ''
  isNotice.value = false
  if (!validate()) return
  // Guard against a double submit racing two logins against each other.
  if (isSubmitting.value) return

  isSubmitting.value = true
  try {
    // The store owns the session: it posts the credentials, then persists
    // the returned user and Passport bearer token.
    await props.signIn({
      user_name: form.user_name,
      password: form.password,
    })

    router.push(safeRedirect(route.query.redirect))
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Sign in failed.'
    isNotice.value = err instanceof ApiError && err.status === 403
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <BaseCard class="w-full max-w-sm">
    <div class="mb-6 text-center">
      <div
        class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-md text-base font-semibold text-white"
        :class="variant === 'admin' ? 'bg-slate-900' : 'bg-brand-600'"
      >
        <ShieldCheck v-if="variant === 'admin'" class="h-5 w-5" aria-hidden="true" />
        <template v-else>S</template>
      </div>
      <h1 class="text-lg font-semibold text-slate-900">{{ title }}</h1>
      <p class="mt-1 text-sm text-slate-500">{{ description }}</p>
    </div>

    <div
      v-if="formError"
      class="mb-4 rounded-lg border px-4 py-3 text-sm"
      :class="
        isNotice ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-red-200 bg-red-50 text-red-700'
      "
    >
      {{ formError }}
    </div>

    <form class="flex flex-col gap-4" novalidate @submit.prevent="handleSubmit">
      <BaseInput
        :id="`${variant}_user_name`"
        v-model="form.user_name"
        label="Username"
        type="text"
        autocomplete="username"
        placeholder="admin"
        required
        :error="errors.user_name"
      />
      <BaseInput
        :id="`${variant}_password`"
        v-model="form.password"
        label="Password"
        type="password"
        autocomplete="current-password"
        placeholder="••••••••"
        required
        :error="errors.password"
      />
      <BaseButton type="submit" class="mt-2 w-full" :disabled="isSubmitting">
        {{ isSubmitting ? 'Signing in…' : 'Sign in' }}
      </BaseButton>
    </form>

    <div v-if="$slots.footer" class="mt-6 border-t border-slate-100 pt-4 text-center text-xs text-slate-500">
      <slot name="footer" />
    </div>
  </BaseCard>
</template>
