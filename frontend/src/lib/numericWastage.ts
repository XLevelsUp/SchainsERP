// Numeric wastage figures, shared by Numeric Wastage In, Numeric Wastage Out
// and the correction modal so the three always agree. Same formulas as
// StockInService::createNumericWasteIn / StockOutService::createNumericWaste,
// which compute them when the screens don't send them.

interface NumericWastageFigures {
  grams: number | null
  touch: number | null
  no_of_pcs: number | null
  waste_total: number | null
}

// WValue: total wastage weight = pieces × wastage per piece.
export function numericWastageWeight(row: NumericWastageFigures): number {
  return (row.no_of_pcs ?? 0) * (row.waste_total ?? 0)
}

// Total (purity) = (grams + wastage weight) × touch ÷ 100.
export function numericWastagePurity(row: NumericWastageFigures): number {
  return (((row.grams ?? 0) + numericWastageWeight(row)) * (row.touch ?? 0)) / 100
}
