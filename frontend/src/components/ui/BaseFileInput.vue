<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { Upload, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: File[]
    label?: string
    id?: string
    hint?: string
    accept?: string
    // Single-line variant: an inline "Add images" control plus small square
    // thumbnails instead of the tall dashed dropzone + stacked file list.
    // Opt-in so existing consumers keep their current footprint.
    compact?: boolean
  }>(),
  {
    label: undefined,
    id: undefined,
    hint: undefined,
    accept: 'image/jpeg,image/png,image/webp',
    compact: false,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: File[]] }>()

const isDragging = ref(false)

// Object URLs are cached per File instance (identity survives the
// spread/filter in addFiles/removeFile below) so re-renders don't leak new
// blob URLs — each one is revoked explicitly on removal and on unmount.
const previewUrls = new Map<File, string>()

function isImage(file: File) {
  return file.type.startsWith('image/')
}

function previewUrl(file: File): string | null {
  if (!isImage(file)) return null
  let url = previewUrls.get(file)
  if (!url) {
    url = URL.createObjectURL(file)
    previewUrls.set(file, url)
  }
  return url
}

function revokePreview(file: File) {
  const url = previewUrls.get(file)
  if (url) {
    URL.revokeObjectURL(url)
    previewUrls.delete(file)
  }
}

onBeforeUnmount(() => {
  previewUrls.forEach((url) => URL.revokeObjectURL(url))
  previewUrls.clear()
})

// Native pickers restrict by `accept` themselves, but a browser's "All
// Files" option and drag-and-drop both bypass it — filter explicitly so
// dropped files honor the same JPG/PNG/WEBP restriction as a normal pick.
function isAccepted(file: File): boolean {
  const types = props.accept
    .split(',')
    .map((t) => t.trim())
    .filter(Boolean)
  if (types.length === 0) return true
  return types.some((t) => (t.endsWith('/*') ? file.type.startsWith(t.slice(0, -1)) : file.type === t))
}

function addFiles(fileList: FileList | File[]) {
  const files = Array.from(fileList).filter(isAccepted)
  if (files.length) emit('update:modelValue', [...props.modelValue, ...files])
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) addFiles(input.files)
  input.value = ''
}

function onDrop(event: DragEvent) {
  isDragging.value = false
  if (event.dataTransfer?.files) addFiles(event.dataTransfer.files)
}

function removeFile(index: number) {
  revokePreview(props.modelValue[index])
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
  <div v-if="compact" class="flex flex-col gap-1">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">{{ label }}</label>
    <div class="flex flex-wrap items-center gap-2">
      <label
        :for="id"
        class="inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-dashed px-2.5 text-xs text-slate-500 transition-colors hover:border-brand-400 hover:text-brand-600"
        :class="isDragging ? 'border-brand-500 bg-brand-50 text-brand-600' : 'border-slate-300'"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
      >
        <Upload class="h-3.5 w-3.5" />
        {{ isDragging ? 'Drop here' : 'Add images' }}
        <input :id="id" type="file" :accept="accept" multiple class="hidden" @change="onFileChange" />
      </label>

      <div v-for="(file, index) in modelValue" :key="`${file.name}-${index}`" class="group relative h-9 w-9 shrink-0">
        <img
          v-if="previewUrl(file)"
          :src="previewUrl(file)!"
          :alt="file.name"
          class="h-9 w-9 rounded-md border border-slate-200 object-cover"
        />
        <div
          v-else
          :title="file.name"
          class="flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-[9px] font-medium text-slate-500"
        >
          FILE
        </div>
        <button
          type="button"
          class="absolute -top-1.5 -right-1.5 rounded-full border border-slate-200 bg-white p-0.5 text-slate-400 opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:text-red-600"
          :aria-label="`Remove ${file.name}`"
          @click="removeFile(index)"
        >
          <X class="h-2.5 w-2.5" />
        </button>
      </div>
    </div>
    <p v-if="hint" class="text-[11px] text-slate-500">{{ hint }}</p>
  </div>

  <div v-else class="flex flex-col gap-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">{{ label }}</label>

    <label
      :for="id"
      class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed px-3 py-4 text-sm text-slate-500 transition-colors hover:border-brand-400 hover:text-brand-600"
      :class="isDragging ? 'border-brand-500 bg-brand-50 text-brand-600' : 'border-slate-300'"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
    >
      <Upload class="h-4 w-4" />
      {{ isDragging ? 'Drop to add' : 'Add receipt images, or drag and drop' }}
      <input :id="id" type="file" :accept="accept" multiple class="hidden" @change="onFileChange" />
    </label>
    <p v-if="hint" class="text-xs text-slate-500">{{ hint }}</p>

    <ul v-if="modelValue.length" class="flex flex-col gap-1">
      <li
        v-for="(file, index) in modelValue"
        :key="`${file.name}-${index}`"
        class="flex items-center justify-between rounded-md bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600"
      >
        <span class="flex min-w-0 items-center gap-2">
          <img
            v-if="previewUrl(file)"
            :src="previewUrl(file)!"
            :alt="file.name"
            class="h-6 w-6 shrink-0 rounded border border-slate-200 object-cover"
          />
          <span class="truncate">{{ file.name }} ({{ formatSize(file.size) }})</span>
        </span>
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
