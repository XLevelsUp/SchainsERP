<script setup lang="ts">
import { ref } from 'vue'
import { ChevronsLeft, ChevronsRight, LogOut, Menu, X } from 'lucide-vue-next'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { navItems } from '@/lib/nav'
import { APP_NAME } from '@/lib/constants'
import NavGroupItem from './NavGroupItem.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const isCollapsed = ref(false)
const isMobileOpen = ref(false)

function toggleCollapsed() {
  isCollapsed.value = !isCollapsed.value
}

function expandSidebar() {
  isCollapsed.value = false
}

function handleLogout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header
    class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden print:hidden"
  >
    <div class="flex items-center gap-2">
      <div
        class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-600 text-sm font-semibold text-white"
      >
        S
      </div>
      <span class="text-sm font-semibold text-slate-900">{{ APP_NAME }}</span>
    </div>
    <button
      type="button"
      class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
      aria-label="Toggle menu"
      @click="isMobileOpen = !isMobileOpen"
    >
      <component :is="isMobileOpen ? X : Menu" class="h-5 w-5" />
    </button>
  </header>

  <div
    v-if="isMobileOpen"
    class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
    @click="isMobileOpen = false"
  />

  <aside
    class="fixed inset-y-0 left-0 z-40 flex h-screen w-64 shrink-0 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 print:hidden lg:static lg:translate-x-0"
    :class="[isMobileOpen ? 'translate-x-0' : '', isCollapsed ? 'lg:w-[68px]' : 'lg:w-64']"
  >
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-200 px-4">
      <div
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand-600 text-sm font-semibold text-white"
      >
        S
      </div>
      <span v-if="!isCollapsed" class="truncate text-sm font-semibold text-slate-900">{{
        APP_NAME
      }}</span>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto p-3">
      <template v-for="item in navItems" :key="item.label">
        <NavGroupItem
          v-if="item.type === 'group'"
          :group="item"
          :collapsed="isCollapsed"
          @expand-sidebar="expandSidebar"
        />
        <RouterLink
          v-else
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
          :class="[
            route.path === item.to
              ? 'bg-brand-50 text-brand-600'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
            isCollapsed ? 'justify-center' : '',
          ]"
          :title="isCollapsed ? item.label : undefined"
          @click="isMobileOpen = false"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
          <span v-if="!isCollapsed">{{ item.label }}</span>
        </RouterLink>
      </template>
    </nav>

    <div class="shrink-0 border-t border-slate-200 p-3">
      <div v-if="auth.user && !isCollapsed" class="truncate px-3 pb-2 text-sm text-slate-600">
        {{ auth.user.name }}
      </div>
      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
        :class="isCollapsed ? 'justify-center' : ''"
        :title="isCollapsed ? 'Sign out' : undefined"
        @click="handleLogout"
      >
        <LogOut class="h-4 w-4 shrink-0" />
        <span v-if="!isCollapsed">Sign out</span>
      </button>
      <button
        type="button"
        class="mt-1 hidden w-full items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:flex"
        :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="toggleCollapsed"
      >
        <component :is="isCollapsed ? ChevronsRight : ChevronsLeft" class="h-4 w-4 shrink-0" />
        <span v-if="!isCollapsed">Collapse</span>
      </button>
    </div>
  </aside>
</template>
