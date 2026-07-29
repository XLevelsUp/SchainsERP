<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/lib/authApi'
import { ApiError } from '@/lib/api'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import { APP_NAME } from '@/lib/constants'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = reactive({
  user_name: '',
  password: '',
})

const errors = reactive({
  user_name: '',
  password: '',
})

const formError = ref('')
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

async function handleSubmit() {
  formError.value = ''
  if (!validate()) return

  isSubmitting.value = true
  try {
    const user = await authApi.login({
      user_name: form.user_name,
      password: form.password,
    })
    auth.login(user)

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.push(redirect)
  } catch (err) {
    formError.value = err instanceof ApiError ? err.message : 'Sign in failed.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <BaseCard class="w-full max-w-sm">
    <div class="mb-6 text-center">
      <div
        class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-md bg-brand-600 text-base font-semibold text-white"
      >
        S
      </div>
      <h1 class="text-lg font-semibold text-slate-900">Sign in to {{ APP_NAME }}</h1>
      <p class="mt-1 text-sm text-slate-500">Enter your credentials to access your workspace.</p>
    </div>

    <div
      v-if="formError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ formError }}
    </div>

    <form class="flex flex-col gap-4" novalidate @submit.prevent="handleSubmit">
      <BaseInput
        id="user_name"
        v-model="form.user_name"
        label="Username"
        type="text"
        autocomplete="username"
        placeholder="admin"
        required
        :error="errors.user_name"
      />
      <BaseInput
        id="password"
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
  </BaseCard>
</template>
