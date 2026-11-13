import { api, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import type {
  CashTxnDetailRow,
  CashTxnEditPayload,
  CashTxnHistoryPage,
  CashTxnHistoryQuery,
  CashTxnPostFormValues,
  CashTxnPostResult,
  CashTxnPrintReceipt,
} from '@/types'

const RESOURCE = '/cash-txn-details'

// ============================================================================
// POST /cash-txn-details/in and /cash-txn-details/out
// (CashTxnDetailController::postIncome/postExpense) are the cash-txn write
// endpoints. PR #17 added the read side — GET .../in-history and
// .../out-history — and PR #32 put the whole group behind `auth:api`.
//
// Since 2026-11-12 `apiResource('cash-txn-details')` is read-only
// (index/show) plus a new PUT for editing. Its old store/update/destroy were
// written against the old schema (given_by, souce_type, bank_id, ...), so
// DELETE removed an entry without restoring either party's balance; they stay
// unregistered, and deleting isn't allowed at all (agreed 2026-11-12).
//
// `show` (getOne below) returns 403 to a user who can't act as head and isn't
// a party to the entry. `update` is heads only and, for now, Cash In / Cash
// Out and Cash Auto Entry only (BACKEND_TODO §17 phases 1–2).
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
  // postIncome/postExpense resolve the acting user (added_by) as
  // `$request->user()->user_id ?? header('X-User-ID', 1)`
  // (CashTxnDetailController:2395/2419). Since PR #32 the bearer token wins,
  // so the header is now belt-and-braces rather than the only signal — kept
  // because it costs nothing and still carries the intent if a route is ever
  // moved outside the auth group. Same pattern as stockApi.ts. Always sent as
  // multipart since PR #16 made images real file uploads on this endpoint.
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

  // type IN (INCOME, AUTO_ENTRY, CASH_TO_GOLD, SALE_GOLD, IN_CASH_CONVERTER) — see CashTxnHistoryType.
  getInHistory: (params: CashTxnHistoryQuery) =>
    api
      .get<ApiResponse<CashTxnHistoryPage>>(`${RESOURCE}/in-history${buildHistoryQuery(params)}`)
      .then((r) => r.data),

  // type IN (EXPENSE, PURCHASE_GOLD, GOLD_TO_CASH, OUT_CASH_CONVERTER).
  getOutHistory: (params: CashTxnHistoryQuery) =>
    api
      .get<ApiResponse<CashTxnHistoryPage>>(`${RESOURCE}/out-history${buildHistoryQuery(params)}`)
      .then((r) => r.data),

  // GET /cash-txn-details/{id}. Returns the row with its parties, bank and
  // images, the opening/closing balance snapshots the history list doesn't
  // carry (only your own side unless you're a head), and edit_blocked_reason.
  getOne: (txnId: number) =>
    api.get<ApiResponse<CashTxnDetailRow>>(`${RESOURCE}/${txnId}`).then((r) => r.data),

  // PUT /cash-txn-details/{id}, heads only. The backend corrects both
  // parties' balances, the bank and every later entry's snapshots, and
  // returns the updated row.
  update: (txnId: number, payload: CashTxnEditPayload) =>
    api.put<ApiResponse<CashTxnDetailRow>>(`${RESOURCE}/${txnId}`, payload).then((r) => r.data),

  // Single-transaction thermal print payload — CashTxnDetailController::
  // getPrintReport's ?id= branch (the bulk date-range branch of the same
  // endpoint isn't used here).
  getPrintReceipt: (txnId: number) =>
    api
      .get<ApiResponse<CashTxnPrintReceipt>>(`${RESOURCE}/print-report?id=${txnId}`)
      .then((r) => r.data),
}
