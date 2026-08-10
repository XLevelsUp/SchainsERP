import { api, type ApiResponse } from './api'
import type { GoldToCashFormValues, GoldToCashRecord } from '@/types'

const RESOURCE = '/gold-to-cash'

function toPayload(form: GoldToCashFormValues) {
  return {
    head_id: form.head_id,
    customer_id: form.customer_id,
    total_gold: form.total_gold,
    touch: form.touch,
    total_grams: form.total_grams,
    per_gram_cash: form.per_gram_cash,
    total_cash: form.total_cash,
    remarks: form.remarks || null,
    retailer_id: form.retailer_id,
    amount_sources: form.amount_sources.map((s) => ({
      source: s.source,
      bank_id: s.source === 'BANK' ? s.bank_id : null,
      amount: s.amount,
    })),
  }
}

// GoldToCashController also defines index()/show()/destroy(), but only
// store() is routed in routes/api.php — create-only for now.
export const goldToCashApi = {
  create: (form: GoldToCashFormValues) =>
    api.post<ApiResponse<GoldToCashRecord>>(RESOURCE, toPayload(form)).then((r) => r.data),
}
