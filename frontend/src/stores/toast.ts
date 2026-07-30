import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  type: ToastType
  message: string
}

let nextId = 0

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function dismiss(id: number) {
    const index = toasts.value.findIndex((t) => t.id === id)
    if (index !== -1) toasts.value.splice(index, 1)
  }

  function show(message: string, type: ToastType = 'info', duration = 5000) {
    const id = nextId++
    toasts.value.push({ id, type, message })
    if (duration > 0) setTimeout(() => dismiss(id), duration)
    return id
  }

  return { toasts, show, dismiss }
})
