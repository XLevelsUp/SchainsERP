import { api, type ApiResponse } from './api'
import type {
  StockOutFormValues,
  StockOutResult,
  StockInFormValues,
  StockInResultRow,
  GmsInFormValues,
  GmsInResultRow,
  GmsOutFormValues,
  GmsOutResultRow,
  NumericWastageInFormValues,
  NumericWastageInResultRow,
  ItemChangeFormValues,
  ItemChangeResultRow,
  ItemConversionFormValues,
  ItemConversionResultRow,
  NumericWastageOutFormValues,
  NumericWastageOutResultRow,
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

function toGmsOutPayload(form: GmsOutFormValues) {
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

function toItemChangePayload(form: ItemChangeFormValues) {
  return {
    user_id: form.user_id,
    added_at: form.added_at || null,
    items: form.items.map((item) => ({
      stock_in_id: item.stock_in_id,
      from_item_id: item.from_item_id,
      to_item_id: item.to_item_id,
      grams: item.grams,
      from_touch: item.from_touch,
      req_touch: item.req_touch,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
    })),
  }
}

function toItemConversionPayload(form: ItemConversionFormValues) {
  return {
    user_id: form.user_id,
    added_at: form.added_at || null,
    items: form.items.map((item) => ({
      stock_in_id: item.stock_in_id,
      source_item_id: item.source_item_id,
      target_item_id: item.target_item_id,
      source_grams: item.source_grams,
      source_touch: item.source_touch,
      target_touch: item.target_touch,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      alloys: item.alloys.map((alloy) => ({
        alloy_item_id: alloy.alloy_item_id,
        alloy_percentage: alloy.alloy_percentage,
        alloy_grams: alloy.alloy_grams,
      })),
    })),
  }
}

function toNumericWastageOutPayload(form: NumericWastageOutFormValues) {
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

  postGmsOut: (form: GmsOutFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<GmsOutResultRow[]>>(`${RESOURCE}/gms-out`, toGmsOutPayload(form), {
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

  postItemChange: (form: ItemChangeFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<ItemChangeResultRow[]>>(`${RESOURCE}/item-change`, toItemChangePayload(form), {
        'X-User-ID': String(actingUserId),
      })
      .then((r) => r.data),

  postItemConversion: (form: ItemConversionFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<ItemConversionResultRow[]>>(
        `${RESOURCE}/item-conversion`,
        toItemConversionPayload(form),
        { 'X-User-ID': String(actingUserId) },
      )
      .then((r) => r.data),

  // Route is `numeric-waste` (no `-out` suffix) — that's the OUT direction;
  // the `-in` counterpart above is a separate, missing route (see
  // NumericWastageInModal.vue).
  postNumericWasteOut: (form: NumericWastageOutFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<NumericWastageOutResultRow[]>>(
        `${RESOURCE}/numeric-waste`,
        toNumericWastageOutPayload(form),
        { 'X-User-ID': String(actingUserId) },
      )
      .then((r) => r.data),
}
