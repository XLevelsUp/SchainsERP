// Mirrors StockInRequest / StockInService::createStockIn(). waste_id is
// intentionally not exposed — same reasoning as StockOutItemInput: no API
// exists yet to read/write wastage_details, so there's no meaningful picker.
export interface StockInItemInput {
  item_id: number | null
  grams: number | null
  touch: number | null
  waste_total: number | null
  remarks: string
  item_remarks: string
}

export interface StockInFormValues {
  given_by: number | null
  given_to: number | null
  added_at: string
  items: StockInItemInput[]
}

// A row as returned in the postStockIn response's data array.
export interface StockInResultRow {
  stock_in_detail_id: number
  item_id: number
  given_by: number
  given_to: number
  type: string
  entry_type: string
  stock_type: string
  grams: number
  touch: number
  purity: number
  waste_total: number | null
  waste_value: number | null
  balance: number
  bill_id: number | null
  added_at?: string
}

// Mirrors GmsInRequest / StockInService::createGmsIn().
export interface GmsInItemInput {
  item_id: number | null
  grams: number | null
  stone: number | null
  thread: number | null
  wastage: number | null
  hall_mark: number | null
  mtouch: number | null
  mtouch_wastage: number | null
  remarks: string
  item_remarks: string
}

export interface GmsInFormValues {
  given_by: number | null
  given_to: number | null
  added_at: string
  items: GmsInItemInput[]
}

// A row as returned in the postGmsIn response's data array.
export interface GmsInResultRow {
  gms_id: number
  item_id: number
  grams: number
  stone: number
  thread: number
  mtouch: number
  mtouch_wastage: number
  wastage: number
  hall_mark: number
  total: number
  gms_type: string
  gms_stock_in_id: number | null
  added_at?: string
}

// Mirrors GmsOutRequest / StockOutService::createGmsOut(). Unlike GMS In,
// given_by is optional (defaults server-side to the acting user) since the
// head/admin is usually the one initiating the send-out.
export interface GmsOutItemInput {
  item_id: number | null
  grams: number | null
  stone: number | null
  thread: number | null
  wastage: number | null
  hall_mark: number | null
  mtouch: number | null
  mtouch_wastage: number | null
  remarks: string
  item_remarks: string
}

export interface GmsOutFormValues {
  given_by: number | null
  given_to: number | null
  added_at: string
  items: GmsOutItemInput[]
}

// A row as returned in the postGmsOut response's data array.
export interface GmsOutResultRow {
  gms_id: number
  item_id: number
  grams: number
  stone: number
  thread: number
  mtouch: number
  mtouch_wastage: number
  wastage: number
  hall_mark: number
  total: number
  gms_type: string
  gms_stock_out_id: number | null
  added_at?: string
}

// Mirrors NumericWastageInRequest / StockInService::createNumericWasteIn().
export interface NumericWastageInItemInput {
  item_id: number | null
  grams: number | null
  touch: number | null
  no_of_pcs: number | null
  amount_pcs: number | null
  waste_total: number | null
  remarks: string
  item_remarks: string
}

export interface NumericWastageInFormValues {
  given_by: number | null
  given_to: number | null
  added_at: string
  items: NumericWastageInItemInput[]
}

// A row as returned in the postNumericWasteIn response's data array.
export interface NumericWastageInResultRow {
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
