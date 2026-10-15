<script setup lang="ts">
import { computed } from 'vue'
import { ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next'

/*
| The OUT/IN stamp used wherever a stock or cash movement's direction is
| shown — column headings, entry-panel headers, history badges. One
| component so the arrow, colour and lettering can't drift between the
| Stock and Cash Management screens.
|
| `plain` is a heading (coloured lettering only); `pill` is a table badge.
| The default label is the bare direction; pass slot text for anything
| more specific ("Pay", "Receive").
*/
const props = withDefaults(
  defineProps<{ direction: 'out' | 'in'; variant?: 'plain' | 'pill' }>(),
  { variant: 'plain' },
)

const icon = computed(() => (props.direction === 'out' ? ArrowUpRight : ArrowDownLeft))
</script>

<template>
  <span
    class="inline-flex items-center gap-1 font-figure font-semibold tracking-[0.08em] whitespace-nowrap uppercase"
    :class="[
      variant === 'pill' ? 'rounded px-1.5 py-0.5 text-[11px] leading-4' : 'text-xs',
      direction === 'out'
        ? variant === 'pill'
          ? 'bg-outflow-50 text-outflow-700'
          : 'text-outflow-700'
        : variant === 'pill'
          ? 'bg-inflow-50 text-inflow-700'
          : 'text-inflow-700',
    ]"
  >
    <component :is="icon" :class="variant === 'pill' ? 'h-3 w-3' : 'h-3.5 w-3.5'" aria-hidden="true" />
    <slot>{{ direction === 'out' ? 'Out' : 'In' }}</slot>
  </span>
</template>
