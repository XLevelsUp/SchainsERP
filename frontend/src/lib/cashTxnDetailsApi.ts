import { api, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import type {
  CashTxnHistoryPage,
  CashTxnHistoryQuery,
  CashTxnPostFormValues,
  CashTxnPostResult,
} from '@/types'

const RESOURCE = '/cash-txn-details'

// ============================================================================
// POST /cash-txn-details/in and /cash-txn-details/out
// (CashTxnDetailController::postIncome/postExpense). These are the only
// cash-txn write endpoints — the old full CRUD (index/store/update/destroy,
// plus per-transaction image upload/delete) was dropped from the backend
// in PR #13 when cash_txn_details/cash_txn_images were reshaped to
// sender_id/recipient_id/payment_method/cash_txn_id/image_path.
//
// PR #17 added a read side back — GET .../in-history and .../out-history
// (below) — but there's still no show/update/destroy for a single row, so
// history is browsable but not editable.
// ============================================================================

function buildHistoryQuery(params: CashTxnHistoryQuery): string {
  const qs = new URLSearchParams()
  if (params.head_id !== undefined) qs.set('head_id', String(params.head_id))
  if (params.cash_user_id !== undefined) qs.set('cash_user_id', String(params.cash_user_id))
  if (params.from_date) qs.set('from_date', params.from_date)
  if (params.to_date) qs.set('to_date', params.to_date)
  if (params.per_page !== undefined) qs.set('per_page', String(params.per_page))
  if (params.page !== undefined) qs.set('page', String(params.page))
  const s = qs.toString()
  return s ? `?${s}` : ''
}

function toPayload(form: CashTxnPostFormValues) {
  const payload: Record<string, unknown> = {
    sender_id: form.sender_id,
    recipient_id: form.recipient_id,
    category_id: form.category_id,
    amount: form.amount,
    payment_method: form.payment_method,
    remarks: form.remarks || null,
    images: form.images,
  }

  // bank_account_id is required_if payment_method=BANK on the backend;
  // send null rather than omit for CASH_ON_HAND so it always overwrites.
  payload.bank_account_id = form.payment_method === 'BANK' ? form.bank_account_id : null

  return payload
}

export const cashTxnDetailsApi = {
  // There is no auth/session on this backend yet — postIncome/postExpense
  // resolve the acting user (added_by) from an X-User-ID header (falling
  // back to a hardcoded id if it's missing, see
  // CashTxnDetailController::postIncome/postExpense), so it must be sent
  // explicitly, same pattern as stockApi.ts. Always sent as multipart since
  // PR #16 made images real file uploads on this endpoint.
  postIncome: (form: CashTxnPostFormValues, actingUserId: number) =>
    api
      .postForm<ApiResponse<CashTxnPostResult>>(`${RESOURCE}/in`, buildMultipartForm(toPayload(form)), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),

  postExpense: (form: CashTxnPostFormValues, actingUserId: number) =>
    api
      .postForm<ApiResponse<CashTxnPostResult>>(`${RESOURCE}/out`, buildMultipartForm(toPayload(form)), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),

  // type IN (INCOME, AUTO_ENTRY, CASH_TO_GOLD, SALE_GOLD) — see CashTxnHistoryType.
  getInHistory: (params: CashTxnHistoryQuery) =>
    api
      .get<ApiResponse<CashTxnHistoryPage>>(`${RESOURCE}/in-history${buildHistoryQuery(params)}`)
      .then((r) => r.data),

  // type IN (EXPENSE, PURCHASE_GOLD, INTERNAL_TRANSFER, GOLD_TO_CASH).
  getOutHistory: (params: CashTxnHistoryQuery) =>
    api
      .get<ApiResponse<CashTxnHistoryPage>>(`${RESOURCE}/out-history${buildHistoryQuery(params)}`)
      .then((r) => r.data),
}
