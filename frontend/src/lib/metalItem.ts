import { ref } from 'vue'
import { systemSettingsApi } from './systemSettingsApi'
import type { Item } from '@/types'

// Which items open MetalPickerModal on the Stock Out/In, GMS Out/In, Item
// Change and Item Conversion rows. The backend decides this with the
// `metal_popup_items` system setting (StockDetailsController::getAvailableMetals
// rejects any other item with a 400), so the screens read the same list.
// Seeded since 2026-11-12 with the item named "Metal".
//
// The list is fetched once and cached. It's reactive, so templates calling
// isMetalItem() re-render when it arrives. Until it has loaded, or if it
// can't be loaded, the old rule applies: the item named exactly "Metal".

const metalItemIds = ref<number[] | null>(null)
let loading: Promise<void> | null = null

function parseIds(value: unknown): number[] | null {
  if (!Array.isArray(value)) return null
  const ids = value.map((v) => Number(v)).filter((n) => Number.isInteger(n))
  return ids
}

export function loadMetalItemIds(): Promise<void> {
  if (!loading) {
    loading = systemSettingsApi
      .get('metal_popup_items')
      .then((setting) => {
        metalItemIds.value = parseIds(setting.setting_value)
      })
      .catch(() => {
        // Missing setting or no access: keep the name-based fallback.
        metalItemIds.value = null
      })
  }
  return loading
}

// Call after the setting is edited so the next check re-fetches it.
export function forgetMetalItemIds(): void {
  loading = null
  metalItemIds.value = null
}

export function isMetalItem(item: Item | undefined | null): boolean {
  if (!item) return false
  void loadMetalItemIds()
  const ids = metalItemIds.value
  if (ids !== null) return ids.includes(item.item_id)
  return item.item_name.trim().toLowerCase() === 'metal'
}
