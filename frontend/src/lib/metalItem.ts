import type { Item } from '@/types'

// Matches StockDetailsController::getAvailableMetals' own check
// (strtolower($item->item_name) !== 'metal') — used to decide whether
// selecting an item in a Stock Out/In or GMS Out/In row should open
// MetalPickerModal.
export function isMetalItem(item: Item | undefined | null): boolean {
  return item ? item.item_name.trim().toLowerCase() === 'metal' : false
}
