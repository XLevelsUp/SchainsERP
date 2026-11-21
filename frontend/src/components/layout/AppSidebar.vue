<script setup lang="ts">
import { ref } from 'vue'
import { ChevronsLeft, ChevronsRight, LogOut, Menu, ShieldCheck, X } from 'lucide-vue-next'
import { RouterLink, useRoute } from 'vue-router'
import type { NavItem, NavLink } from '@/types/nav'
import NavGroupItem from './NavGroupItem.vue'

/*
| Presentational: the shells own the session. AppShell passes the app's
| navigation and store, AdminShell the admin console's, so both realms share
| one sidebar and look like one product. `variant: 'admin'` swaps the mark
| for a shield and shows the badge, so it's always clear which side you're on.
*/
const props = withDefaults(
  defineProps<{
    items: NavItem[]
    title: string
    variant?: 'app' | 'admin'
    // Short label beside the title, e.g. "Admin".
    badge?: string
    userName?: string | null
    isSigningOut?: boolean
    // A link above Sign out, e.g. into the admin console or back to the app.
    footerLink?: NavLink | null
  }>(),
  { variant: 'app', badge: undefined, userName: null, isSigningOut: false, footerLink: null },
)

const emit = defineEmits<{ (e: 'sign-out'): void }>()

const route = useRoute()

const isCollapsed = ref(false)
const isMobileOpen = ref(false)

function toggleCollapsed() {
  isCollapsed.value = !isCollapsed.value
}

function expandSidebar() {
  isCollapsed.value = false
}

const markClass = props.variant === 'admin' ? 'bg-slate-900' : 'bg-brand-600'
</script>

<template>
  <header
    class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden print:hidden"
  >
    <div class="flex items-center gap-2">
      <div
        class="flex h-8 w-8 items-center justify-center rounded-md text-sm font-semibold text-white"
        :class="markClass"
      >
        <ShieldCheck v-if="variant === 'admin'" class="h-4 w-4" aria-hidden="true" />
        <template v-else>S</template>
      </div>
      <span class="text-sm font-semibold text-slate-900">{{ title }}</span>
      <span
        v-if="badge"
        class="rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-white uppercase"
        >{{ badge }}</span
      >
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
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-sm font-semibold text-white"
        :class="markClass"
      >
        <ShieldCheck v-if="variant === 'admin'" class="h-4 w-4" aria-hidden="true" />
        <template v-else>S</template>
      </div>
      <span v-if="!isCollapsed" class="truncate text-sm font-semibold text-slate-900">{{
        title
      }}</span>
      <span
        v-if="badge && !isCollapsed"
        class="rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-white uppercase"
        >{{ badge }}</span
      >
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto p-3">
      <template v-for="item in items" :key="item.label">
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
      <div v-if="userName && !isCollapsed" class="truncate px-3 pb-2 text-sm text-slate-600">
        {{ userName }}
      </div>
      <RouterLink
        v-if="footerLink"
        :to="footerLink.to"
        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
        :class="isCollapsed ? 'justify-center' : ''"
        :title="isCollapsed ? footerLink.label : undefined"
        @click="isMobileOpen = false"
      >
        <component :is="footerLink.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
        <span v-if="!isCollapsed">{{ footerLink.label }}</span>
      </RouterLink>
      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
        :class="isCollapsed ? 'justify-center' : ''"
        :title="isCollapsed ? 'Sign out' : undefined"
        :disabled="isSigningOut"
        @click="emit('sign-out')"
      >
        <LogOut class="h-4 w-4 shrink-0" />
        <span v-if="!isCollapsed">{{ isSigningOut ? 'Signing out…' : 'Sign out' }}</span>
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
