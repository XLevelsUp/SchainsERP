import { api, type ApiResponse } from './api'
import type { SaleGoldCreateResult, SaleGoldFormValues, SaleGoldRecord } from '@/types'

const RESOURCE = '/sale-gold'

function toPayload(form: SaleGoldFormValues) {
  return {
    head_id: form.head_id,
    customer_id: form.customer_id,
    item_id: form.item_id,
    stock_id: form.stock_id,
    grams: form.grams,
    touch: form.touch,
    purity: form.purity,
    per_gram_cash: form.per_gram_cash,
    total_cash: form.total_cash,
    amount_transfer_to_head: form.amount_transfer_to_head,
    remarks: form.remarks || null,
    opening_account_balance: form.opening_account_balance,
    opening_user_balance: form.opening_user_balance,
    amount_sources: form.amount_sources.map((source) => ({
      source_type: source.source_type,
      amount: source.amount,
      bank_id: source.source_type === 'BANK' ? source.bank_id : null,
      bank_name: source.source_type === 'BANK' ? source.bank_name : null,
      opening_bank_account_balance:
        source.source_type === 'BANK' ? source.opening_bank_account_balance : null,
      opening_bank_user_balance:
        source.source_type === 'BANK' ? source.opening_bank_user_balance : null,
    })),
  }
}

// Backend only implements index/store/show — update/destroy are routed
// (apiResource) but throw "undefined method" on the server, so they are
// intentionally not exposed here.
export const saleGoldApi = {
  list: () => api.get<ApiResponse<SaleGoldRecord[]>>(RESOURCE).then((r) => r.data),

  get: (id: number) => api.get<ApiResponse<SaleGoldRecord>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (form: SaleGoldFormValues) =>
    api.post<ApiResponse<SaleGoldCreateResult>>(RESOURCE, toPayload(form)).then((r) => r.data),
}
