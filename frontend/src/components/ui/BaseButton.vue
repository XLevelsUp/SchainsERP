<script setup lang="ts">
import type { Component } from 'vue'

/*
| `ink`, `outflow`/`inflow` and their `-soft` forms are the ledger variants
| (see main.css): ink is the page's one primary action, outflow/inflow are
| direction-coded transaction actions — filled for the main action of a
| direction, soft for the rest. They focus with the gold ring. The original
| three variants are unchanged.
*/
withDefaults(
  defineProps<{
    variant?:
      | 'primary'
      | 'secondary'
      | 'ghost'
      | 'ink'
      | 'outflow'
      | 'outflow-soft'
      | 'inflow'
      | 'inflow-soft'
    type?: 'button' | 'submit' | 'reset'
    icon?: Component
    disabled?: boolean
  }>(),
  {
    variant: 'primary',
    type: 'button',
    icon: undefined,
    disabled: false,
  },
)

const variantClasses: Record<string, string> = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600',
  secondary:
    'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus-visible:outline-brand-600',
  ghost: 'text-slate-600 hover:bg-slate-100 focus-visible:outline-brand-600',
  ink: 'bg-ink-900 text-white hover:bg-ink-700 focus-visible:outline-gold-500',
  outflow: 'bg-outflow-600 text-white hover:bg-outflow-700 focus-visible:outline-gold-500',
  'outflow-soft':
    'bg-white text-outflow-700 border border-outflow-200 hover:bg-outflow-50 focus-visible:outline-gold-500',
  inflow: 'bg-inflow-600 text-white hover:bg-inflow-700 focus-visible:outline-gold-500',
  'inflow-soft':
    'bg-white text-inflow-700 border border-inflow-200 hover:bg-inflow-50 focus-visible:outline-gold-500',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    :class="variantClasses[variant]"
  >
    <component :is="icon" v-if="icon" class="h-4 w-4" aria-hidden="true" />
    <slot />
  </button>
</template>
