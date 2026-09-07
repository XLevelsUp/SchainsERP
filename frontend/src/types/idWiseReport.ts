// GET /stock/reports/id-wise?stock_id= — StockDetailsController::
// getIdWiseReport -> ReportService::getIdWiseStockReport (PR #33).
//
// Traces a stock row back to its parent lot and lists every child
// transaction drawn from that lot (rows whose stock_in_id points at it).
// Cached server-side for an hour per stock_id.

export interface IdWiseStockSummary {
  queried_id: number
  queried_type: string
  item_name: string
}

export interface IdWiseLotDetails {
  lot_id: number
  item_name: string
  added_at: string
  original_grams: number
  purity: number
  touch: number
  current_balance: number
  lot_creator: string
}

export interface IdWiseQuantitySummary {
  total_received: number
  total_consumed: number
  reconciled_balance: number
  actual_balance: number
}

export interface IdWiseTransaction {
  id: number
  added_at: string
  item_name: string
  stock_type: string
  entry_type: string
  type: string
  grams: number
  touch: number
  mtouch: number
  wastage: number
  purity: number
  given_by: string
  given_to: string
  remarks: string | null
  item_remarks: string | null
  bill_id: number | null
}

export interface IdWiseReportResult {
  stock_summary: IdWiseStockSummary
  lot_details: IdWiseLotDetails
  quantity_summary: IdWiseQuantitySummary
  transactions: IdWiseTransaction[]
}
