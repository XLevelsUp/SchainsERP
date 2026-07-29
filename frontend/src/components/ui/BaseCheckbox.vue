<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: boolean
    id?: string
    label?: string
    disabled?: boolean
  }>(),
  {
    modelValue: false,
    id: undefined,
    label: undefined,
    disabled: false,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const value = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})
</script>

<template>
  <label
    class="flex items-center gap-2 text-sm text-slate-700"
    :class="disabled ? 'opacity-60' : ''"
  >
    <input
      :id="id"
      v-model="value"
      type="checkbox"
      :disabled="disabled"
      v-bind="$attrs"
      class="rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-600/20"
    />
    <slot>{{ label }}</slot>
  </label>
</template>
