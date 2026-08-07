<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { RouterLink, useRoute } from 'vue-router'
import type { NavGroup } from '@/types/nav'

const props = defineProps<{ group: NavGroup }>()

const route = useRoute()
const isOpen = ref(false)
const rootRef = ref<HTMLElement | null>(null)

const isActive = computed(() => props.group.children.some((child) => child.to === route.path))

function close() {
  isOpen.value = false
}

function toggle() {
  isOpen.value = !isOpen.value
}

function handleClickOutside(event: MouseEvent) {
  if (!rootRef.value?.contains(event.target as Node)) close()
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

watch(isOpen, (open) => {
  if (open) {
    document.addEventListener('mousedown', handleClickOutside)
    document.addEventListener('keydown', handleKeydown)
  } else {
    document.removeEventListener('mousedown', handleClickOutside)
    document.removeEventListener('keydown', handleKeydown)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="rootRef" class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
      :class="
        isActive || isOpen
          ? 'bg-brand-50 text-brand-600'
          : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
      "
      :aria-expanded="isOpen"
      @click="toggle"
    >
      <component :is="group.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
      {{ group.label }}
      <ChevronDown
        class="h-3.5 w-3.5 shrink-0 transition-transform"
        :class="isOpen ? 'rotate-180' : ''"
      />
    </button>

    <div
      v-if="isOpen"
      class="absolute top-full left-0 z-20 mt-1 w-56 rounded-lg border border-slate-200 bg-white p-1.5 shadow-lg"
    >
      <RouterLink
        v-for="child in group.children"
        :key="child.to"
        :to="child.to"
        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
        :class="
          route.path === child.to
            ? 'bg-brand-50 text-brand-600'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="close"
      >
        <component :is="child.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
        {{ child.label }}
      </RouterLink>
    </div>
  </div>
</template>
