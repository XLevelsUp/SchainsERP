import type { CashTxnSourceType } from './cashTxnDetail'
import type { CashToGoldPartyBalance } from './cashToGold'

// PurchaseGoldService branches real accounting behavior on this: HEAD is
// the standard "head buys gold from customer" flow (stock IN); OUT_CASH_
// CONVERTER moves gold out to a customer/retailer without a purchase
// (stock OUT) — a distinct, unrelated use of the same endpoint.
export type PurchaseGoldType = 'HEAD' | 'OUT_CASH_CONVERTER'

export interface PurchaseGoldAmountSource {
  id: number
  source: CashTxnSourceType
  bank_id: number | null
  amount: number
  cash_txn_id: number | null
}

// A row as returned by POST /purchase-gold (PurchaseGoldResource).
// PurchaseGoldController only routes store() — create-only, no list/get.
export interface PurchaseGoldRecord {
  cash_to_gold_id: number
  type: PurchaseGoldType
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
  taken_total_cash: number
  taken_total_grams: number
  taken_purity: number
  ob_grams: number
  ob_purity: number
  remarks: string | null
  retailer_id: number | null
  is_rate_avg: boolean
  added_at?: string
  head?: CashToGoldPartyBalance
  customer?: CashToGoldPartyBalance
  amount_sources?: PurchaseGoldAmountSource[]
  cash_txn_ids?: number[]
}

// One payment-source row in the create form.
export interface PurchaseGoldAmountSourceInput {
  source: CashTxnSourceType
  bank_id: number | null
  amount: number | null
}

// The full payload POST /purchase-gold accepts (StorePurchaseGoldRequest).
// taken_total_cash/taken_total_grams/taken_purity (partial-delivery
// tracking) default to the full totals on the backend when omitted —
// optional here so callers that don't drive a "Taken" UI (the standalone
// PurchaseGoldView page) can still omit them and get that default.
// PurchaseGoldModal.vue sends them explicitly, auto-filled to match the
// full amount and staying editable, mirroring the legacy dialog's Taken
// fields.
export interface PurchaseGoldFormValues {
  type: PurchaseGoldType
  head_id: number | null
  customer_id: number | null
  total_cash: number | null
  per_gram_cash: number | null
  total_grams: number | null
  touch: number | null
  purity: number | null
  taken_total_cash?: number | null
  taken_total_grams?: number | null
  taken_purity?: number | null
  item_id: number | null
  amnt_transfer_to_head: boolean
  remarks: string
  retailer_id: number | null
  amount_sources: PurchaseGoldAmountSourceInput[]
}
