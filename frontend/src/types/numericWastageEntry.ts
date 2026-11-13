// Numeric wastage lines (in / out) as the correction endpoints see them
// (BACKEND_TODO §17, heads only).
//
// A correction never edits the original. POST
// /numeric-wastage-entries/{direction}/{id}/correct reverses the line (grams,
// pieces, wastage weight, purity and cash negated) and posts the corrected
// line, both dated today, and links the three stock rows:
//   original.corrected_by_id → corrected, reversal.reversal_of_id → original,
//   corrected.corrects_id → original.
// {id} is the line's stock row id, the "ID : N" in its cash entry's remarks.
// The parties and the item can't change.

// in = Numeric Wastage In (stock_in_details), out = Numeric Wastage Out
// (stock_details).
export type NumericWastageDirection = 'in' | 'out'

// Which line a cash entry belongs to (CashTxnDetailRow.numeric_wastage).
export interface NumericWastageRef {
  direction: NumericWastageDirection
  id: number
}

// The line the entry was saved with, rebuilt by the backend, to prefill the
// correction form. Numbers arrive as decimal strings.
export interface NumericWastageEntryInput {
  given_by: number
  given_to: number
  item_id: number
  grams: string
  touch: string
  no_of_pcs: string
  amount_pcs: string
  // Wastage per piece.
  waste_total: string
  waste_id: number | null
  remarks: string | null
  item_remarks: string | null
}

// The stock row; its key is stock_in_detail_id (in) or stock_id (out).
export interface NumericWastageStockRef {
  stock_in_detail_id?: number
  stock_id?: number
  reversal_of_id: number | null
  corrects_id: number | null
  corrected_by_id: number | null
}

// GET /numeric-wastage-entries/{direction}/{id}
export interface NumericWastageEntryDetail {
  direction: NumericWastageDirection
  record: NumericWastageStockRef
  input: NumericWastageEntryInput | null
  editable_fields: string[]
  // Why it can't be corrected (a reversal, already corrected), or null.
  correct_blocked_reason: string | null
}

// POST body: any of the editable fields.
export interface NumericWastageCorrectionPayload {
  grams: number
  touch: number
  no_of_pcs: number
  amount_pcs: number
  waste_total: number
  remarks: string | null
  item_remarks: string | null
}

// POST /numeric-wastage-entries/{direction}/{id}/correct
export interface NumericWastageCorrectionResult {
  direction: NumericWastageDirection
  original: NumericWastageStockRef
  reversal: NumericWastageStockRef
  corrected: NumericWastageStockRef
}

// Everything the correction modal needs, as emitted by the cash entry
// detail modal.
export interface NumericWastageCorrectionRequest extends NumericWastageRef {
  input: NumericWastageEntryInput
  givenByName: string
  givenToName: string
}
