export interface CustomerTouch {
  item_id: number
  item_name: string
  is_active: boolean
  added_at?: string
}

export interface CustomerTouchFormValues {
  item_name: string
  is_active: boolean
}
