<script setup lang="ts" generic="T">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Check, ChevronDown, Search, X } from 'lucide-vue-next'

interface SelectOption<T> {
  value: T
  label: string
  disabled?: boolean
}

// Multi-value sibling of BaseSelect — same trigger, teleported panel,
// search box and keyboard handling, but v-model is a T[] and picking an
// option toggles it instead of closing the panel. Kept as its own component
// rather than a `multiple` flag on BaseSelect because widening BaseSelect's
// modelValue to T | T[] would break v-model typing for every existing
// single-value caller.

const props = withDefaults(
  defineProps<{
    modelValue?: T[]
    id?: string
    label?: string
    error?: string
    required?: boolean
    placeholder?: string
    options?: SelectOption<T>[]
    size?: 'md' | 'sm'
    disabled?: boolean
  }>(),
  {
    modelValue: () => [],
    id: undefined,
    label: undefined,
    error: undefined,
    required: false,
    placeholder: undefined,
    options: () => [],
    size: 'md',
    disabled: false,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: T[]] }>()

const isOpen = ref(false)
const isPositioned = ref(false)
const searchQuery = ref('')
const highlightedIndex = ref(0)
const triggerRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const position = ref({ top: 0, left: 0, width: 0 })

function isSelected(value: T): boolean {
  return props.modelValue.includes(value)
}

// Keeps model order (the order they were picked in), not option order, so
// chips don't jump around as more are added.
const selectedOptions = computed(() =>
  props.modelValue
    .map((value) => props.options.find((o) => o.value === value))
    .filter((o): o is SelectOption<T> => o !== undefined),
)

const filteredOptions = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return props.options
  return props.options.filter((o) => o.label.toLowerCase().includes(query))
})

async function updatePosition() {
  const trigger = triggerRef.value
  const panel = panelRef.value
  if (!trigger || !panel) return

  const triggerRect = trigger.getBoundingClientRect()
  const panelRect = panel.getBoundingClientRect()
  const gap = 4
  const spaceBelow = window.innerHeight - triggerRect.bottom
  const openUpward = spaceBelow < panelRect.height + gap && triggerRect.top > panelRect.height + gap

  position.value = {
    top: openUpward ? triggerRect.top - panelRect.height - gap : triggerRect.bottom + gap,
    left: triggerRect.left,
    width: triggerRect.width,
  }
  isPositioned.value = true
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as Node
  if (panelRef.value?.contains(target) || triggerRef.value?.contains(target)) return
  close()
}

function addListeners() {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('scroll', updatePosition, true)
  window.addEventListener('resize', updatePosition)
}
function removeListeners() {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('scroll', updatePosition, true)
  window.removeEventListener('resize', updatePosition)
}

async function open() {
  if (isOpen.value || props.disabled) return
  isOpen.value = true
  isPositioned.value = false
  searchQuery.value = ''
  highlightedIndex.value = 0
  await nextTick()
  await updatePosition()
  searchInputRef.value?.focus()
  addListeners()
}

function close() {
  if (!isOpen.value) return
  isOpen.value = false
  isPositioned.value = false
  removeListeners()
  triggerRef.value?.querySelector('button')?.focus()
}

function toggle() {
  if (isOpen.value) close()
  else open()
}

function emitValue(next: T[]) {
  emit('update:modelValue', next)
  // Chips wrap onto new lines as they're added/removed, which moves the
  // trigger's bottom edge — re-anchor the open panel to it.
  if (isOpen.value) nextTick(updatePosition)
}

function toggleOption(option: SelectOption<T>) {
  if (option.disabled) return
  emitValue(
    isSelected(option.value)
      ? props.modelValue.filter((v) => v !== option.value)
      : [...props.modelValue, option.value],
  )
}

function removeValue(event: Event, value: T) {
  event.stopPropagation()
  emitValue(props.modelValue.filter((v) => v !== value))
}

function clearAll(event: Event) {
  event.stopPropagation()
  emitValue([])
}

watch(searchQuery, () => {
  highlightedIndex.value = 0
})

function moveHighlight(delta: number) {
  const count = filteredOptions.value.length
  if (count === 0) return
  highlightedIndex.value = (highlightedIndex.value + delta + count) % count
}

function toggleHighlighted() {
  const option = filteredOptions.value[highlightedIndex.value]
  if (option) toggleOption(option)
}

function handleSearchKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    moveHighlight(1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    moveHighlight(-1)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    toggleHighlighted()
  } else if (event.key === 'Escape') {
    event.preventDefault()
    close()
  } else if (event.key === 'Backspace' && !searchQuery.value && props.modelValue.length) {
    emitValue(props.modelValue.slice(0, -1))
  }
}

onBeforeUnmount(removeListeners)
</script>

<template>
  <div class="flex flex-col" :class="size === 'sm' ? 'gap-1' : 'gap-1.5'">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-brand-600">*</span>
    </label>

    <div ref="triggerRef" class="relative">
      <button
        :id="id"
        type="button"
        class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white text-left text-sm text-slate-900 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
        :class="[
          error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : '',
          size === 'sm' ? 'px-2.5 py-1.5' : 'px-3 py-2',
          disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'cursor-pointer',
        ]"
        :disabled="disabled"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error && id ? `${id}-error` : undefined"
        aria-haspopup="listbox"
        :aria-expanded="isOpen"
        @click="toggle"
      >
        <span v-if="selectedOptions.length" class="flex min-w-0 flex-wrap gap-1">
          <span
            v-for="option in selectedOptions"
            :key="String(option.value)"
            class="inline-flex max-w-full items-center gap-1 rounded-md bg-slate-100 py-0.5 pr-1 pl-2 text-xs text-slate-700"
            :title="option.label"
          >
            <span class="truncate">{{ option.label }}</span>
            <span
              v-if="!disabled"
              role="button"
              :aria-label="`Remove ${option.label}`"
              class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
              @click="removeValue($event, option.value)"
            >
              <X class="h-3 w-3" />
            </span>
          </span>
        </span>
        <span v-else class="truncate text-slate-400">{{ placeholder ?? 'Select…' }}</span>

        <span class="flex shrink-0 items-center gap-1">
          <span
            v-if="selectedOptions.length && !disabled"
            role="button"
            aria-label="Clear all"
            class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            @click="clearAll"
          >
            <X class="h-3.5 w-3.5" />
          </span>
          <ChevronDown class="h-4 w-4 text-slate-400" :class="isOpen ? 'rotate-180' : ''" />
        </span>
      </button>
    </div>

    <p v-if="error" :id="id ? `${id}-error` : undefined" class="text-sm text-red-600">{{ error }}</p>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="panelRef"
        role="listbox"
        aria-multiselectable="true"
        class="fixed z-50 flex max-h-72 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
          width: `${position.width}px`,
          visibility: isPositioned ? 'visible' : 'hidden',
        }"
      >
        <div class="relative shrink-0 border-b border-slate-100 p-2">
          <Search class="pointer-events-none absolute top-1/2 left-4.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            placeholder="Search…"
            class="w-full rounded-md border border-slate-200 py-1.5 pr-2 pl-7 text-sm text-slate-900 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600/20"
            @keydown="handleSearchKeydown"
          />
        </div>

        <ul class="overflow-y-auto py-1">
          <li v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-slate-400">
            No matches.
          </li>
          <li
            v-for="(option, index) in filteredOptions"
            :key="String(option.value)"
            role="option"
            :aria-selected="isSelected(option.value)"
            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm"
            :class="[
              option.disabled
                ? 'cursor-not-allowed text-slate-300'
                : index === highlightedIndex
                  ? 'bg-brand-50 text-brand-700'
                  : 'text-slate-700 hover:bg-slate-50',
            ]"
            @click="toggleOption(option)"
            @mouseenter="highlightedIndex = index"
          >
            <span
              class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
              :class="
                isSelected(option.value)
                  ? 'border-brand-600 bg-brand-600 text-white'
                  : 'border-slate-300 bg-white'
              "
            >
              <Check v-if="isSelected(option.value)" class="h-3 w-3" />
            </span>
            <span class="truncate">{{ option.label }}</span>
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
