// GET /report/live-metal-balance — ReportController::getLiveMetalBalance ->
// ReportService::getLiveMetalBalanceReport (PR #35).
//
// KNOWN BACKEND BUGS (see PENDING_WORK.md) — all three now closed:
//   #17 Default item. Fixed frontend-side by PR #42 (this app always
//       resolves the real Metal item by name and sends item_id explicitly,
//       so it never hits the backend's default). PR #46 changed what that
//       default even means — the hardcoded `2` is gone, replaced by an
//       optional `live_metal_report_items` System Setting, 422 if neither
//       item_id nor that setting is present — but since this screen never
//       omits item_id, that change has no effect here.
//   #18 FIXED in PR #46 (2026-09-22, backend commit 60b3816). The "as of"
//       query's raw SQL is now Postgres-safe (COALESCE, no backticks).
//   #19 FIXED in PR #46. `role_id == 1` (which meant CUSTOMER, not admin)
//       was removed from the override check — it only tests
//       `role->role == 'HEAD'` now, which resolves correctly. The
//       signed-in user needs the HEAD role for "view as" to take effect.
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
  // omitted here — see #17 above for why.
  item_id?: number
  // "As of" reconstruction — both required together or the endpoint just
  // returns the live (balance > 0) view. Works against Postgres since #18.
  date?: string
  time?: string
  per_page?: number
  page?: number
  // Admin override — works for a signed-in HEAD user since #19. The backend
  // also accepts `view_as`, which takes precedence over `user_id` when both
  // are sent; this app only ever sends one or the other, so it is moot here.
  user_id?: number
}
