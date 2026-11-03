// GET /report/daywise-grand — ReportController::getDaywiseGrandReport ->
// ReportService::getDaywiseGrandReport. Not in the API testing doc
// (BACKEND_TODO §11); everything here is read off the service.
//
// A STOCK report, whatever the name suggests: per calendar day it sums
// stock_details grams and purity in and out for one party, across every
// item, skipping hidden (is_hided) and frozen (is_freezed) rows. No cash.
//
// Who the party is comes from `type4`:
//  - omitted / HEAD — the acting head, read from the X-User-ID header ONLY
//    (no bearer-token fallback — BACKEND_TODO §7), or `head_user_id`.
//    In = given_to is them, Out = given_by is them.
//  - EMPLOYEE — `employee_id` (or `user_id`), same given_to/given_by
//    columns, so it works for any user, not only employees.
//  - CUSTOMER / RETAILER — identical: `customer_id` (or `retailer_id`)
//    matched against the stock row's retailer_id / to_retailer_id tags,
//    NOT given_by/given_to.
// A missing or zero id silently falls back to the acting head.

export type DaywiseGrandPartyType = 'HEAD' | 'EMPLOYEE' | 'CUSTOMER' | 'RETAILER'

export interface DaywiseGrandQuery {
  from_date?: string
  to_date?: string
  type4?: DaywiseGrandPartyType
  employee_id?: number
  retailer_id?: number
  page?: number
  // Backend default 15.
  page_size?: number
}

export interface DaywiseGrandRow {
  // "YYYY-MM-DD" — the service groups on DATE(added_at).
  date: string
  // Rounded to 3 decimals server-side.
  in_grams: number
  in_purity: number
  out_grams: number
  out_purity: number
}

// Same envelope ReportService uses for its other paginated reports:
// legacy-style total_count/page_no/page_size plus a pagination object.
// Rows arrive newest day first.
export interface DaywiseGrandResult {
  total_count: number
  page_no: number
  page_size: number
  records: DaywiseGrandRow[]
  pagination: {
    current_page: number
    per_page: number
    total_items: number
    total_pages: number
  }
}
