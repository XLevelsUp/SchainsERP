export type SaleGoldSourceType = 'CASH_ON_HAND' | 'BANK'

// A row as returned by GET /sale-gold and GET /sale-gold/{id} — the
// cash_to_gold table filtered to type=SALE_GOLD, with amountSources loaded.
export interface SaleGoldRecord {
  cash_to_gold_id: number
  type: 'SALE_GOLD'
  head_id: number
  customer_id: number | null
  total_cash: number
  per_gram_cash: number
  total_grams: number
  touch: number
  purity: number
  item_id: number
  stock_id: number | null
  added_at?: string
  added_by: number
  amnt_transfer_to_head: boolean
  taken_total_cash: number
  taken_total_grams: number
  taken_purity: number
  ob_grams: number | null
  ob_purity: number | null
  remarks: string | null
  retailer_id: number | null
  is_rate_avg: boolean
  partial_amount: number | null
  balance_amount: number | null
  adjust_cash: number | null
  amountSources: SaleGoldAmountSource[]
}

export interface SaleGoldAmountSource {
  id: number
  cash_to_gold_id: number
  cash_txn_id: number
  souce_type: SaleGoldSourceType
  bank_id: number | null
  amount: number
  added_at?: string
}

// One payment-source row in the create form.
export interface SaleGoldAmountSourceInput {
  source_type: SaleGoldSourceType
  amount: number | null
  bank_id: number | null
  bank_name: string
  opening_bank_account_balance: number | null
  opening_bank_user_balance: number | null
}

// The full payload POST /sale-gold accepts.
export interface SaleGoldFormValues {
  head_id: number | null
  customer_id: number | null
  item_id: number | null
  stock_id: number | null
  grams: number | null
  touch: number | null
  purity: number | null
  per_gram_cash: number | null
  total_cash: number | null
  amount_transfer_to_head: boolean
  remarks: string
  opening_account_balance: number | null
  opening_user_balance: number | null
  amount_sources: SaleGoldAmountSourceInput[]
}

// Shape of the `data` object returned by a successful POST /sale-gold.
export interface SaleGoldCreateResult {
  cash_to_gold: SaleGoldRecord
  source_results: Array<{
    source_type: SaleGoldSourceType
    bank_id?: number
    bank_name?: string
    amount: number
    closing_balance?: number
    closing_bank_balance?: number
  }>
  user: {
    user_id: number
    name: string
    rak_cash_balance: number
  }
}
