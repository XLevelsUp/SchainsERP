import type { CashTxnSourceType } from './cashTxnDetail'
import type { CashToGoldAmountSourceInput, CashToGoldPartyBalance } from './cashToGold'

export interface GoldToCashAmountSource {
  id: number
  source: CashTxnSourceType
  bank_id: number | null
  amount: number
  cash_txn_id: number | null
}

// A row as returned by POST /gold-to-cash (GoldToCashResource). Same
// cash_to_gold table as Cash To Gold/Sale Gold, filtered to type=GOLD_TO_CASH.
// GoldToCashController also defines index()/show()/destroy(), but only
// store() is actually routed in routes/api.php — create-only for now.
export interface GoldToCashRecord {
  cash_to_gold_id: number
  type: string
  head_id: number
  customer_id: number
  // Stored swapped in the DB (total_gold -> purity column, total_grams ->
  // total_grams column) — GoldToCashResource already remaps these back to
  // frontend-friendly names, see the comment there.
  total_gold: number
  total_grams: number
  touch: number
  per_gram_cash: number
  total_cash: number
  ob_grams: number
  ob_purity: number
  remarks: string | null
  stock_id: number | null
  is_rate_avg: boolean
  retailer_id: number | null
  added_at?: string
  head?: CashToGoldPartyBalance
  customer?: CashToGoldPartyBalance
  amount_sources?: GoldToCashAmountSource[]
  cash_txn_ids?: number[]
}

// The full payload POST /gold-to-cash accepts (StoreGoldToCashRequest).
// amount_sources is always required — the head always pays out
// (amnt_transfer_to_head=1 is hardcoded server-side, no toggle needed here).
export interface GoldToCashFormValues {
  head_id: number | null
  customer_id: number | null
  total_gold: number | null
  touch: number | null
  total_grams: number | null
  per_gram_cash: number | null
  total_cash: number | null
  remarks: string
  retailer_id: number | null
  amount_sources: CashToGoldAmountSourceInput[]
}
