<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { navItems } from '@/lib/nav'
import { APP_NAME } from '@/lib/constants'

withDefaults(defineProps<{ open?: boolean }>(), { open: false })
defineEmits<{ close: [] }>()

const route = useRoute()
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-30 flex w-64 shrink-0 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0"
    :class="{ 'translate-x-0': open }"
  >
    <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-6">
      <div
        class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-600 text-sm font-semibold text-white"
      >
        S
      </div>
      <span class="text-sm font-semibold text-slate-900">{{ APP_NAME }}</span>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
        :class="
          route.path === item.to
            ? 'bg-brand-50 text-brand-600'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="$emit('close')"
      >
        <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
        {{ item.label }}
      </RouterLink>
    </nav>
  </aside>
</template>
