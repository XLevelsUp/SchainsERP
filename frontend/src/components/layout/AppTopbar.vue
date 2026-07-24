<script setup lang="ts">
import { Menu, LogOut } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineEmits<{ 'toggle-sidebar': [] }>()

const router = useRouter()
const auth = useAuthStore()

function handleLogout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header
    class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6"
  >
    <button
      type="button"
      class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
      aria-label="Toggle sidebar"
      @click="$emit('toggle-sidebar')"
    >
      <Menu class="h-5 w-5" />
    </button>

    <div class="flex-1" />

    <div class="flex items-center gap-3">
      <span v-if="auth.userEmail" class="hidden text-sm text-slate-600 sm:inline">
        {{ auth.userEmail }}
      </span>
      <button
        type="button"
        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
        @click="handleLogout"
      >
        <LogOut class="h-4 w-4" />
        Sign out
      </button>
    </div>
  </header>
</template>
