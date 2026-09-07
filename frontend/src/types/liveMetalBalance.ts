// GET /report/live-metal-balance — ReportController::getLiveMetalBalance ->
// ReportService::getLiveMetalBalanceReport (PR #35).
//
// KNOWN BACKEND BUGS (see PENDING_WORK.md #17-20) — this endpoint is wired
// up and used as-built rather than held back, per standing instruction:
//   #17 "Metal" is hardcoded to item_id 2, which is actually "Gold Chain"
//       in this app's data (item_id 4 is Metal). Every result here is
//       really a Gold Chain balance mislabeled as Metal.
//   #18 The date+time ("as of") query path uses MySQL-only raw SQL
//       (IFNULL, backticks) against a Postgres database and will 500.
//   #19 The `user_id` admin-override param can never activate — the
//       backend's admin check is unreachable against this role schema.
// The frontend surfaces all three on-screen rather than hiding them.

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
