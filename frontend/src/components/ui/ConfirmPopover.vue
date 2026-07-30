<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue'

withDefaults(
  defineProps<{
    message: string
    confirmLabel?: string
    cancelLabel?: string
  }>(),
  {
    confirmLabel: 'Delete',
    cancelLabel: 'Cancel',
  },
)

const emit = defineEmits<{ confirm: [] }>()

const isOpen = ref(false)
const isPositioned = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const position = ref({ top: 0, left: 0 })

async function updatePosition() {
  const trigger = triggerRef.value
  const panel = panelRef.value
  if (!trigger || !panel) return

  const triggerRect = trigger.getBoundingClientRect()
  const panelRect = panel.getBoundingClientRect()
  const gap = 6
  const spaceBelow = window.innerHeight - triggerRect.bottom
  const openUpward = spaceBelow < panelRect.height + gap && triggerRect.top > panelRect.height + gap

  position.value = {
    top: openUpward ? triggerRect.top - panelRect.height - gap : triggerRect.bottom + gap,
    left: Math.max(8, triggerRect.right - panelRect.width),
  }
  isPositioned.value = true
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as Node
  if (panelRef.value?.contains(target) || triggerRef.value?.contains(target)) return
  close()
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

function addListeners() {
  document.addEventListener('mousedown', handleClickOutside)
  document.addEventListener('keydown', handleKeydown)
  window.addEventListener('scroll', close, true)
  window.addEventListener('resize', close)
}

function removeListeners() {
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('scroll', close, true)
  window.removeEventListener('resize', close)
}

async function open() {
  if (isOpen.value) return
  isOpen.value = true
  isPositioned.value = false
  await nextTick()
  await updatePosition()
  addListeners()
}

function close() {
  if (!isOpen.value) return
  isOpen.value = false
  isPositioned.value = false
  removeListeners()
}

function toggle() {
  if (isOpen.value) close()
  else open()
}

function handleConfirm() {
  close()
  emit('confirm')
}

onBeforeUnmount(removeListeners)
</script>

<template>
  <span ref="triggerRef" class="inline-flex">
    <slot :toggle="toggle" :open="open" :is-open="isOpen" />
  </span>

  <Teleport to="body">
    <div
      v-if="isOpen"
      ref="panelRef"
      class="fixed z-50 w-56 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
      :style="{
        top: `${position.top}px`,
        left: `${position.left}px`,
        visibility: isPositioned ? 'visible' : 'hidden',
      }"
    >
      <p class="mb-3 text-sm text-slate-700">{{ message }}</p>
      <div class="flex items-center justify-end gap-2">
        <button
          type="button"
          class="rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"
          @click="close"
        >
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          class="rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700"
          @click="handleConfirm"
        >
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </Teleport>
</template>
