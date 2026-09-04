// GET /stock/reports/consolidated — StockDetailsController::
// getConsolidatedReport -> ReportService::getConsolidatedReport (PR #31).
//
// Summarised outward/inward history for one employee or retailer. The
// summary totals are aggregated in PostgreSQL (SUM/CASE WHEN) rather than
// in PHP, so they cover the whole filtered set, not just the page below.

export interface ConsolidatedTotals {
  // round()ed floats from the SQL aggregate — real JSON numbers.
  grams: number
  purity: number
  wastage: number
}

export interface ConsolidatedRow {
  stock_id: number
  entry_type: string
  stock_type: string
  // StockDetails casts these as `decimal:4` and this service passes the
  // attributes through uncast, so they arrive as strings ("10.0000") — the
  // API doc's example is right about this and the items OB/CB report is the
  // one that differs. Run them through Number() before doing any maths.
  grams: string
  touch: string
  purity: string
  // Nullable in the schema (grams/touch/purity are NOT NULL); confirmed
  // arriving as null on real rows.
  waste_value: string | null
  // `added_at` is cast to `datetime` on the model, so this is a serialised
  // Carbon instance (ISO-8601), not the "YYYY-MM-DD HH:MM:SS" the API doc
  // shows. formatDateTime() parses either, so nothing here depends on it.
  added_at: string
  remarks: string | null
  item_id: number
  item_name: string | null
  given_by_name: string | null
  given_to_name: string | null
}

export interface ConsolidatedSection {
  total_count: number
  // Echoed straight back from the query string, so these are strings on a
  // paged request and numbers only when the service defaulted them. The
  // view tracks its own page state rather than reading these back.
  page_no: number | string
  page_size: number | string
  records: ConsolidatedRow[]
}

export interface ConsolidatedResult {
  summary: {
    out: ConsolidatedTotals
    in: ConsolidatedTotals
  }
  out_details: ConsolidatedSection
  in_details: ConsolidatedSection
}

export interface ConsolidatedQuery {
  // Send exactly one of these. Unlike items-obcb's single prefixed
  // `employee_id`, this endpoint takes plain numeric ids and decides which
  // party a bare value means from which key was present.
  user_id?: number
  retailer_id?: number
  item_id?: number
  from_date?: string
  to_date?: string
  from_time?: string
  to_time?: string
  // Defaults to 1000 server-side — shared by both sections.
  page_size?: number
  page_no_out?: number
  page_no_in?: number
}
