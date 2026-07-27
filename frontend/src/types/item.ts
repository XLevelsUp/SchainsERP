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
}

export type ItemFormValues = Omit<Item, 'item_id' | 'added_at'>