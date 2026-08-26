<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ChevronRight } from 'lucide-vue-next'
import { RouterLink, useRoute } from 'vue-router'
import type { NavGroup } from '@/types/nav'

const props = defineProps<{ group: NavGroup; collapsed: boolean }>()
const emit = defineEmits<{ (e: 'expand-sidebar'): void }>()

const route = useRoute()
const isActive = computed(() => props.group.children.some((child) => child.to === route.path))
const isOpen = ref(isActive.value)

watch(
  () => props.collapsed,
  (collapsed) => {
    if (collapsed) isOpen.value = false
  },
)

function toggle() {
  if (props.collapsed) {
    emit('expand-sidebar')
    isOpen.value = true
    return
  }
  isOpen.value = !isOpen.value
}
</script>

<template>
  <div>
    <button
      type="button"
      class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
      :class="[
        isActive || isOpen
          ? 'bg-brand-50 text-brand-600'
          : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        collapsed ? 'justify-center' : '',
      ]"
      :title="collapsed ? group.label : undefined"
      :aria-expanded="isOpen"
      @click="toggle"
    >
      <component :is="group.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
      <span v-if="!collapsed" class="flex-1 text-left">{{ group.label }}</span>
      <ChevronRight
        v-if="!collapsed"
        class="h-3.5 w-3.5 shrink-0 transition-transform"
        :class="isOpen ? 'rotate-90' : ''"
      />
    </button>

    <div v-if="isOpen && !collapsed" class="mt-1 space-y-0.5 pl-4">
      <RouterLink
        v-for="child in group.children"
        :key="child.to"
        :to="child.to"
        class="flex items-center gap-3 rounded-lg py-2 pr-3 pl-5 text-sm font-medium transition-colors"
        :class="
          route.path === child.to
            ? 'bg-brand-50 text-brand-600'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
      >
        <component :is="child.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
        {{ child.label }}
      </RouterLink>
    </div>
  </div>
</template>
