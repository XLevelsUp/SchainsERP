<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { APP_NAME } from '@/lib/constants'
import { adminNavItems } from '../lib/nav'
import { useAdminAuthStore } from '../stores/adminAuth'

const router = useRouter()
const adminAuth = useAdminAuthStore()

// Ends the admin session only (revoked server-side).
async function handleSignOut() {
  await adminAuth.logout()
  router.push({ name: 'admin-login' })
}
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-slate-50 lg:flex-row">
    <AppSidebar
      variant="admin"
      badge="Admin"
      :items="adminNavItems"
      :title="APP_NAME"
      :user-name="adminAuth.user?.name"
      :is-signing-out="adminAuth.isLoggingOut"
      @sign-out="handleSignOut"
    />
    <main class="flex-1 overflow-y-auto p-6">
      <RouterView />
    </main>
  </div>
</template>
