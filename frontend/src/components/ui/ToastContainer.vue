<script setup lang="ts">
import { CheckCircle2, XCircle, Info, X } from 'lucide-vue-next'
import { useToastStore, type ToastType } from '@/stores/toast'

const toastStore = useToastStore()

const icons: Record<ToastType, typeof CheckCircle2> = {
  success: CheckCircle2,
  error: XCircle,
  info: Info,
}

const styles: Record<ToastType, string> = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  error: 'border-red-200 bg-red-50 text-red-800',
  info: 'border-slate-200 bg-white text-slate-800',
}

const iconStyles: Record<ToastType, string> = {
  success: 'text-emerald-600',
  error: 'text-red-600',
  info: 'text-brand-600',
}
</script>

<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed top-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2"
    >
      <TransitionGroup
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-x-4"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-for="toast in toastStore.toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-start gap-2 rounded-lg border px-3.5 py-3 text-sm shadow-lg"
          :class="styles[toast.type]"
          role="alert"
        >
          <component :is="icons[toast.type]" class="mt-0.5 h-4 w-4 shrink-0" :class="iconStyles[toast.type]" />
          <p class="flex-1">{{ toast.message }}</p>
          <button
            type="button"
            class="shrink-0 rounded p-0.5 text-current opacity-60 hover:opacity-100"
            aria-label="Dismiss notification"
            @click="toastStore.dismiss(toast.id)"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
