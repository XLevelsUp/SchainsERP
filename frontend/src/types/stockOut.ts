// One item row in the Stock Out form. waste_id is intentionally not
// exposed — wastage_details has no API to read/write it from, so there's
// no way to show a meaningful picker; waste_total (%) is the actual input
// that drives the wastage math. waste_value/purity are left for the
// backend to derive (StockInventoryService::createStockOut) rather than
// duplicated client-side, to avoid the two ever drifting apart.
export interface StockOutItemInput {
  item_id: number | null
  grams: number | null
  touch: number | null
  waste_total: number | null
  remarks: string
  item_remarks: string
}

export interface StockOutFormValues {
  given_by: number | null
  given_to: number | null
  retailer_id: number | null
  added_at: string
  items: StockOutItemInput[]
}

// A row as returned in the postStockOut response's `stocks` array.
export interface StockOutResultRow {
  stock_id: number
  item_id: number
  given_by: number
  given_to: number
  type: string
  entry_type: string
  stock_type: string
  grams: number
  touch: number
  purity: number
  waste_total: number
  waste_value: number
  balance: number
  bill_id: number
  added_at?: string
}

export interface StockOutResult {
  bill_id: number
  stocks: StockOutResultRow[]
}
