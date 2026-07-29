<script setup lang="ts" generic="T">
import { computed } from 'vue'

interface SelectOption<T> {
  value: T
  label: string
  disabled?: boolean
}

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: T
    id?: string
    label?: string
    error?: string
    required?: boolean
    placeholder?: string
    options?: SelectOption<T>[]
    size?: 'md' | 'sm'
  }>(),
  {
    id: undefined,
    label: undefined,
    error: undefined,
    required: false,
    placeholder: undefined,
    options: undefined,
    size: 'md',
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: T] }>()

const value = computed({
  get: () => props.modelValue,
  set: (v: T) => emit('update:modelValue', v),
})
</script>

<template>
  <div class="flex flex-col" :class="size === 'sm' ? 'gap-1' : 'gap-1.5'">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-brand-600">*</span>
    </label>
    <select
      :id="id"
      v-model="value"
      v-bind="$attrs"
      class="rounded-lg border border-slate-300 text-sm text-slate-900 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
      :class="[
        error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : '',
        size === 'sm' ? 'px-2.5 py-1.5' : 'px-3 py-2',
      ]"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error && id ? `${id}-error` : undefined"
    >
      <template v-if="options">
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option v-for="opt in options" :key="String(opt.value)" :value="opt.value" :disabled="opt.disabled">
          {{ opt.label }}
        </option>
      </template>
      <slot v-else />
    </select>
    <p v-if="error" :id="id ? `${id}-error` : undefined" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>
