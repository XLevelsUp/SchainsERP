<script setup lang="ts">
import { X } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    title: string
    badge?: string
    badgeClass?: string
    maxWidth?: string
  }>(),
  {
    badge: undefined,
    badgeClass: 'bg-slate-600',
    maxWidth: 'max-w-6xl',
  },
)

const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-8 print:hidden"
      @click.self="emit('close')"
    >
      <div class="w-full rounded-lg bg-white shadow-xl" :class="maxWidth">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
          <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
            <span
              v-if="badge"
              class="rounded-full px-2.5 py-1 text-xs font-semibold text-white"
              :class="badgeClass"
            >
              {{ badge }}
            </span>
          </div>
          <button
            type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="flex flex-col gap-5 px-6 py-5">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
