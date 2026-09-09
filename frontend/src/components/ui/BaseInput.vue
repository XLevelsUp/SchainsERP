<script setup lang="ts">
import type { Component } from 'vue'
import { computed, useAttrs } from 'vue'
import { X } from 'lucide-vue-next'

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
    // Opt-in inline "x" that resets the value to ''. Meant for filter
    // fields — date ranges, as-of dates — where "no value" is a real
    // state the operator has to be able to get back to, and where the
    // native date input gives no consistent way to do it (Chrome hides
    // its own clear affordance, Firefox shows one, neither is reachable
    // in a compact filter row). Entry fields (added_at, due_date, …)
    // deliberately leave this off: an empty value there is a validation
    // problem, not a filter reset. Renders the same glyph/hover styling
    // as BaseSelect's clear button so a filter row reads consistently.
    clearable?: boolean
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
    clearable: false,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const attrs = useAttrs()

const effectiveStep = computed(() => props.step ?? (props.type === 'number' ? '0.001' : undefined))

// disabled/readonly have no props here — they ride in through $attrs, where
// a bare `disabled` arrives as "" (falsy), so test for presence, not truth.
function isAttrSet(value: unknown) {
  return value !== undefined && value !== null && value !== false
}

const showClear = computed(
  () =>
    props.clearable &&
    props.modelValue !== '' &&
    !isAttrSet(attrs.disabled) &&
    !isAttrSet(attrs.readonly),
)
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
          showClear ? (size === 'sm' ? 'pr-7' : 'pr-9') : '',
        ]"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error && id ? `${id}-error` : undefined"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="showClear"
        type="button"
        :aria-label="label ? `Clear ${label}` : 'Clear'"
        class="absolute top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        :class="size === 'sm' ? 'right-1.5' : 'right-2'"
        @click="emit('update:modelValue', '')"
      >
        <X class="h-3.5 w-3.5" />
      </button>
    </div>
    <p v-if="error" :id="id ? `${id}-error` : undefined" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>
