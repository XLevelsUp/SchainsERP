<script setup lang="ts">
import { ref } from 'vue'
import { Menu, X, LogOut } from 'lucide-vue-next'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { navItems } from '@/lib/nav'
import { APP_NAME } from '@/lib/constants'
import NavDropdown from './NavDropdown.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const isMobileMenuOpen = ref(false)

function handleLogout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="shrink-0 border-b border-slate-200 bg-white print:hidden">
    <div class="flex h-16 items-center gap-6 px-4 sm:px-6">
      <div class="flex items-center gap-2">
        <div
          class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-600 text-sm font-semibold text-white"
        >
          S
        </div>
        <span class="text-sm font-semibold text-slate-900">{{ APP_NAME }}</span>
      </div>

      <nav class="hidden flex-1 items-center gap-1 lg:flex">
        <template v-for="item in navItems" :key="item.label">
          <NavDropdown v-if="item.type === 'group'" :group="item" />
          <RouterLink
            v-else
            :to="item.to"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="
              route.path === item.to
                ? 'bg-brand-50 text-brand-600'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            "
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ item.label }}
          </RouterLink>
        </template>
      </nav>

      <div class="flex-1 lg:hidden" />

      <button
        type="button"
        class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
        aria-label="Toggle menu"
        @click="isMobileMenuOpen = !isMobileMenuOpen"
      >
        <component :is="isMobileMenuOpen ? X : Menu" class="h-5 w-5" />
      </button>

      <div class="flex items-center gap-3">
        <span v-if="auth.user" class="hidden text-sm text-slate-600 sm:inline">
          {{ auth.user.name }}
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
    </div>

    <nav v-if="isMobileMenuOpen" class="space-y-1 border-t border-slate-200 px-3 py-3 lg:hidden">
      <template v-for="item in navItems" :key="item.label">
        <RouterLink
          v-if="item.type === 'link'"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
          :class="
            route.path === item.to
              ? 'bg-brand-50 text-brand-600'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          "
          @click="isMobileMenuOpen = false"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
          {{ item.label }}
        </RouterLink>

        <div v-else class="pt-2 first:pt-0">
          <p
            class="flex items-center gap-2 px-3 pb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase"
          >
            <component :is="item.icon" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            {{ item.label }}
          </p>
          <RouterLink
            v-for="child in item.children"
            :key="child.to"
            :to="child.to"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="
              route.path === child.to
                ? 'bg-brand-50 text-brand-600'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            "
            @click="isMobileMenuOpen = false"
          >
            <component :is="child.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ child.label }}
          </RouterLink>
        </div>
      </template>
    </nav>
  </header>
</template>
