import type { Item } from './item'

export interface FitemBox {
  box_id: number
  box_name: string
  item_id: number
  is_active: boolean
  added_by: number
  updated_by: number | null
  added_at?: string
  updated_at?: string
  // Eager-loaded by the backend (index/show use ->with('item'))
  item?: Item | null
}

export interface FitemBoxFormValues {
  box_name: string
  item_id: number | null
  is_active: boolean
  added_by: number
  updated_by: number | null
}