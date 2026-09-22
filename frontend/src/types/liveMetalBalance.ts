// GET /report/live-metal-balance — ReportController::getLiveMetalBalance ->
// ReportService::getLiveMetalBalanceReport (PR #35).
//
// KNOWN BACKEND BUGS (see PENDING_WORK.md) — this endpoint is wired up and
// used as-built rather than held back, per standing instruction:
//   #17 FIXED FRONTEND-SIDE by PR #42. The item is a parameter now, but it
//       still DEFAULTS to item_id 2 ("Gold Necklace" in this dataset, not
//       "Metal"). So item_id is not optional in practice — this app always
//       resolves the real Metal item by name and sends it. Omitting it
//       silently reports the wrong item.
//   #18 STILL OPEN. The date+time ("as of") query path uses MySQL-only raw
//       SQL (IFNULL, backticks) against a Postgres database and 500s.
//       Verified against the live database 2026-09-15.
//   #19 STILL OPEN. The `user_id` admin-override param can never activate —
//       the backend's admin check is unreachable against this role schema.
// The frontend surfaces the two open ones on-screen rather than hiding them.

export interface LiveMetalBalanceRecord {
  stock_id: number
  balance: number
  touch: number
  purity: number
  party_name: string
  added_at: string
}

export interface LiveMetalBalanceSummary {
  total_grams: number
  total_purity: number
}

export interface LiveMetalBalancePagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface LiveMetalBalanceResult {
  summary: LiveMetalBalanceSummary
  records: LiveMetalBalanceRecord[]
  pagination: LiveMetalBalancePagination
}

export interface LiveMetalBalanceQuery {
  // Which item to report on (PR #42). Optional to the backend but never
  // omitted here — its default is the wrong item. See bug #17 above.
  item_id?: number
  // "As of" reconstruction — both required together or the endpoint just
  // returns the live (balance > 0) view. See bug #18 above.
  date?: string
  time?: string
  per_page?: number
  page?: number
  // Admin override — see bug #19 above. Sent when provided; currently has
  // no effect against any real user in this dataset.
  user_id?: number
}
