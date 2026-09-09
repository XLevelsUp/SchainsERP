import { api, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import type {
  CashTxnDetailRow,
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
// On show/update/destroy: `apiResource('cash-txn-details')` IS registered
// (routes/api.php:62) and all three methods exist on the controller, but only
// `show` is usable. It eager-loads images/bank/givenByUser/givenToUser, every
// one of which maps to a real column, so getOne below is safe.
//
// `update` and `destroy` are NOT wired up here on purpose. Both were written
// against the pre-PR-#13 schema and never migrated: they read `given_by`,
// `souce_type`, `bank_id` and `opening_account_balance`, none of which exist
// on cash_txn_details any more (it has sender_id / recipient_id /
// payment_method / bank_account_id, and no opening-balance columns). The
// practical effect is that `destroy` looks up `user_id = null`, matches
// nobody, skips its guarded balance restore, and then deletes the row and
// returns 200 — removing the transaction from history while leaving both
// parties' rak_cash_balance as if it had happened. `update`'s validator only
// recognises those same dead field names, so its balance-recalculation branch
// operates on nulls. Calling either would silently desync the cash ledger.
// Flagged to the backend team; see PENDING_WORK.md.
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

  // GET /cash-txn-details/{id} — the one usable member of the apiResource
  // trio (see the header comment). Returns the row with its parties, bank and
  // images, plus the opening/closing balance snapshots the history list
  // doesn't carry.
  getOne: (txnId: number) =>
    api.get<ApiResponse<CashTxnDetailRow>>(`${RESOURCE}/${txnId}`).then((r) => r.data),

  // Single-transaction thermal print payload — CashTxnDetailController::
  // getPrintReport's ?id= branch (the bulk date-range branch of the same
  // endpoint isn't used here).
  getPrintReceipt: (txnId: number) =>
    api
      .get<ApiResponse<CashTxnPrintReceipt>>(`${RESOURCE}/print-report?id=${txnId}`)
      .then((r) => r.data),
}
