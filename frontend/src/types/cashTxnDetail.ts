// ============================================================================
// POST /cash-txn-details/in and /cash-txn-details/out
// (CashTxnDetailController::postIncome/postExpense, backed by
// CashTxnDetailService + StoreCashTxnDetailRequest) are the cash-txn write
// endpoints, on the post-PR-#13 schema: sender_id / recipient_id /
// payment_method / bank_account_id, images via cash_txn_id/image_path.
//
// GET /cash-txn-details/{id} (show) reads back a single row on that same
// schema — see CashTxnDetailRow below. update/destroy exist as routes but
// are unusable against the current schema; cashTxnDetailsApi.ts has the
// detail.
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
  // PR #16 fixed this endpoint to accept real receipt uploads (was
  // string|max:255 — pre-uploaded paths nothing could ever produce; now
  // nullable|image, same as the four gold-conversion endpoints).
  images: File[]
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

// ----------------------------------------------------------------------------
// GET /cash-txn-details/{id} — CashTxnDetailController::show
// ----------------------------------------------------------------------------
// The raw CashTxnDetail model with images/bank/givenByUser/givenToUser eager
// loaded. Every one of those relations maps to a real column (sender_id,
// recipient_id, bank_account_id, cash_txn_id), so this endpoint is sound —
// unlike its update/destroy siblings.
//
// Numeric columns are `decimal:2` casts, which Laravel serialises as strings.
// The relation keys are the model's method names, so they are givenByUser /
// givenToUser even though the columns underneath are sender_id / recipient_id.

export interface CashTxnDetailUserRef {
  user_id: number
  name: string | null
}

export interface CashTxnDetailBankRef {
  bank_id: number
  bank_name: string | null
  account_number?: string | null
}

// image_path is the real column. `image_full_url`, which show() also sets, is
// built from `$image->image_url` — a column that does not exist on
// cash_txn_images — so it always resolves to the bare storage root and is
// deliberately not modelled here. Rendering it would show broken images.
export interface CashTxnDetailImage {
  image_id: number
  cash_txn_id: number
  image_path: string
}

export interface CashTxnDetailRow {
  txn_id: number
  type: string
  sender_id: number
  recipient_id: number
  category_id: number | null
  amount: string
  balance_after_txn: string | null
  sender_opening_cash: string
  sender_opening_rtgs: string
  recipient_opening_cash: string
  recipient_opening_rtgs: string
  sender_closing_cash: string
  sender_closing_rtgs: string
  recipient_closing_cash: string
  recipient_closing_rtgs: string
  payment_method: CashTxnSourceType
  bank_account_id: number | null
  remarks: string | null
  remainder: string | null
  remainder_at: string | null
  is_hide: boolean
  bank_entry_date: string | null
  added_by: number
  created_at: string | null
  updated_at: string | null
  givenByUser: CashTxnDetailUserRef | null
  givenToUser: CashTxnDetailUserRef | null
  bank: CashTxnDetailBankRef | null
  images: CashTxnDetailImage[]
}
