import type { CashTxnSourceType } from './cashTxnDetail'

// Balance snapshot embedded on the head/customer relation (CashToGoldResource
// whenLoaded blocks) — distinct field names from UserDetail
// (rak_cash_balance/rak_rtgs_balance), so kept as its own shape.
export interface CashToGoldPartyBalance {
  user_id: number
  name: string
  cash_balance: number
  rtgs_balance: number
  grams_grand_total: number
  purity_grand_total: number
}

export interface CashToGoldAmountSource {
  id: number
  source: CashTxnSourceType
  bank_id: number | null
  amount: number
  cash_txn_id: number | null
}

// A row as returned by POST /cash-to-gold (CashToGoldResource).
// CashToGoldController also defines index()/show()/destroy(), but only
// store() is actually routed in routes/api.php — create-only for now.
export interface CashToGoldRecord {
  cash_to_gold_id: number
  type: string
  head_id: number
  customer_id: number
  total_cash: number
  per_gram_cash: number
  total_grams: number
  touch: number
  purity: number
  item_id: number
  stock_id: number | null
  amnt_transfer_to_head: boolean
  ob_grams: number
  ob_purity: number
  remarks: string | null
  is_rate_avg: boolean
  retailer_id: number | null
  added_at?: string
  head?: CashToGoldPartyBalance
  customer?: CashToGoldPartyBalance
  item?: { item_id: number; item_name: string }
  amount_sources?: CashToGoldAmountSource[]
  cash_txn_ids?: number[]
}

// One payment-source row in the create form.
export interface CashToGoldAmountSourceInput {
  source: CashTxnSourceType
  bank_id: number | null
  amount: number | null
}

// The full payload POST /cash-to-gold accepts (StoreCashToGoldRequest).
// amount_sources is only required (and only sent) when amnt_transfer_to_head
// is true — required_if on the backend.
export interface CashToGoldFormValues {
  head_id: number | null
  customer_id: number | null
  total_cash: number | null
  per_gram_cash: number | null
  total_grams: number | null
  touch: number | null
  purity: number | null
  item_id: number | null
  amnt_transfer_to_head: boolean
  remarks: string
  retailer_id: number | null
  amount_sources: CashToGoldAmountSourceInput[]
}
