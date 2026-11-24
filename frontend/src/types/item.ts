export interface Item {
  item_id: number
  item_name: string
  is_active: boolean
  default_touch: number
  item_touch: number
  added_at: string
  is_need_fitem_shown: boolean
  mtouch: number | null
  is_barcode: boolean
  is_no_barcode: boolean
  // The legacy "Orders No" (2026-11-24): the order items appear in, in every
  // item dropdown and list (GET /items is sorted by it). Unique, 1 or more.
  order_no: number
}

// The Items page's form (admin console). order_no null = the next free
// number (the backend assigns it).
export type ItemFormValues = Omit<Item, 'item_id' | 'added_at' | 'order_no'> & { order_no: number | null }
