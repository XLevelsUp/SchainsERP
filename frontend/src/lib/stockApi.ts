import { api, type ApiResponse } from './api'
import type {
  StockOutFormValues,
  StockOutResult,
  StockInFormValues,
  StockInResultRow,
  GmsInFormValues,
  GmsInResultRow,
  NumericWastageInFormValues,
  NumericWastageInResultRow,
} from '@/types'

const RESOURCE = '/stock'

function toStockOutPayload(form: StockOutFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    retailer_id: form.retailer_id,
    added_at: form.added_at || null,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
    })),
  }
}

function toStockInPayload(form: StockInFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    added_at: form.added_at || null,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
    })),
  }
}

function toGmsInPayload(form: GmsInFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    added_at: form.added_at || null,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      stone: item.stone ?? 0,
      thread: item.thread ?? 0,
      wastage: item.wastage ?? 0,
      hall_mark: item.hall_mark,
      mtouch: item.mtouch ?? 0,
      mtouch_wastage: item.mtouch_wastage ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
    })),
  }
}

function toNumericWastageInPayload(form: NumericWastageInFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    added_at: form.added_at || null,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      no_of_pcs: item.no_of_pcs,
      amount_pcs: item.amount_pcs ?? 0,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
    })),
  }
}

export const stockApi = {
  // There is no auth/session on this backend yet — StockDetailsController
  // resolves the acting user from an X-User-ID header (falling back to a
  // hardcoded id if it's missing), so it must be sent explicitly on every
  // stock write.
  postStockOut: (form: StockOutFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<StockOutResult>>(`${RESOURCE}/out`, toStockOutPayload(form), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),

  postStockIn: (form: StockInFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<StockInResultRow[]>>(`${RESOURCE}/in`, toStockInPayload(form), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),

  postGmsIn: (form: GmsInFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<GmsInResultRow[]>>(`${RESOURCE}/gms-in`, toGmsInPayload(form), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),

  postNumericWasteIn: (form: NumericWastageInFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<NumericWastageInResultRow[]>>(
        `${RESOURCE}/numeric-waste-in`,
        toNumericWastageInPayload(form),
        { 'X-User-ID': String(actingUserId) },
      )
      .then((r) => r.data),
}
