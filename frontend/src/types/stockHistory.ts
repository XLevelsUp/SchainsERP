// GET /stock-details/history — paginated transaction history for a head,
// with grand totals calculated across the full filtered dataset (not just
// the current page).
export interface StockHistoryRow {
  id: number
  item_name: string
  stock_type: 'IN' | 'OUT'
  grams: number
  pcs: number
  touch: number
  wastage: number
  purity: number
  user_id: number | null
  user: string
  remarks: string
}

export interface StockHistoryTotals {
  grams: number
  purity: number
  pcs: number
}

export interface StockHistoryResult {
  totals: StockHistoryTotals
  transactions: {
    current_page: number
    data: StockHistoryRow[]
    last_page: number
    total: number
  }
}

export interface StockHistoryQuery {
  head_id?: number
  employee_id?: number
  item_id?: number
  type?: 'IN' | 'OUT'
  from_date?: string
  to_date?: string
  page_size?: number
  page?: number
}
