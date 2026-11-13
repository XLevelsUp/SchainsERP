import type { CashTxnSourceType } from './cashTxnDetail'

// Gold entries (Cash to Gold, Gold to Cash, Purchase Gold, Sale Gold) as the
// correction endpoints see them (BACKEND_TODO §17 phase 3, heads only).
//
// A correction never edits the original. POST /gold-entries/{id}/correct
// reverses it (the original with its money and weight negated) and posts the
// corrected entry, both dated today, and links the three rows:
//   original.corrected_by_id → corrected, reversal.reversal_of_id → original,
//   corrected.corrects_id → original.
// The parties, item and type can't change.

// Which screen created the entry, from cash_to_gold.type.
export type GoldEntryKind = 'cash_to_gold' | 'gold_to_cash' | 'purchase' | 'sale'

export interface GoldEntryAmountSource {
  source: CashTxnSourceType
  bank_id: number | null
  // decimal string
  amount: string
}

// The input the entry was saved with, rebuilt by the backend, to prefill the
// correction form. Numbers arrive as decimal strings.
export interface GoldEntryInput {
  head_id: number
  customer_id: number
  total_cash: string
  per_gram_cash: string
  total_grams: string
  touch: string
  remarks: string | null
  retailer_id: number | null
  is_rate_avg: boolean
  amount_sources: GoldEntryAmountSource[]
  // Gold to Cash only: the pure gold (it always books item 3).
  total_gold?: string
  // Everything but Gold to Cash:
  purity?: string
  item_id?: number
  amnt_transfer_to_head?: boolean
  // Purchase and Sale:
  type?: string
  taken_total_cash?: string | null
  taken_total_grams?: string | null
  taken_purity?: string | null
  is_live?: boolean
  bank_entry_date?: string | null
  // Sale only: the lot it drew from.
  stock_in_id?: number | null
}

export interface GoldEntryRecordRef {
  cash_to_gold_id: number
  type: string
  reversal_of_id: number | null
  corrects_id: number | null
  corrected_by_id: number | null
}

// GET /gold-entries/{id}
export interface GoldEntryDetail {
  record: GoldEntryRecordRef
  kind: GoldEntryKind | null
  input: GoldEntryInput | null
  editable_fields: string[]
  // Why it can't be corrected (a reversal, already corrected, unknown
  // type), or null.
  correct_blocked_reason: string | null
}

// POST /gold-entries/{id}/correct
export interface GoldCorrectionResult {
  original: GoldEntryRecordRef
  reversal: GoldEntryRecordRef
  corrected: GoldEntryRecordRef
}

// Passed to a gold modal to open it in "correct" mode.
export interface GoldCorrection {
  goldEntryId: number
  input: GoldEntryInput
}

// Everything GoldCorrectionHost needs to open the right modal, as emitted by
// the cash entry detail modal.
export interface GoldCorrectionRequest extends GoldCorrection {
  kind: GoldEntryKind
  headName: string
  customerName: string
}
