// POST /stock/auto-entry — StockDetailsController::postAutoEntry ->
// AutoEntryService::executeAutoTransfer (PR #30).
//
// Unified direct transfer between two users. One request creates one
// billing entry plus one OUT stock_details row per item row, and moves both
// parties' balances.

export type AutoEntryType = 'EMPTOEMP' | 'EMPTOHEAD' | 'ANOTHERHEADTOEMP' | 'HEADTOHEAD'

// Per-row calculation mode. Drives which extra columns apply and which
// auxiliary history record the service writes (GmsHistory / FitemHistory).
export type AutoEntryRowType = 'NORMAL' | 'GMS' | 'FITEM'

// NOTE: rows carry no item id. AutoEntryService resolves from_item/to_item
// ONCE from the type-specific header fields and applies them to every row,
// so item selection belongs to the header, not the grid.
export interface AutoEntryItemInput {
  type: AutoEntryRowType
  grams: number | null
  // Both required. Validated `min:1|max:999` here — NOT `between:0,100`
  // like every other stock module. Do not copy the usual validator.
  touch: number | null
  to_touch: number | null
  waste_total: number | null
  to_waste_total: number | null
  remarks: string
  item_remarks: string
  // Validated as `date_format:Y-m-d H:i:s` (strict). See the view's note on
  // why the backend currently ignores it anyway.
  added_at: string
  // GMS rows only
  stone: number | null
  thread: number | null
  to_stone: number | null
  to_thread: number | null
  gms_mtouch: number | null
  // Spelled this way in AutoEntryRequest and in the stock_details column.
  // The typo is the contract — send it.
  gms_mthouch_wastage: number | null
  to_gms_mtouch: number | null
  to_gms_mthouch_wastage: number | null
  // FITEM rows only
  box_id: number | null
  mtouch: number | null
  to_mtouch: number | null
}

// Neutral form shape. stockApi::toAutoEntryPayload maps these onto the
// per-type field names the backend expects — see the table there.
export interface AutoEntryFormValues {
  type: AutoEntryType
  from_user_id: number | null
  to_user_id: number | null
  from_item_id: number | null
  to_item_id: number | null
  // Only some types accept these; the payload builder drops the ones that
  // do not apply. Neither has an `exists:` rule server-side.
  from_retailer_id: number | null
  to_retailer_id: number | null
  items: AutoEntryItemInput[]
}

// The service returns the created StockDetails models. Only the fields this
// app reads are declared; the payload carries the full row.
export interface AutoEntryResultRow {
  stock_id: number
  entry_type: string
  stock_type: string
  grams: string
  purity: string
}
