<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    id?: string
    label?: string
    type?: string
    placeholder?: string
    error?: string
    required?: boolean
    autocomplete?: string
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
  },
)

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-brand-600">*</span>
    </label>
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :autocomplete="autocomplete"
      class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
      :class="error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : ''"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error && id ? `${id}-error` : undefined"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="error" :id="id ? `${id}-error` : undefined" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>
