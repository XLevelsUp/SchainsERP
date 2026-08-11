<script setup lang="ts" generic="T">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Check, ChevronDown, Search, X } from 'lucide-vue-next'

interface SelectOption<T> {
  value: T
  label: string
  disabled?: boolean
}

// Deliberately NOT inheritAttrs:false — class/style (e.g. the "flex-1" a
// couple of callers use to stretch the select in a flex row) should land
// on the root wrapper, not get buried on the inner button where a
// flex-* class would be inert (the button's own parent isn't a flex
// container).

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
    disabled?: boolean
  }>(),
  {
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

const emit = defineEmits<{ 'update:modelValue': [value: T] }>()

// Master searchable-dropdown control — every picker in the app (users,
// items, roles, banks, categories…) goes through this one component, so
// the search-box + clear-button UX matches the legacy app everywhere at
// once rather than being rebuilt screen by screen.

const isOpen = ref(false)
const isPositioned = ref(false)
const searchQuery = ref('')
const highlightedIndex = ref(0)
const triggerRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const position = ref({ top: 0, left: 0, width: 0 })

const selectedOption = computed(() => props.options.find((o) => o.value === props.modelValue))

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
  highlightedIndex.value = Math.max(
    0,
    filteredOptions.value.findIndex((o) => o.value === props.modelValue),
  )
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

function selectOption(option: SelectOption<T>) {
  if (option.disabled) return
  emit('update:modelValue', option.value)
  close()
}

function clearValue(event: Event) {
  event.stopPropagation()
  emit('update:modelValue', null as T)
}

watch(searchQuery, () => {
  highlightedIndex.value = 0
})

function moveHighlight(delta: number) {
  const count = filteredOptions.value.length
  if (count === 0) return
  highlightedIndex.value = (highlightedIndex.value + delta + count) % count
}

function selectHighlighted() {
  const option = filteredOptions.value[highlightedIndex.value]
  if (option) selectOption(option)
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
    selectHighlighted()
  } else if (event.key === 'Escape') {
    event.preventDefault()
    close()
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
        <span class="truncate" :class="selectedOption ? '' : 'text-slate-400'">
          {{ selectedOption?.label ?? placeholder ?? 'Select…' }}
        </span>
        <span class="flex shrink-0 items-center gap-1">
          <span
            v-if="selectedOption && !disabled"
            role="button"
            aria-label="Clear selection"
            class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            @click="clearValue"
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
            :aria-selected="option.value === modelValue"
            class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm"
            :class="[
              option.disabled
                ? 'cursor-not-allowed text-slate-300'
                : index === highlightedIndex
                  ? 'bg-brand-50 text-brand-700'
                  : 'text-slate-700 hover:bg-slate-50',
            ]"
            @click="selectOption(option)"
            @mouseenter="highlightedIndex = index"
          >
            <span class="truncate">{{ option.label }}</span>
            <Check v-if="option.value === modelValue" class="h-4 w-4 shrink-0" />
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
