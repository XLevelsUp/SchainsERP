// GET /report/cash-transactions-obcb (PR #21, ReportController::getCashTransactionsObcb).
// Legacy "Cash Transactions" OB/CB report replication — see
// CashTransactionReportResource::toArray() for the exact shape.
//
// Note: `cash_main_category_id` and `bank_entry_from_date`/`bank_entry_to_date`
// query params exist on the backend but reference DB columns that were never
// migrated (cash_categories has no cash_main_category_id, cash_txn_details has
// no bank_entry_date) — both 500 if used, so they're intentionally NOT exposed
// here. `bank_entry_date` in the response is always "-" for the same reason.
export type CashTransactionReportType =
  | 'EXPENSE'
  | 'INCOME'
  | 'AUTO_ENTRY'
  | 'PURCHASE_GOLD'
  | 'SALE_GOLD'
  | 'CASH_TO_GOLD'
  | 'GOLD_TO_CASH'
  | 'INTERNAL_TRANSFER'
  | 'OUT_CASH_CONVERTER'
  | 'IN_CASH_CONVERTER'

export interface CashTransactionReportRow {
  id: number
  date: string
  bank_entry_date: string
  name: string
  category_name: string
  type_label: string
  source_type: string
  opening_balance: number
  amount: number
  closing_balance: number
  remarks: string | null
}

export interface CashTransactionReportQuery {
  category_id?: number
  type?: CashTransactionReportType
  bank_id?: number
  from_date?: string
  to_date?: string
  page_size?: number
  page?: number
  is_all?: boolean
}

// Distinct envelope from ApiResponse<T> — this endpoint wraps its payload in
// `parameters` rather than `data`.
export interface CashTransactionReportResponse {
  success: boolean
  message: string
  parameters: {
    count: number
    content: CashTransactionReportRow[]
  }
}
