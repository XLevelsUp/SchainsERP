// ============================================================================
// POST /cash-txn-details/in and /cash-txn-details/out
// (CashTxnDetailController::postIncome/postExpense, backed by
// CashTxnDetailService + StoreCashTxnDetailRequest) — the only cash-txn
// endpoints the backend exposes as of PR #13. The old CRUD
// (index/store/update/destroy on cash-txn-details, plus the per-transaction
// image upload/delete endpoints) was dropped when cash_txn_details/
// cash_txn_images were reshaped to sender_id/recipient_id/payment_method/
// cash_txn_id/image_path, with no replacement list/get endpoint.
// ============================================================================

export type CashTxnSourceType = 'CASH_ON_HAND' | 'BANK'

// Values the quick in/out form collects. sender_id is who the cash/bank
// balance moves from, recipient_id who it moves to — for an "IN" entry
// that's typically the counter user as sender and the cash head as
// recipient (or vice versa for "OUT"); the UI decides which, the API
// only cares about direction via which endpoint you call (in vs out).
export interface CashTxnPostFormValues {
  sender_id: number | null
  recipient_id: number | null
  category_id: number | null
  amount: number | null
  payment_method: CashTxnSourceType
  bank_account_id: number | null
  remarks: string
  // Paths of already-uploaded attachments (this endpoint takes JSON, not
  // multipart — there is currently no separate upload endpoint for these
  // paths; images.* is validated as plain strings server-side).
  images: string[]
}

// Shape returned by postIncome/postExpense — a raw CashTxnDetail row on
// the current schema (relations are not eager-loaded).
export interface CashTxnPostResult {
  txn_id: number
  type: 'INCOME' | 'EXPENSE'
  sender_id: number
  recipient_id: number
  category_id: number | null
  amount: number
  balance_after_txn: number | null
  sender_opening_cash: number
  sender_opening_rtgs: number
  recipient_opening_cash: number
  recipient_opening_rtgs: number
  sender_closing_cash: number
  sender_closing_rtgs: number
  recipient_closing_cash: number
  recipient_closing_rtgs: number
  payment_method: CashTxnSourceType
  bank_account_id: number | null
  remarks: string | null
  added_by: number
  created_at?: string
  updated_at?: string
}
