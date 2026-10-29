<script setup lang="ts">
import { X } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'

/*
|--------------------------------------------------------------------------
| Print preview shell — shared by every print button in the app
|--------------------------------------------------------------------------
| Each print button opens one of these with its own content: the caller
| fetches from the one endpoint that owns that print, passes the loading /
| error state in, and renders the slip into the default slot. What is shown
| here is exactly what reaches the printer — the header, loading/error
| states and buttons are all print:hidden.
|
| Deliberately not BaseModal: BaseModal's overlay is print:hidden (correct
| for every other dialog), which would hide the very content this one exists
| to print.
|
| Print isolation is keyed off this overlay being in the DOM, not off a body
| class toggled around window.print(), so Ctrl+P while a preview is open
| prints the preview too. `display: none` on everything else (rather than
| the older `visibility: hidden` trick) means the hidden app takes up no
| space, so a long report paginates from the top of page one instead of
| repeating a fixed-position box on every sheet.
|--------------------------------------------------------------------------
*/

withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    isLoading?: boolean
    error?: string
    ready?: boolean
    maxWidth?: string
  }>(),
  {
    subtitle: undefined,
    isLoading: false,
    error: '',
    ready: false,
    maxWidth: 'max-w-sm',
  },
)

const emit = defineEmits<{ close: [] }>()

function handlePrint() {
  window.print()
}
</script>

<template>
  <Teleport to="body">
    <div
      class="print-preview-overlay fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-8"
      @click.self="emit('close')"
    >
      <div
        class="print-preview-panel w-full rounded-lg bg-white shadow-xl"
        :class="maxWidth"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 print:hidden">
          <div class="min-w-0">
            <h2 class="text-sm font-semibold text-slate-900">{{ title }}</h2>
            <p v-if="subtitle" class="truncate text-xs text-slate-500">{{ subtitle }}</p>
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

        <div v-if="isLoading" class="px-4 py-10 text-center text-sm text-slate-500 print:hidden">
          Loading…
        </div>
        <p v-else-if="error" class="px-4 py-4 text-sm text-red-700 print:hidden">{{ error }}</p>
        <div v-else-if="ready" class="overflow-x-auto px-4 py-4 print:overflow-visible print:p-0">
          <slot />
        </div>

        <div class="flex items-center gap-3 border-t border-slate-200 px-4 py-3 print:hidden">
          <BaseButton type="button" :disabled="!ready || isLoading" @click="handlePrint">Print</BaseButton>
          <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<!--
  Not scoped: it has to reach the app root, a sibling of the teleported
  overlay. Unlayered, so it outranks Tailwind's layered `fixed` / `flex` /
  `bg-*` utilities on the overlay without !important.
-->
<style>
@media print {
  body:has(> .print-preview-overlay) > :not(.print-preview-overlay) {
    display: none;
  }

  .print-preview-overlay {
    position: static;
    display: block;
    overflow: visible;
    padding: 0;
    background: none;
  }

  .print-preview-panel {
    max-width: none;
    border-radius: 0;
    box-shadow: none;
  }
}
</style>
