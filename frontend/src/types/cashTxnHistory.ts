import type { BankDetail } from './bankDetail'
import type { CashCategory } from './cashCategory'
import type { UserDetail } from './userDetail'

// GET /cash-txn-details/in-history and /out-history (PR #17). Each row is a
// full cash_txn_details record with givenByUser/givenToUser/category/bank
// eager-loaded — no images relation, so receipts don't show up here.
//
// in-history filters type IN (INCOME, AUTO_ENTRY, CASH_TO_GOLD, SALE_GOLD).
// out-history filters type IN (EXPENSE, PURCHASE_GOLD, INTERNAL_TRANSFER,
// GOLD_TO_CASH). INTERNAL_TRANSFER is never actually written by any
// service (matches the disabled INTERNAL button). OUT_CASH_CONVERTER /
// IN_CASH_CONVERTER (Purchase/Sale Gold's alternate Type) are written but
// excluded from both filters, so those transactions never appear here —
// a backend gap, not a frontend bug.
export type CashTxnHistoryType =
  | 'EXPENSE'
  | 'INCOME'
  | 'AUTO_ENTRY'
  | 'PURCHASE_GOLD'
  | 'SALE_GOLD'
  | 'CASH_TO_GOLD'
  | 'GOLD_TO_CASH'
  | 'INTERNAL_TRANSFER'
  | 'OUT_CASH_CONVERTER'
  | 'IN_CASH_CONVERTER'

export interface CashTxnHistoryRow {
  txn_id: number
  type: CashTxnHistoryType
  sender_id: number
  recipient_id: number
  category_id: number | null
  amount: string
  payment_method: 'CASH_ON_HAND' | 'BANK'
  bank_account_id: number | null
  remarks: string | null
  added_by: number
  cash_to_gold_id: number | null
  balance_after_txn: string | null
  sender_opening_cash: string
  sender_opening_rtgs: string
  recipient_opening_cash: string
  recipient_opening_rtgs: string
  sender_closing_cash: string
  sender_closing_rtgs: string
  recipient_closing_cash: string
  recipient_closing_rtgs: string
  created_at: string
  updated_at: string
  given_by_user: UserDetail
  given_to_user: UserDetail
  category: CashCategory | null
  bank: BankDetail | null
}

// Laravel's native paginate() envelope — distinct from CashCategoryListPage's
// custom {total_count, page_no, page_size, records} shape used elsewhere.
export interface CashTxnHistoryPage {
  current_page: number
  data: CashTxnHistoryRow[]
  last_page: number
  per_page: number
  total: number
}

export interface CashTxnHistoryQuery {
  head_id?: number
  cash_user_id?: number
  from_date?: string
  to_date?: string
  per_page?: number
  page?: number
}
