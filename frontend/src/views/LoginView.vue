<script setup lang="ts">
import { RouterLink } from 'vue-router'
import SignInCard from '@/components/auth/SignInCard.vue'
import { useAuthStore } from '@/stores/auth'
import { APP_NAME } from '@/lib/constants'

const auth = useAuthStore()
</script>

<template>
  <SignInCard
    :title="`Sign in to ${APP_NAME}`"
    description="Enter your credentials to access your workspace."
    :sign-in="auth.login"
    home-path="/"
  >
    <template #footer>
      Have an admin account?
      <RouterLink to="/admin/login" class="font-medium text-slate-700 underline hover:text-slate-900">
        Admin console
      </RouterLink>
    </template>
  </SignInCard>
</template>
