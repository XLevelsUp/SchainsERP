// GET /report/one-day-action (app) and /admin/reports/one-day-action
// (console): ReportService::getOneDayActionReport.
//
// 2026-11-25: the legacy screen's two sections, Stock Report and Cash
// Management Report, each with its own total and paging (it used to be one
// merged feed). Today by default; the app scopes to the signed-in head, the
// console to `head_id` or every head.

export type OneDayActionSectionName = 'stock' | 'cash'

export interface OneDayActionQuery {
  // Admin console only: the head the report is for; left out, every head.
  head_id?: number
  // Default to today server-side; a missing `to_date` means one day.
  from_date?: string
  to_date?: string
  // "User": who keyed the entry (`added_by`), in both sections.
  employee_id?: number
  // Stock section only (cash has no item).
  item_id?: number
  // "Stock Type". On cash, IN = the Receive group, OUT = the Pay group.
  type?: 'IN' | 'OUT'
  given_by?: number
  given_to?: number
  // Either party has this role.
  role_id?: number
  // Only this section comes back (a section's Load More).
  section?: OneDayActionSectionName
  page_no?: number
  // 1–1000.
  page_size?: number
}

export interface OneDayActionStockRow {
  id: number
  entry_type: string
  stock_type: string
  // Resolved names, "-" when the relation is missing.
  given_by: string
  given_to: string
  item_name: string
  grams: number | string | null
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

export interface OneDayActionCashRow {
  id: number
  // cash_txn_details.type: INCOME, EXPENSE, SALE_GOLD, ...
  type: string
  flow: 'IN' | 'OUT'
  given_by: string
  given_to: string
  category_name: string | null
  amount: number | string | null
  // CASH_ON_HAND | BANK (older rows may say CASH).
  payment_method: string | null
  bank_name: string | null
  remarks: string | null
  added_by: number | null
  added_at: string
}

export interface OneDayActionPagination {
  current_page: number
  per_page: number
  total_items: number
  total_pages: number
}

export interface OneDayActionSection<Row> {
  total_count: number
  records: Row[]
  pagination: OneDayActionPagination
}

// Both sections, unless `section` asked for one.
export interface OneDayActionResult {
  stock?: OneDayActionSection<OneDayActionStockRow>
  cash?: OneDayActionSection<OneDayActionCashRow>
}
