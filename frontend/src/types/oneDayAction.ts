// GET /report/one-day-action — PR #37, testing doc §47.
//
// A single day's stock movements and cash transactions merged into one
// feed, replacing the legacy Yii2 "One Day Action" screen.
//
// Two things about this contract drive the whole view and are not
// negotiable frontend-side:
//
//  1. `grams` carries the *rupee amount* on CASH rows. ReportService says
//     so in its own comment ("Using grams column to display amount for
//     consolidated views"). Nothing may total that column across mixed
//     rows — see OneDayActionView's split totals.
//  2. `added_at` comes back as "DD-MM-YYYY HH:mm:ss", not the
//     "YYYY-MM-DD HH:mm:ss" every other endpoint returns, so `new Date()`
//     cannot parse it and lib/date's formatDateTime passes it through
//     verbatim. The view parses it explicitly.
//
// Both are flagged to the backend (PENDING_WORK.md #24, #25).

export type OneDayActionRecordType = 'STOCK' | 'CASH'

export interface OneDayActionQuery {
  // Defaults to today server-side when omitted. `to_date` defaults to
  // whatever `from_date` is, making the single-day case the default.
  from_date?: string
  to_date?: string
  // Filters `added_by` — who *keyed* the entry, not who the gold or cash
  // moved between. Labelled "Entered by" in the UI for that reason.
  employee_id?: number
  retailer_id?: number
  // entry_type: NORMAL | SALES | HEADTOHEAD | the cash-converter types.
  stocktype?: string
  // stock_type: IN | OUT. On cash rows the backend maps this onto its own
  // IN/OUT type groups instead.
  type?: 'IN' | 'OUT'
  // Matches either side of the transfer.
  given_by_given_to?: number
  given_by?: number
  given_to?: number
  item_id?: number
  page_no?: number
  page_size?: number
}

export interface OneDayActionRow {
  // stock_id for STOCK rows, txn_id for CASH rows — the two can collide,
  // so never key a list on this alone.
  id: number
  record_type: OneDayActionRecordType
  entry_type: string
  stock_type: string
  // Resolved names, already "-" when the relation is missing.
  given_by: string
  given_to: string
  // Always the literal "CASH" on cash rows.
  item_name: string
  // Grams on STOCK rows; rupee amount on CASH rows. See the note above.
  grams: number | string | null
  touch: number | string | null
  purity: number | string | null
  waste_total: number | string | null
  waste_value: number | string | null
  remarks: string | null
  added_by: number | null
  // "DD-MM-YYYY HH:mm:ss", or "-" when the source timestamp was null.
  added_at: string
  is_hided: number
}

export interface OneDayActionResult {
  // Counted per source, not combined: page_no/page_size are applied to the
  // stock query and the cash query separately and the two result sets are
  // then concatenated (PENDING_WORK.md #23).
  total_stock_count: number
  total_cash_count: number
  page_no: number
  page_size: number
  transactions: OneDayActionRow[]
}
