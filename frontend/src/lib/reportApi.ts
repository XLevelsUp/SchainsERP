import { api, type ApiResponse } from './api'
import type {
  CashTransactionReportQuery,
  CashTransactionReportResponse,
  LiveMetalBalanceQuery,
  LiveMetalBalanceResult,
  OneDayActionQuery,
  OneDayActionResult,
} from '@/types'

const RESOURCE = '/report'

function buildQuery(params: CashTransactionReportQuery): string {
  const qs = new URLSearchParams()
  if (params.category_id !== undefined) qs.set('category_id', String(params.category_id))
  if (params.type) qs.set('type', params.type)
  if (params.bank_id !== undefined) qs.set('bank_id', String(params.bank_id))
  if (params.from_date) qs.set('from_date', params.from_date)
  if (params.to_date) qs.set('to_date', params.to_date)
  if (params.bank_entry_from_date) qs.set('bank_entry_from_date', params.bank_entry_from_date)
  if (params.bank_entry_to_date) qs.set('bank_entry_to_date', params.bank_entry_to_date)
  if (params.page_size !== undefined) qs.set('page_size', String(params.page_size))
  if (params.page !== undefined) qs.set('page', String(params.page))
  if (params.is_all) qs.set('is_all', '1')
  const s = qs.toString()
  return s ? `?${s}` : ''
}

function buildLiveMetalQuery(params: LiveMetalBalanceQuery): string {
  const qs = new URLSearchParams()
  // Always sent by this app — the backend's default is item_id 2, which is
  // not the Metal item in this dataset. See types/liveMetalBalance.ts.
  if (params.item_id !== undefined) qs.set('item_id', String(params.item_id))
  if (params.date) qs.set('date', params.date)
  if (params.time) qs.set('time', params.time)
  if (params.per_page !== undefined) qs.set('per_page', String(params.per_page))
  if (params.page !== undefined) qs.set('page', String(params.page))
  if (params.user_id !== undefined) qs.set('user_id', String(params.user_id))
  const s = qs.toString()
  return s ? `?${s}` : ''
}

function buildOneDayActionQuery(params: OneDayActionQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const reportApi = {
  // GET /report/cash-transactions-obcb — see cashTransactionReport.ts for why
  // cash_main_category_id is still not exposed (the column exists, but nothing
  // in the backend can name its values).
  getCashTransactionsObcb: (params: CashTransactionReportQuery) =>
    api.get<CashTransactionReportResponse>(`${RESOURCE}/cash-transactions-obcb${buildQuery(params)}`),

  // GET /report/live-metal-balance — see types/liveMetalBalance.ts for the
  // known backend bugs (#17-20 in PENDING_WORK.md) this wraps around rather
  // than waits on.
  getLiveMetalBalance: (params: LiveMetalBalanceQuery = {}) =>
    api
      .get<ApiResponse<LiveMetalBalanceResult>>(`${RESOURCE}/live-metal-balance${buildLiveMetalQuery(params)}`)
      .then((r) => r.data),

  // GET /report/one-day-action — one day's stock movements and cash
  // transactions in a single feed (PR #37, testing doc section 47).
  //
  // PR #46 made the report head-scoped: ReportController::getOneDayActionReport
  // reads `X-User-ID` (defaulting to 1 if absent — it does not fall back to
  // the bearer token like most other controllers do) and the service now
  // ANDs `given_by = headId OR given_to = headId` into both queries
  // (PENDING_WORK.md #22, fixed). `actingUserId` is required so the report
  // scopes to the signed-in head instead of silently defaulting to user 1.
  getOneDayAction: (params: OneDayActionQuery, actingUserId: number) =>
    api
      .get<ApiResponse<OneDayActionResult>>(`${RESOURCE}/one-day-action${buildOneDayActionQuery(params)}`, {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),
}
