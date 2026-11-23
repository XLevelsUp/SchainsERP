<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { navItems, visibleNavItems } from '@/lib/nav'
import { APP_NAME } from '@/lib/constants'
import AppSidebar from './AppSidebar.vue'

const router = useRouter()
const auth = useAuthStore()

// Only what the signed-in user's permissions allow (lib/appPermissions.ts).
const visibleNav = computed(() => visibleNavItems(navItems, auth.can))

// auth.logout() revokes the token server-side before clearing the local
// session, and never rejects — so the redirect below always runs.
async function handleSignOut() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-slate-50 lg:flex-row">
    <AppSidebar
      :items="visibleNav"
      :title="APP_NAME"
      :user-name="auth.user?.name"
      :is-signing-out="auth.isLoggingOut"
      @sign-out="handleSignOut"
    />
    <main class="flex-1 overflow-y-auto p-6">
      <RouterView />
    </main>
  </div>
</template>
