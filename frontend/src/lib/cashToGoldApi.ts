import { api, type ApiResponse } from './api'
import { buildGoldConversionForm } from './multipartForm'
import type { CashToGoldFormValues, CashToGoldRecord } from '@/types'

const RESOURCE = '/cash-to-gold'

function toFields(form: CashToGoldFormValues) {
  return {
    head_id: form.head_id,
    customer_id: form.customer_id,
    total_cash: form.total_cash,
    per_gram_cash: form.per_gram_cash,
    total_grams: form.total_grams,
    touch: form.touch,
    purity: form.purity,
    item_id: form.item_id,
    amnt_transfer_to_head: form.amnt_transfer_to_head,
    remarks: form.remarks || null,
    retailer_id: form.retailer_id,
    // required_if:amnt_transfer_to_head,true on the backend — omitted
    // entirely (not just empty) when the head isn't being paid.
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

// CashToGoldController also defines index()/show()/destroy(), but only
// store() is routed in routes/api.php — create-only for now. Always sent
// as multipart since the endpoint accepts an optional images[] receipt
// upload (PR #15).
export const cashToGoldApi = {
  create: (form: CashToGoldFormValues, images: File[] = []) =>
    api
      .postForm<ApiResponse<CashToGoldRecord>>(RESOURCE, buildGoldConversionForm(toFields(form), images))
      .then((r) => r.data),
}
