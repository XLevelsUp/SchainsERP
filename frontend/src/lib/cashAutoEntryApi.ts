import { api, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import type { CashAutoEntryFormValues, CashAutoEntryResultRow } from '@/types'

const RESOURCE = '/cash-txn-details/auto-entry'

function toFields(form: CashAutoEntryFormValues) {
  return {
    type: form.type,
    head_id: form.head_id,
    cash_user_id: form.cash_user_id,
    transactions: form.transactions.map((t) => ({
      amount: t.amount,
      category_id: t.category_id,
      remarks: t.remarks || null,
      added_at: t.added_at || null,
      images: t.images,
    })),
  }
}

export const cashAutoEntryApi = {
  create: (form: CashAutoEntryFormValues, actingUserId: number) =>
    api
      .postForm<ApiResponse<CashAutoEntryResultRow[]>>(RESOURCE, buildMultipartForm(toFields(form)), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),
}
