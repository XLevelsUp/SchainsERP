<script setup lang="ts">
withDefaults(defineProps<{ padded?: boolean }>(), { padded: true })
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white" :class="padded ? 'p-6' : ''">
    <slot />
  </div>
</template>
