import type { BankDetail } from './bankDetail'
import type { UserDetail } from './userDetail'

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
  givenByUser: UserDetail | null
  givenToUser: UserDetail | null
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
