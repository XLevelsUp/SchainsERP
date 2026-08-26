// GET /cash-txn-details/print-report?id={txn_id} (CashTxnDetailController::
// getPrintReport, single-transaction branch). A pre-formatted thermal-print
// payload, not a raw CashTxnDetail row — see the controller for the exact
// shape. `date` already comes formatted as "d-M-Y H:i:s".
//
// ob_amount/cb_amount read $transaction->sender_ob / ->sender_cb on the
// backend, but the model's real columns are sender_opening_cash /
// sender_closing_cash — those attributes don't exist, so both fields are
// always null today. Typed nullable and rendered as "—" until that's fixed
// backend-side.
export interface CashTxnPrintReceipt {
  heading: string
  given_by_name: string
  given_to_name: string
  date: string
  ob_label: string
  ob_amount: string | number | null
  bill_no: number
  amount: string | number
  cb_label: string
  cb_amount: string | number | null
  remarks: string | null
}
