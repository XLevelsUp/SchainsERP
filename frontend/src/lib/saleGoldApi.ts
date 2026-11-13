import { api, type ApiResponse } from './api'
import { goldEntriesApi } from './goldEntriesApi'
import { buildGoldConversionForm } from './multipartForm'
import type { SaleGoldFormValues, SaleGoldRecord } from '@/types'

const RESOURCE = '/sale-gold'

function toFields(form: SaleGoldFormValues) {
  return {
    type: form.type,
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
    stock_in_id: form.stock_in_id,
    // required_if:amnt_transfer_to_head,true on the backend — omitted
    // entirely (not just empty) when the head isn't the one paid.
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

// SaleGoldController only routes store() as of PR #15 (the old apiResource
// index/show/update/destroy routes are gone) — create-only. Always sent as
// multipart since the endpoint accepts an optional images[] receipt upload.
export const saleGoldApi = {
  create: (form: SaleGoldFormValues, images: File[] = []) =>
    api
      .postForm<ApiResponse<SaleGoldRecord>>(RESOURCE, buildGoldConversionForm(toFields(form), images))
      .then((r) => r.data),

  // Heads only: reverse gold entry `goldEntryId` and post these values
  // instead (BACKEND_TODO §17 phase 3). Same fields as a new entry, as JSON.
  correct: (goldEntryId: number, form: SaleGoldFormValues) =>
    goldEntriesApi.correct(goldEntryId, toFields(form)),
}
