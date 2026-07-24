<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import { APP_NAME } from '@/lib/constants'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const isSubmitting = ref(false)

function validate(): boolean {
  errors.email = ''
  errors.password = ''

  if (!form.email) {
    errors.email = 'Email is required.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'Enter a valid email address.'
  }

  if (!form.password) {
    errors.password = 'Password is required.'
  } else if (form.password.length < 6) {
    errors.password = 'Password must be at least 6 characters.'
  }

  return !errors.email && !errors.password
}

async function handleSubmit() {
  if (!validate()) return

  isSubmitting.value = true
  // Stub: simulates an auth request. Replace with a real API call later.
  await new Promise((resolve) => setTimeout(resolve, 400))
  auth.login(form.email)
  isSubmitting.value = false

  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
  router.push(redirect)
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

    <form class="flex flex-col gap-4" novalidate @submit.prevent="handleSubmit">
      <BaseInput
        id="email"
        v-model="form.email"
        label="Email"
        type="email"
        autocomplete="email"
        placeholder="you@company.com"
        required
        :error="errors.email"
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
