import { api, type ApiResponse } from './api'
import type { CashTxnDetail, CashTxnDetailFormValues } from '@/types'

const RESOURCE = '/cash-txn-details'

// Builds the JSON payload the backend's store()/update() validators expect.
// Optional fields are sent as explicit `null` rather than omitted or '' —
// Laravel's `nullable` rule accepts real JSON null cleanly (unlike '' for
// numeric/enum rules), so this avoids ever tripping validation on blanks.
function toPayload(form: CashTxnDetailFormValues) {
  const payload: Record<string, unknown> = {
    type: form.type,
    given_to: form.given_to,
    given_by: form.given_by,
    category_id: form.category_id,
    amount: form.amount,

    opening_account_balance: form.opening_account_balance,
    opening_user_balance: form.opening_user_balance,

    souce_type: form.souce_type,

    remarks: form.remarks || null,
    remainder: form.remainder || null,
    remainder_at: form.remainder_at || null,

    added_by: form.added_by,

    is_active: form.is_active,
    is_hidden: form.is_hidden,
    is_show_to_all: form.is_show_to_all,

    amount_transfer_id: form.amount_transfer_id,
    cash_to_gold_id: form.cash_to_gold_id,
    stock_id: form.stock_id,
    amnt_transfer_from_head: form.amnt_transfer_from_head,
    internal_type: form.internal_type || null,

    retailer_id: form.retailer_id,
    retailer_ob_cash_balance: form.retailer_ob_cash_balance,
    retailer_ob_rtgs_balance: form.retailer_ob_rtgs_balance,

    txn_type: form.txn_type,
    bank_entry_date: form.bank_entry_date || null,

    machine_vendor_id: form.machine_vendor_id,
    machine_vendor_ob_cash_balance: form.machine_vendor_ob_cash_balance,
    machine_vendor_ob_rtgs_balance: form.machine_vendor_ob_rtgs_balance,

    is_bill_cash: form.is_bill_cash,
    is_payment_cash: form.is_payment_cash,
    is_customer_affect: form.is_customer_affect,
    is_need_receipt: form.is_need_receipt,
    bill_payment_cash_type: form.bill_payment_cash_type || null,
    partial_amount: form.partial_amount,
    actual_amount: form.actual_amount,
    receipt_cash_txn_id: form.receipt_cash_txn_id,

    given_by_arithmetic_operation: form.given_by_arithmetic_operation || null,
    given_to_arithmetic_operation: form.given_to_arithmetic_operation || null,

    cash_loan_type: form.cash_loan_type || null,
    per_gram_cash: form.per_gram_cash,

    over_all_bill_id: form.over_all_bill_id,
    estimate_retailer_bill_id: form.estimate_retailer_bill_id,
    estimate_metal_bill_id: form.estimate_metal_bill_id,

    is_admin_head_entry: form.is_admin_head_entry,
    admin_head_txn_id: form.admin_head_txn_id,
  }

  // bank_id/bank_name/opening_bank_*_balance are only required (and only
  // meaningful) when souce_type is BANK — required_if on the backend.
  if (form.souce_type === 'BANK') {
    payload.bank_id = form.bank_id
    payload.bank_name = form.bank_name
    payload.opening_bank_account_balance = form.opening_bank_account_balance
    payload.opening_bank_user_balance = form.opening_bank_user_balance
  } else {
    payload.opening_bank_account_balance = null
    payload.opening_bank_user_balance = null
  }

  return payload
}

export const cashTxnDetailsApi = {
  list: () => api.get<ApiResponse<CashTxnDetail[]>>(RESOURCE).then((r) => r.data),

  get: (id: number) =>
    api.get<ApiResponse<CashTxnDetail>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (form: CashTxnDetailFormValues) =>
    api.post<ApiResponse<CashTxnDetail>>(RESOURCE, toPayload(form)).then((r) => r.data),

  update: (id: number, form: CashTxnDetailFormValues) =>
    api.put<ApiResponse<CashTxnDetail>>(`${RESOURCE}/${id}`, toPayload(form)).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),

  addImages: (id: number, files: File[]) => {
    const formData = new FormData()
    files.forEach((file) => formData.append('images[]', file))
    return api
      .postForm<ApiResponse<CashTxnDetail>>(`${RESOURCE}/${id}/images`, formData)
      .then((r) => r.data)
  },

  deleteImage: (imageId: number) =>
    api.delete<ApiResponse<null>>(`/cash-txn-images/${imageId}`),
}
