// GET /stock-details/available-metals?item_id=&user_id= (API doc #22).
// Backend strictly requires the item named by item_id to literally be
// "Metal" (case-insensitive) — StockDetailsController::getAvailableMetals
// 400s otherwise. See AvailableMetalResource for the exact response shape.
export interface AvailableMetalRow {
  id: number
  grams: number
  touch: number
  purity: number
  party_name: string
  balance_grams: number
}

export interface AvailableMetalQuery {
  item_id: number
  user_id: number
}

// A row after the operator has filled in Taken/Wastage in the picker —
// see the API doc's "IMPORTANT" note: the endpoint only returns the raw
// stock rows, all Taken/Wastage entry and row/footer totals are computed
// client-side.
export interface MetalPickerSelection extends AvailableMetalRow {
  taken: number
  wastage: number
  rowTotal: number
}
