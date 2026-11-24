<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { useCompanyName } from '@/lib/companyName'
import { APP_NAME } from '@/lib/constants'
import { adminApi } from '../lib/adminApi'
import { buildAdminNavItems } from '../lib/nav'
import { useAdminAuthStore } from '../stores/adminAuth'

const router = useRouter()
const adminAuth = useAdminAuthStore()
// Heads the company group in the menu (the `company_name` setting).
const companyName = useCompanyName(adminApi)
const adminNavItems = computed(() => buildAdminNavItems(companyName.value))

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
