// Mirrors ItemChangeRequest / StockOutService::createItemChange(). Changes
// stock from one item to another at a new touch, optionally drawing it
// from a specific lot. stock_in_id became `nullable` in backend commit
// 6747887; when set, the service deducts that lot's balance and records
// OB/CB against it, when null it skips the draw-down entirely. Metal rows
// get their lot from MetalPickerModal (available-metals returns stock_id
// as `id`); other items have no lot-listing endpoint, so they post null.
export interface ItemChangeItemInput {
  stock_in_id: number | null
  from_item_id: number | null
  to_item_id: number | null
  grams: number | null
  from_touch: number | null
  req_touch: number | null
  remarks: string
  item_remarks: string
  // Per-item, not form-level — see StockOutItemInput's comment.
  added_at: string
}

export interface ItemChangeFormValues {
  user_id: number | null
  items: ItemChangeItemInput[]
}

// A row as returned in the postItemChange response's data array
// (ItemChangeHistory rows).
export interface ItemChangeResultRow {
  id: number
  stock_in_id: number
  from_item_id: number
  to_item_id: number
  grams: number
  from_touch: number
  req_touch: number
  added_at?: string
}

// Mirrors ItemConversionRequest / StockOutService::createItemConversion().
// Converts source item/grams/touch into a target item/touch, with an
// optional alloy breakdown per row. stock_in_id is optional here (unlike
// Item Change) and, like it, has no lot-listing endpoint yet.
export interface ItemConversionAlloyInput {
  alloy_item_id: number | null
  alloy_percentage: number | null
  alloy_grams: number | null
}

export interface ItemConversionItemInput {
  stock_in_id: number | null
  source_item_id: number | null
  target_item_id: number | null
  source_grams: number | null
  source_touch: number | null
  target_touch: number | null
  remarks: string
  item_remarks: string
  alloys: ItemConversionAlloyInput[]
  // Per-item, not form-level — see StockOutItemInput's comment.
  added_at: string
}

export interface ItemConversionFormValues {
  user_id: number | null
  items: ItemConversionItemInput[]
}

// A row as returned in the postItemConversion response's data array.
export interface ItemConversionResultRow {
  id: number
  source_item_id: number
  target_item_id: number
  source_grams: number
  converted_grams: number
  source_touch: number
  target_touch: number
  added_at?: string
}

// Mirrors NumericWasteRequest / StockOutService::createNumericWaste() — the
// OUT-direction counterpart of NumericWastageInFormValues (stockIn.ts).
// given_by is optional here (defaults server-side to the acting user),
// unlike Numeric Wastage In where both parties are required.
export interface NumericWastageOutItemInput {
  item_id: number | null
  grams: number | null
  touch: number | null
  no_of_pcs: number | null
  amount_pcs: number | null
  waste_total: number | null
  remarks: string
  item_remarks: string
  // Per-item, not form-level — see StockOutItemInput's comment.
  added_at: string
}

export interface NumericWastageOutFormValues {
  given_by: number | null
  given_to: number | null
  items: NumericWastageOutItemInput[]
}

// A row as returned in the postNumericWasteOut response's data array —
// same shape as NumericWastageInResultRow (stockIn.ts).
export interface NumericWastageOutResultRow {
  id: number
  item_id: number
  grams: number
  touch: number
  no_of_pcs: number
  wastage_value: number
  wastage_total: number
  type: string
  stock_in_detail_id: number | null
  amount: number
  cash_txn_id: number | null
  added_at?: string
}
