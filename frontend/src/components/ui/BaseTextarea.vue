<script setup lang="ts">
defineOptions({ inheritAttrs: false })

withDefaults(
  defineProps<{
    modelValue?: string
    id?: string
    label?: string
    placeholder?: string
    error?: string
    required?: boolean
    rows?: number
    size?: 'md' | 'sm'
  }>(),
  {
    modelValue: '',
    id: undefined,
    label: undefined,
    placeholder: '',
    error: undefined,
    required: false,
    rows: 3,
    size: 'md',
  },
)

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div class="flex flex-col" :class="size === 'sm' ? 'gap-1' : 'gap-1.5'">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-brand-600">*</span>
    </label>
    <textarea
      :id="id"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :rows="rows"
      v-bind="$attrs"
      class="w-full resize-y rounded-lg border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
      :class="[
        error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : '',
        size === 'sm' ? 'px-2.5 py-1.5' : 'px-3 py-2',
      ]"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error && id ? `${id}-error` : undefined"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <p v-if="error" :id="id ? `${id}-error` : undefined" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>
