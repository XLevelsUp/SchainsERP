import { api, type ApiResponse } from './api'
import type { StockCashHistoryPage, StockCashHistoryQuery } from '@/types'

const RESOURCE = '/stock-details'

function buildQuery(params: StockCashHistoryQuery): string {
  const qs = new URLSearchParams()
  if (params.head_id !== undefined) qs.set('head_id', String(params.head_id))
  if (params.cash_user_id !== undefined) qs.set('cash_user_id', String(params.cash_user_id))
  if (params.per_page !== undefined) qs.set('per_page', String(params.per_page))
  if (params.page !== undefined) qs.set('page', String(params.page))
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const stockCashHistoryApi = {
  // type IN/OUT scoped to one head/cash-user pair — see StockCashHistoryRow.
  list: (params: StockCashHistoryQuery) =>
    api
      .get<ApiResponse<StockCashHistoryPage>>(
        `${RESOURCE}/cash-transaction-history${buildQuery(params)}`,
      )
      .then((r) => r.data),
}
