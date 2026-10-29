import { ref } from 'vue'
import { ApiError } from './api'
import { useToastStore } from '@/stores/toast'

// Shared by BaseSelect and BaseMultiSelect. The small reload button inside
// the box re-fetches only that dropdown's master list: the caller passes the
// fetch (which writes into its own options ref), and this owns the spinner,
// the double-click guard and the failure toast — so a failed reload never
// touches the page's own loading/error state, form, or current selection.

// Held for a moment so a fast response still reads as "reloaded" instead of
// a flicker the operator can't see.
const MIN_SPIN_MS = 400

export function useOptionsReload(getReload: () => (() => unknown) | undefined) {
  const toast = useToastStore()
  const isReloading = ref(false)

  async function reloadOptions(event: Event) {
    // Inside the trigger button: without this the click also opens the panel.
    event.stopPropagation()
    const reload = getReload()
    if (!reload || isReloading.value) return
    isReloading.value = true
    try {
      await Promise.all([reload(), new Promise((resolve) => setTimeout(resolve, MIN_SPIN_MS))])
    } catch (err) {
      toast.show(err instanceof ApiError ? err.message : 'Could not reload this list.', 'error')
    } finally {
      isReloading.value = false
    }
  }

  return { isReloading, reloadOptions }
}
