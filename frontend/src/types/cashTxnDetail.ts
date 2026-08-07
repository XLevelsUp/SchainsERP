import type { BankDetail } from './bankDetail'
import type { UserDetail } from './userDetail'

// ============================================================================
// WARNING — everything below this point (CashTxnDetail, CashTxnImage,
// CashTxnDetailFormValues) targets the CRUD endpoints
// (GET/POST/PUT/DELETE /cash-txn-details, /cash-txn-details/{id}/images,
// /cash-txn-images/{id}). As of PR #13, the backend rewrote the
// cash_txn_details / cash_txn_images / bank_details tables (sender_id
// /recipient_id/payment_method/bank_account_id, cash_txn_id/image_path,
// account_name/ledger_balance) but left this CRUD controller code
// unchanged — it still reads/writes given_by, opening_account_balance,
// txn_id/image_url, bank_name/current_balance, none of which exist
// anymore. Every one of those endpoints now 500s with an "unknown
// column" SQL error. Do not build new screens against these types.
// Use CashTxnPostFormValues / CashTxnPostResult (below) instead, which
// target the only endpoints that actually work against the current
// schema: POST /cash-txn-details/in and /cash-txn-details/out.
// ============================================================================

// Only INCOME/EXPENSE actually drive balance math server-side (see
// CashTxnDetailController::calculateBalances) — every other type is stored
// as a manual entry with closing balances equal to whatever opening values
// are submitted. CASH_TO_GOLD and CashToGold are both accepted by the
// backend validator (a legacy duplicate) — kept as-is rather than merged.
export type CashTxnType =
  | 'INCOME'
  | 'EXPENSE'
  | 'AUTO_ENTRY'
  | 'CASH_TO_GOLD'
  | 'PURCHASE_GOLD'
  | 'SALE_GOLD'
  | 'AMOUNT_TRANSFER'
  | 'GOLD_TO_CASH'
  | 'INTERNAL_TRANSFER'
  | 'IN_CASH_CONVERTER'
  | 'OUT_CASH_CONVERTER'
  | 'CashToGold'
  | 'CASH_LOAN'

export type CashTxnSourceType = 'CASH_ON_HAND' | 'BANK'
export type CashTxnEntryType = 'NORMAL' | 'ATTENDANCE'
export type BillPaymentCashType = 'ON_SPOT_NILL' | 'ON_ACCOUNTABLE' | 'PARTIAL_PAYMENT'
export type ArithmeticOperation = '+' | '-'
export type CashLoanType =
  | 'cash_loan_taken'
  | 'cash_loan_given'
  | 'interest_payment'
  | 'interest_receipt'

export interface CashTxnImage {
  image_id: number
  txn_id: number
  image_url: string
  image_full_url: string
  added_at?: string
}

// A row as returned by the backend (index/show/store/update all eager-load
// images/bank/givenByUser/givenToUser and attach image_full_url).
export interface CashTxnDetail {
  txn_id: number
  type: CashTxnType
  given_to: number
  given_by: number
  category_id: number | null
  amount: number
  balance: number
  opening_account_balance: number
  opening_user_balance: number
  opening_bank_account_balance: number | null
  opening_bank_user_balance: number | null
  closing_account_balance: number
  closing_user_balance: number
  closing_bank_account_balance: number | null
  closing_bank_user_balance: number | null
  souce_type: CashTxnSourceType
  bank_id: number | null
  remarks: string | null
  remainder: string | null
  remainder_at: string | null
  added_at?: string
  added_by: number
  is_active: boolean
  is_hidden: boolean
  is_show_to_all: boolean
  amount_transfer_id: number | null
  image_url: string | null
  image_full_url: string | null
  cash_to_gold_id: number | null
  stock_id: number | null
  amnt_transfer_from_head: boolean
  internal_type: string | null
  retailer_id: number | null
  retailer_ob_cash_balance: number | null
  retailer_ob_rtgs_balance: number | null
  txn_type: CashTxnEntryType
  bank_entry_date: string | null
  machine_vendor_id: number | null
  machine_vendor_ob_cash_balance: number | null
  machine_vendor_ob_rtgs_balance: number | null
  is_bill_cash: boolean | null
  is_payment_cash: boolean | null
  is_customer_affect: boolean | null
  is_need_receipt: boolean | null
  bill_payment_cash_type: BillPaymentCashType | null
  partial_amount: number | null
  actual_amount: number | null
  receipt_cash_txn_id: number | null
  given_by_arithmetic_operation: ArithmeticOperation | null
  given_to_arithmetic_operation: ArithmeticOperation | null
  cash_loan_type: CashLoanType | null
  per_gram_cash: number | null
  over_all_bill_id: number | null
  estimate_retailer_bill_id: number | null
  estimate_metal_bill_id: number | null
  is_admin_head_entry: boolean
  admin_head_txn_id: number | null
  images: CashTxnImage[]
  bank: BankDetail | null
  // Eloquent snake_cases relation names in JSON output by default, so
  // these arrive as given_by_user/given_to_user despite the PHP relation
  // methods being camelCase (givenByUser()/givenToUser()).
  given_by_user: UserDetail | null
  given_to_user: UserDetail | null
}

// Fields the store()/update() endpoints accept as JSON (images are handled
// separately via the dedicated multipart add-images/delete-image endpoints).
export interface CashTxnDetailFormValues {
  type: CashTxnType
  given_to: number | null
  given_by: number | null
  category_id: number | null
  amount: number | null

  opening_account_balance: number | null
  opening_user_balance: number | null
  opening_bank_account_balance: number | null
  opening_bank_user_balance: number | null

  souce_type: CashTxnSourceType
  bank_id: number | null
  bank_name: string

  remarks: string
  remainder: string
  remainder_at: string

  added_by: number | null

  is_active: boolean
  is_hidden: boolean
  is_show_to_all: boolean

  amount_transfer_id: number | null
  cash_to_gold_id: number | null
  stock_id: number | null
  amnt_transfer_from_head: boolean
  internal_type: string

  retailer_id: number | null
  retailer_ob_cash_balance: number | null
  retailer_ob_rtgs_balance: number | null

  txn_type: CashTxnEntryType
  bank_entry_date: string

  machine_vendor_id: number | null
  machine_vendor_ob_cash_balance: number | null
  machine_vendor_ob_rtgs_balance: number | null

  is_bill_cash: boolean
  is_payment_cash: boolean
  is_customer_affect: boolean
  is_need_receipt: boolean
  bill_payment_cash_type: BillPaymentCashType | ''
  partial_amount: number | null
  actual_amount: number | null
  receipt_cash_txn_id: number | null

  given_by_arithmetic_operation: ArithmeticOperation | ''
  given_to_arithmetic_operation: ArithmeticOperation | ''

  cash_loan_type: CashLoanType | ''
  per_gram_cash: number | null

  over_all_bill_id: number | null
  estimate_retailer_bill_id: number | null
  estimate_metal_bill_id: number | null

  is_admin_head_entry: boolean
  admin_head_txn_id: number | null
}

// ============================================================================
// WORKING CONTRACT — POST /cash-txn-details/in and /cash-txn-details/out
// (CashTxnDetailController::postIncome/postExpense, backed by
// CashTxnDetailService + StoreCashTxnDetailRequest). Matches the actual
// current cash_txn_details/cash_txn_images schema.
// ============================================================================

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
// the new schema (relations are NOT eager-loaded, unlike the old index()).
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
