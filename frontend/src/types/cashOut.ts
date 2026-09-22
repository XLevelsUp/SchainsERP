// POST /stock/cash-out — StockDetailsController::postCash ->
// StockOutService::createCashOut. Implemented in PR #31, routed in PR #42.
//
// Filed under /stock but it writes cash, not stock: one cash_txn_details row
// of type EXPENSE, payment_method CASH_ON_HAND, moving rak_cash_balance from
// the acting user to `given_to`.
//
// The sender is always the authenticated (or X-User-ID) user — createCashOut
// resolves it from $addedBy, and there is no field for it. A head cannot
// record a transfer between two other people through this endpoint.

export interface CashOutFormValues {
  given_to: number | null
  // `numeric|gt:0` server-side. Unlike the Cash Management endpoints this one
  // has no 2-decimal regex cap.
  amount: number | null
  remarks: string
}

// The created CashTxnDetail, as returned in `data`. Only the fields this app
// reads are declared; the response carries the full row.
export interface CashOutResult {
  id: number
  type: string
  sender_id: number
  recipient_id: number
  amount: string
  payment_method: string
  remarks: string | null
}
