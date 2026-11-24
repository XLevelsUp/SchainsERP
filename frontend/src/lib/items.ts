import type { Item } from '@/types'

// Helpers for the admin console's Items page (2026-11-24).

export type YesNoAll = 'all' | 'yes' | 'no'

export interface ItemFilters {
  name: string
  fitem: YesNoAll
  active: YesNoAll
  orderNo: string
}

/** Items sorted the way every dropdown shows them: order number, then name. */
export function sortByOrder<T extends Pick<Item, 'order_no' | 'item_name'>>(items: T[]): T[] {
  return [...items].sort(
    (a, b) => (a.order_no ?? Number.MAX_SAFE_INTEGER) - (b.order_no ?? Number.MAX_SAFE_INTEGER) || a.item_name.localeCompare(b.item_name),
  )
}

/** The legacy column filters: name contains, Yes/No/All flags, order number starts with. */
export function filterItems(items: Item[], filters: ItemFilters): Item[] {
  const name = filters.name.trim().toLowerCase()
  const orderNo = filters.orderNo.trim()
  const flag = (value: boolean, filter: YesNoAll) => filter === 'all' || value === (filter === 'yes')
  return items.filter(
    (i) =>
      (!name || i.item_name.toLowerCase().includes(name)) &&
      flag(i.is_need_fitem_shown, filters.fitem) &&
      flag(i.is_active, filters.active) &&
      (!orderNo || String(i.order_no ?? '').startsWith(orderNo)),
  )
}

/** The next free order number, for a new item's form (the backend checks it). */
export function nextOrderNo(items: Pick<Item, 'order_no'>[]): number {
  return items.reduce((max, i) => Math.max(max, i.order_no ?? 0), 0) + 1
}

function csvCell(value: string): string {
  return /[",\n\r]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value
}

/** The export: the listed items in the order shown, the legacy columns. */
export function itemsCsv(items: Item[]): string {
  const header = ['Orders No', 'Item Name', 'Item Touch', 'Is Need Fitem Shown', 'Is Active']
  const rows = items.map((i) => [
    String(i.order_no ?? ''),
    i.item_name,
    String(i.item_touch ?? 0),
    i.is_need_fitem_shown ? 'Yes' : 'No',
    i.is_active ? 'Yes' : 'No',
  ])
  return [header, ...rows].map((row) => row.map(csvCell).join(',')).join('\r\n')
}
