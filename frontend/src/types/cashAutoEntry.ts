// POST /cash-txn-details/auto-entry (CashTxnDetailController::autoEntry,
// backed by CashAutoEntryService + StoreCashAutoEntryRequest, PR #16).
// Batches multiple rows against one head/cash_user pair in one call, each
// row becoming its own cash_txn_details row with type=AUTO_ENTRY
// (hardcoded server-side regardless of the outer `type`) and its own
// receipt images.
//
// Direction — only the sender's balance moves, the recipient's is
// untouched (CashAutoEntryService comment: "an Expense is money leaving
// the system", replicating legacy behavior deliberately, not a bug):
//   EXPENSE / AUTO_ENTRY: head pays out — head's balance decreases.
//   INCOME: reversed — the cash user's balance decreases, not the head's.
export type CashAutoEntryType = 'AUTO_ENTRY' | 'EXPENSE' | 'INCOME'

export interface CashAutoEntryTransactionInput {
  amount: number | null
  category_id: number | null
  remarks: string
  // Accepted by the request but not in CashTxnDetail's $fillable, so it's
  // silently dropped server-side right now — kept in the form to match the
  // documented contract, not because it currently does anything.
  added_at: string
  images: File[]
}

export interface CashAutoEntryFormValues {
  type: CashAutoEntryType
  head_id: number | null
  cash_user_id: number | null
  transactions: CashAutoEntryTransactionInput[]
}

export interface CashAutoEntryResultRow {
  txn_id: number
  type: 'AUTO_ENTRY'
  sender_id: number
  recipient_id: number
  category_id: number | null
  amount: number
  sender_opening_cash: number
  sender_opening_rtgs: number
  recipient_opening_cash: number
  recipient_opening_rtgs: number
  sender_closing_cash: number
  sender_closing_rtgs: number
  recipient_closing_cash: number
  recipient_closing_rtgs: number
  payment_method: string
  bank_account_id: number | null
  remarks: string | null
  added_by: number
  created_at?: string
  images?: { image_id: number; cash_txn_id: number; image_path: string }[]
}
