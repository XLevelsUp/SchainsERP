<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string
    id?: string
    label?: string
    type?: string
    placeholder?: string
    error?: string
    required?: boolean
    autocomplete?: string
    icon?: Component
    size?: 'md' | 'sm'
    // Every number field project-wide defaults to 3-decimal precision
    // unless a caller opts into something else (e.g. step="1" for a
    // genuinely integer field like an ID or a piece count).
    step?: string
  }>(),
  {
    modelValue: '',
    id: undefined,
    label: undefined,
    type: 'text',
    placeholder: '',
    error: undefined,
    required: false,
    autocomplete: undefined,
    icon: undefined,
    size: 'md',
    step: undefined,
  },
)

const effectiveStep = computed(() => props.step ?? (props.type === 'number' ? '0.001' : undefined))

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div class="flex flex-col" :class="size === 'sm' ? 'gap-1' : 'gap-1.5'">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-brand-600">*</span>
    </label>
    <div class="relative">
      <component
        :is="icon"
        v-if="icon"
        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
        aria-hidden="true"
      />
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :autocomplete="autocomplete"
        :step="effectiveStep"
        v-bind="$attrs"
        class="w-full rounded-lg border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
        :class="[
          error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : '',
          size === 'sm' ? 'px-2.5 py-1.5' : 'px-3 py-2',
          icon ? (size === 'sm' ? 'pl-8' : 'pl-9') : '',
        ]"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error && id ? `${id}-error` : undefined"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
    </div>
    <p v-if="error" :id="id ? `${id}-error` : undefined" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>
