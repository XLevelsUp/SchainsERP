// GET /report/cash-transactions-obcb (PR #21, ReportController::getCashTransactionsObcb).
// Legacy "Cash Transactions" OB/CB report replication — see
// CashTransactionReportResource::toArray() for the exact shape.
//
// `bank_entry_from_date`/`bank_entry_to_date` were previously left unexposed
// because `cash_txn_details.bank_entry_date` didn't exist. It does now
// (migration 2026_08_17_151809), ReportController:87-91 filters on it, and
// CashTransactionReportResource:70 formats it as `d-M-Y` — so both params are
// wired below, and the response's `bank_entry_date` is a real date whenever
// the column is populated ("-" now means null for that row, not "unsupported").
//
// Still NOT exposed: `cash_main_category_id`. That column does exist on
// cash_categories, and the endpoint accepts it as an id or a comma-separated
// list — but it is a bare nullable bigint with no FK, no `cash_main_categories`
// table and no names anywhere in the backend, so there is nothing to label a
// filter's options with. Exposing it would mean showing operators raw integers
// or inventing names client-side. Needs a backend lookup first.
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
  // Filters on cash_txn_details.bank_entry_date — the date the entry was
  // reconciled against the bank statement, which is independent of the
  // transaction's own date (from_date/to_date above). Compared with
  // whereDate, so these are plain Y-m-d with no time component.
  bank_entry_from_date?: string
  bank_entry_to_date?: string
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
