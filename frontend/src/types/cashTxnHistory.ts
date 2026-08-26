// GET /cash-txn-details/in-history and /out-history (PR #17, reshaped in
// PR #18 to go through CashTxnHistoryResource::collection()->response()).
// Rows are now a constrained projection, not the full cash_txn_details
// record — see CashTxnHistoryResource::toArray(). No images relation, so
// receipts don't show up here.
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

// Partial user projection returned by CashTxnHistoryResource — not the full
// UserDetail record (no balances, roles, etc.).
export interface CashTxnHistoryUserRef {
  user_id: number
  name: string
  user_name: string
}

export interface CashTxnHistoryCategoryRef {
  category_id: number
  category_name: string
}

// Note the field is bank_name here (CashTxnHistoryResource maps it from
// BankDetail.account_name), not account_name like the full BankDetail type.
export interface CashTxnHistoryBankRef {
  bank_id: number
  bank_name: string
}

export interface CashTxnHistoryRow {
  txn_id: number
  type: CashTxnHistoryType
  amount: string
  remarks: string | null
  payment_method: 'CASH_ON_HAND' | 'BANK'
  remainder: string | null
  remainder_at: string | null
  is_hide: boolean
  added_at: string | null
  given_by_user: CashTxnHistoryUserRef | null
  given_to_user: CashTxnHistoryUserRef | null
  category: CashTxnHistoryCategoryRef | null
  bank: CashTxnHistoryBankRef | null
}

// Laravel's paginated ResourceCollection envelope (data/links/meta) — since
// PR #18 switched these two endpoints from raw ->paginate() to
// CashTxnHistoryResource::collection()->response()->getData(true). Distinct
// from CashCategoryListPage's custom {total_count, page_no, page_size,
// records} shape used elsewhere, and from the flat current_page/last_page/
// total shape this used before PR #18.
export interface CashTxnHistoryPage {
  data: CashTxnHistoryRow[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

export interface CashTxnHistoryQuery {
  head_id?: number
  cash_user_id?: number
  from_date?: string
  to_date?: string
  per_page?: number
  page?: number
}
