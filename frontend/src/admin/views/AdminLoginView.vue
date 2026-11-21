<script setup lang="ts">
import { RouterLink } from 'vue-router'
import SignInCard from '@/components/auth/SignInCard.vue'
import { useAdminAuthStore } from '../stores/adminAuth'

const adminAuth = useAdminAuthStore()
</script>

<template>
  <SignInCard
    variant="admin"
    title="Admin console"
    description="Sign in with your admin account to create users and assign them to heads."
    :sign-in="adminAuth.login"
    home-path="/admin"
    redirect-scope="/admin"
  >
    <template #footer>
      Admin accounts are separate from head and staff accounts.
      <RouterLink to="/login" class="font-medium text-slate-700 underline hover:text-slate-900">
        App sign-in
      </RouterLink>
    </template>
  </SignInCard>
</template>
