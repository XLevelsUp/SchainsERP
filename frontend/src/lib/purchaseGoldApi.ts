import { api, type ApiResponse } from './api'
import { buildGoldConversionForm } from './multipartForm'
import type { PurchaseGoldFormValues, PurchaseGoldRecord } from '@/types'

const RESOURCE = '/purchase-gold'

function toFields(form: PurchaseGoldFormValues) {
  return {
    type: form.type,
    head_id: form.head_id,
    customer_id: form.customer_id,
    total_cash: form.total_cash,
    per_gram_cash: form.per_gram_cash,
    total_grams: form.total_grams,
    touch: form.touch,
    purity: form.purity,
    taken_total_cash: form.taken_total_cash,
    taken_total_grams: form.taken_total_grams,
    taken_purity: form.taken_purity,
    item_id: form.item_id,
    amnt_transfer_to_head: form.amnt_transfer_to_head,
    remarks: form.remarks || null,
    retailer_id: form.retailer_id,
    // required_if:amnt_transfer_to_head,true on the backend — omitted
    // entirely (not just empty) when the head isn't the one paying.
    ...(form.amnt_transfer_to_head
      ? {
          amount_sources: form.amount_sources.map((s) => ({
            source: s.source,
            bank_id: s.source === 'BANK' ? s.bank_id : null,
            amount: s.amount,
          })),
        }
      : {}),
  }
}

// PurchaseGoldController only routes store() — create-only, no list/get.
// Always sent as multipart since the endpoint accepts an optional images[]
// receipt upload.
export const purchaseGoldApi = {
  create: (form: PurchaseGoldFormValues, images: File[] = []) =>
    api
      .postForm<ApiResponse<PurchaseGoldRecord>>(
        RESOURCE,
        buildGoldConversionForm(toFields(form), images),
      )
      .then((r) => r.data),
}
