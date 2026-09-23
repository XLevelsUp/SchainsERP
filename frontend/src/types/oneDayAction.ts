// GET /report/one-day-action — PR #37, testing doc §47.
//
// A single day's stock movements and cash transactions merged into one
// feed, replacing the legacy Yii2 "One Day Action" screen.
//
// PR #46 (2026-09-22, backend commit 60b3816) rewrote the response contract.
// What changed, all verified against ReportService::getOneDayActionReport:
//
//  1. The endpoint is now scoped to a head. `$headId` (from `X-User-ID`,
//     defaulting to 1 if the header is absent — see reportApi.ts) is ANDed
//     into both queries as `given_by = headId OR given_to = headId` /
//     `sender_id = headId OR recipient_id = headId`. Previously it accepted
//     the parameter and never used it (PENDING_WORK.md #22, now fixed) —
//     the caller MUST send X-User-ID now, or every result is scoped to
//     user 1 instead of the signed-in head.
//  2. Cash rows now carry the amount in `amount`, not `grams`. `grams` is
//     `null` on CASH rows and `amount` is `null` on STOCK rows (#24, fixed).
//  3. `added_at` is "YYYY-MM-DD HH:mm:ss" like every other endpoint, or the
//     literal "-" when the source timestamp was null. No more bespoke
//     "DD-MM-YYYY" parsing (#25, fixed).
//  4. Pagination is now applied to the combined, globally-sorted dataset
//     instead of per-source-then-concatenated, so `records` arrives in
//     final order and pages append cleanly (#23, fixed). `total_stock_count`
//     / `total_cash_count` are unchanged; `page_no`/`page_size` were
//     replaced by a `pagination` object.

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
  // Grams on STOCK rows, null on CASH rows.
  grams: number | string | null
  // Rupee amount on CASH rows, null on STOCK rows.
  amount: number | string | null
  touch: number | string | null
  purity: number | string | null
  waste_total: number | string | null
  waste_value: number | string | null
  remarks: string | null
  added_by: number | null
  // "YYYY-MM-DD HH:mm:ss", or "-" when the source timestamp was null.
  added_at: string
  is_hided: number
}

export interface OneDayActionPagination {
  current_page: number
  per_page: number
  total_items: number
  total_pages: number
}

export interface OneDayActionResult {
  // Still counted per source, even though the rows themselves are now one
  // combined, globally-paginated list.
  total_stock_count: number
  total_cash_count: number
  records: OneDayActionRow[]
  pagination: OneDayActionPagination
}
