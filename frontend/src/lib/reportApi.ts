import { api, type ApiResponse } from './api'
import type {
  CashTransactionReportQuery,
  CashTransactionReportResponse,
  LiveMetalBalanceQuery,
  LiveMetalBalanceResult,
} from '@/types'

const RESOURCE = '/report'

function buildQuery(params: CashTransactionReportQuery): string {
  const qs = new URLSearchParams()
  if (params.category_id !== undefined) qs.set('category_id', String(params.category_id))
  if (params.type) qs.set('type', params.type)
  if (params.bank_id !== undefined) qs.set('bank_id', String(params.bank_id))
  if (params.from_date) qs.set('from_date', params.from_date)
  if (params.to_date) qs.set('to_date', params.to_date)
  if (params.page_size !== undefined) qs.set('page_size', String(params.page_size))
  if (params.page !== undefined) qs.set('page', String(params.page))
  if (params.is_all) qs.set('is_all', '1')
  const s = qs.toString()
  return s ? `?${s}` : ''
}

function buildLiveMetalQuery(params: LiveMetalBalanceQuery): string {
  const qs = new URLSearchParams()
  if (params.date) qs.set('date', params.date)
  if (params.time) qs.set('time', params.time)
  if (params.per_page !== undefined) qs.set('per_page', String(params.per_page))
  if (params.page !== undefined) qs.set('page', String(params.page))
  if (params.user_id !== undefined) qs.set('user_id', String(params.user_id))
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const reportApi = {
  // GET /report/cash-transactions-obcb — see cashTransactionReport.ts for the
  // two query params (cash_main_category_id, bank_entry_*) deliberately left
  // unexposed because the columns they filter on don't exist in the DB yet.
  getCashTransactionsObcb: (params: CashTransactionReportQuery) =>
    api.get<CashTransactionReportResponse>(`${RESOURCE}/cash-transactions-obcb${buildQuery(params)}`),

  // GET /report/live-metal-balance — see types/liveMetalBalance.ts for the
  // known backend bugs (#17-20 in PENDING_WORK.md) this wraps around rather
  // than waits on.
  getLiveMetalBalance: (params: LiveMetalBalanceQuery = {}) =>
    api
      .get<ApiResponse<LiveMetalBalanceResult>>(`${RESOURCE}/live-metal-balance${buildLiveMetalQuery(params)}`)
      .then((r) => r.data),
}
