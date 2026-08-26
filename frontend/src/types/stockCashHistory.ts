// GET /stock-details/cash-transaction-history (StockDetailsController::
// getCashTransactionHistory, added alongside PR #18's cash-txn-details
// in/out history). The gold/stock side of the same head/user pair — Purchase
// Gold, Sale Gold, Cash To Gold, Gold To Cash, and plain Stock In/Out all
// write stock_details rows here. Replaces the now-disabled
// stock-details/history (see StockCashHistoryResource for the exact field
// list — it's a flat projection, not the raw stock_details record).
export type StockCashHistoryType = 'IN' | 'OUT'

export interface StockCashHistoryRow {
  id: number
  item: string | null
  stock_type: StockCashHistoryType
  grams: number
  no_of_pcs: number
  touch: number
  wastage: number
  purity: number
  user: string
  remarks: string | null
}

export interface StockCashHistoryQuery {
  head_id?: number
  cash_user_id?: number
  per_page?: number
  page?: number
}

// Same paginated-resource envelope as CashTxnHistoryPage — see that type's
// comment for why this isn't a flat current_page/last_page/total shape.
export interface StockCashHistoryPage {
  data: StockCashHistoryRow[]
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
