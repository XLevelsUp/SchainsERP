import { api, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import type { CashTxnPostFormValues, CashTxnPostResult } from '@/types'

const RESOURCE = '/cash-txn-details'

// ============================================================================
// POST /cash-txn-details/in and /cash-txn-details/out
// (CashTxnDetailController::postIncome/postExpense). These are the only
// cash-txn write endpoints — the old full CRUD (index/store/update/destroy,
// plus per-transaction image upload/delete) was dropped from the backend
// in PR #13 when cash_txn_details/cash_txn_images were reshaped to
// sender_id/recipient_id/payment_method/cash_txn_id/image_path. There is
// no replacement list/get endpoint, so a ledger view isn't possible against
// the current API — only posting new in/out entries.
// ============================================================================

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
}
