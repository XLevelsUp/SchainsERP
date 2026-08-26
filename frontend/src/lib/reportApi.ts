import { api } from './api'
import type { CashTransactionReportQuery, CashTransactionReportResponse } from '@/types'

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

export const reportApi = {
  // GET /report/cash-transactions-obcb — see cashTransactionReport.ts for the
  // two query params (cash_main_category_id, bank_entry_*) deliberately left
  // unexposed because the columns they filter on don't exist in the DB yet.
  getCashTransactionsObcb: (params: CashTransactionReportQuery) =>
    api.get<CashTransactionReportResponse>(`${RESOURCE}/cash-transactions-obcb${buildQuery(params)}`),
}
