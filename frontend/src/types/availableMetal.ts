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

// A row after the operator has filled in Taken in the picker — see the
// API doc's "IMPORTANT" note: the endpoint only returns the raw stock
// rows, Taken entry and the Required/Taken/Remaining summary are all
// computed client-side. No per-lot wastage here — the resulting stock
// row(s) this feeds into already carry their own waste_total field.
export interface MetalPickerSelection extends AvailableMetalRow {
  taken: number
}
