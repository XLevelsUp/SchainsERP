// GET /stock-details/head-stocks?user_id=&head_txn_from_date=&head_txn_from_time=
// Item-wise stock balance for a head user, plus cash balance and active
// orders weight. With no date/time filter it returns live totals; passing
// head_txn_from_date (and optionally head_txn_from_time) replays the
// balance as of that moment instead.
export interface HeadStockItem {
  item_id: number
  item_name: string
  grams: number
  percentage: number
  purity: number
}

export interface HeadStockSummary {
  items: HeadStockItem[]
  totals: {
    grams: number
    purity: number
  }
  cash_balance: number
  active_orders: number
}

export interface HeadStockFilters {
  head_txn_from_date?: string
  head_txn_from_time?: string
}
