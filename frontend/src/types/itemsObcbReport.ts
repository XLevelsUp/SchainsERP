// GET /stock/reports/items-obcb — StockDetailsController::getHistoryItemsObcb
// -> ReportService::getItemsObcbReport (PR #31).
//
// Path note: the route is registered inside the `v1/stock` prefix group, so
// the full path is /api/v1/stock/reports/items-obcb. The API doc writes it
// as /api/v1/stocks/... (plural) — that path does not exist.
//
// Running opening/closing balance ledger. OB/CB are read from each row's
// `obcb_details` JSON snapshot, picking the given_by or given_to side based
// on which party the report is scoped to, and falling back to the flat
// *_op/*_cb columns when a row has no snapshot.

export interface ItemsObcbRow {
  stock_id: number
  given_by_name: string
  given_to_name: string
  // Resolved to the head's perspective server-side: an OUT row addressed to
  // the acting head via HEADTOHEAD/EMPTOHEAD is reported as IN.
  stock_type: string
  entry_type: string
  item_name: string
  // Only set for conversions (ITEMCHANGE / ITEMCONVERSION / auto-entry).
  to_item_name: string | null
  // Every weight field here is explicitly (float)-cast in the service, so
  // these arrive as JSON numbers — unlike the consolidated report's, which
  // come back as decimal strings. Don't assume one shape for both.
  grams: number
  touch: number
  purity: number
  ob_grams: number
  cb_grams: number
  ob_purity: number
  cb_purity: number
  remarks: string | null
  // "YYYY-MM-DD HH:MM:SS" — the service calls ->toDateTimeString().
  added_at: string
}

export interface ItemsObcbQuery {
  // "user_<id>" or "retailer_<id>". A bare number is parsed as a USER id, so
  // a retailer must carry the prefix — see getItemsObcbReport's parser.
  employee_id?: string
  item_id?: number
  from_date?: string
  to_date?: string
  from_time?: string
  to_time?: string
  type?: 'IN' | 'OUT'
  page_size?: number
  page_no?: number
}

export interface ItemsObcbResult {
  total_count: number
  page_no: number
  page_size: number
  records: ItemsObcbRow[]
}
