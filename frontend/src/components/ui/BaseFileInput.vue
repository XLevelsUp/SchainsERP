<script setup lang="ts">
import { Upload, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: File[]
    label?: string
    id?: string
    hint?: string
    accept?: string
  }>(),
  {
    label: undefined,
    id: undefined,
    hint: undefined,
    accept: 'image/jpeg,image/png,image/webp',
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: File[]] }>()

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const files = input.files ? Array.from(input.files) : []
  if (files.length) emit('update:modelValue', [...props.modelValue, ...files])
  input.value = ''
}

function removeFile(index: number) {
  emit(
    'update:modelValue',
    props.modelValue.filter((_, i) => i !== index),
  )
}

function formatSize(bytes: number) {
  return bytes < 1024 * 1024
    ? `${Math.round(bytes / 1024)} KB`
    : `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">{{ label }}</label>

    <label
      :for="id"
      class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500 transition-colors hover:border-brand-400 hover:text-brand-600"
    >
      <Upload class="h-4 w-4" />
      Add receipt images
      <input
        :id="id"
        type="file"
        :accept="accept"
        multiple
        class="hidden"
        @change="onFileChange"
      />
    </label>
    <p v-if="hint" class="text-xs text-slate-500">{{ hint }}</p>

    <ul v-if="modelValue.length" class="flex flex-col gap-1">
      <li
        v-for="(file, index) in modelValue"
        :key="`${file.name}-${index}`"
        class="flex items-center justify-between rounded-md bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600"
      >
        <span class="truncate">{{ file.name }} ({{ formatSize(file.size) }})</span>
        <button
          type="button"
          class="ml-2 shrink-0 rounded p-0.5 text-slate-400 hover:text-red-600"
          aria-label="Remove image"
          @click="removeFile(index)"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </li>
    </ul>
  </div>
</template>
