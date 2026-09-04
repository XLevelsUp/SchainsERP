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
  AutoEntryFormValues,
  AutoEntryItemInput,
  AutoEntryResultRow,
} from '@/types'

const RESOURCE = '/stock'

function toStockOutPayload(form: StockOutFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    retailer_id: form.retailer_id,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      added_at: item.added_at || null,
    })),
  }
}

function toStockInPayload(form: StockInFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      added_at: item.added_at || null,
    })),
  }
}

function toGmsInPayload(form: GmsInFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
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
      added_at: item.added_at || null,
    })),
  }
}

function toGmsOutPayload(form: GmsOutFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
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
      added_at: item.added_at || null,
    })),
  }
}

function toNumericWastageInPayload(form: NumericWastageInFormValues) {
  return {
    given_by: form.given_by,
    given_to: form.given_to,
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      no_of_pcs: item.no_of_pcs,
      amount_pcs: item.amount_pcs ?? 0,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      added_at: item.added_at || null,
    })),
  }
}

// Auto Entry names the same four conceptual slots differently per
// transaction type, so the neutral form fields are mapped here rather than
// leaking four field-name sets into the view:
//
//   type              | from user       | to user       | from item      | to item
//   ------------------|-----------------|---------------|----------------|---------------
//   EMPTOEMP          | from_employee   | to_employee   | emp_item_id1   | emp_item_id2
//   EMPTOHEAD         | from_employee1  | to_head       | emp_item_id3   | head_item_id
//   ANOTHERHEADTOEMP  | from_head       | to_employee1  | head_item_id1  | emp_item_id4
//   HEADTOHEAD        | from_head1      | to_head1      | head_item_id2  | head_item_id3
//
// Retailer slots differ too: EMPTOEMP takes from_retailer/to_retailer,
// EMPTOHEAD only from_retailer1, ANOTHERHEADTOEMP only to_retailer1, and
// HEADTOHEAD none at all.
function toAutoEntryPartyPayload(form: AutoEntryFormValues): Record<string, unknown> {
  switch (form.type) {
    case 'EMPTOEMP':
      return {
        from_employee: form.from_user_id,
        to_employee: form.to_user_id,
        emp_item_id1: form.from_item_id,
        emp_item_id2: form.to_item_id,
        from_retailer: form.from_retailer_id,
        to_retailer: form.to_retailer_id,
      }
    case 'EMPTOHEAD':
      return {
        from_employee1: form.from_user_id,
        to_head: form.to_user_id,
        emp_item_id3: form.from_item_id,
        head_item_id: form.to_item_id,
        from_retailer1: form.from_retailer_id,
      }
    case 'ANOTHERHEADTOEMP':
      return {
        from_head: form.from_user_id,
        to_employee1: form.to_user_id,
        head_item_id1: form.from_item_id,
        emp_item_id4: form.to_item_id,
        to_retailer1: form.to_retailer_id,
      }
    case 'HEADTOHEAD':
      return {
        from_head1: form.from_user_id,
        to_head1: form.to_user_id,
        head_item_id2: form.from_item_id,
        head_item_id3: form.to_item_id,
      }
  }
}

// Only the inputs are sent — purity/to_purity and waste_value/to_waste_value
// are left for AutoEntryService to derive, same as every other stock payload
// builder here. waste_id/to_waste_id are omitted entirely: they must exist
// in wastage_details and no endpoint lists that table (same reason
// StockOutPanel has no waste picker).
function toAutoEntryItemPayload(item: AutoEntryItemInput) {
  const base: Record<string, unknown> = {
    type: item.type,
    grams: item.grams,
    touch: item.touch,
    to_touch: item.to_touch,
    waste_total: item.waste_total ?? 0,
    to_waste_total: item.to_waste_total ?? 0,
    remarks: item.remarks || null,
    item_remarks: item.item_remarks || null,
    added_at: item.added_at || null,
  }

  if (item.type === 'GMS') {
    base.stone = item.stone ?? 0
    base.thread = item.thread ?? 0
    base.to_stone = item.to_stone ?? 0
    base.to_thread = item.to_thread ?? 0
    base.gms_mtouch = item.gms_mtouch ?? 0
    base.gms_mthouch_wastage = item.gms_mthouch_wastage ?? 0
    base.to_gms_mtouch = item.to_gms_mtouch ?? 0
    base.to_gms_mthouch_wastage = item.to_gms_mthouch_wastage ?? 0
  }

  if (item.type === 'FITEM') {
    base.box_id = item.box_id
    base.mtouch = item.mtouch ?? 0
    base.to_mtouch = item.to_mtouch ?? 0
  }

  return base
}

function toAutoEntryPayload(form: AutoEntryFormValues) {
  return {
    type: form.type,
    ...toAutoEntryPartyPayload(form),
    items: form.items.map(toAutoEntryItemPayload),
  }
}

function toItemChangePayload(form: ItemChangeFormValues) {
  return {
    user_id: form.user_id,
    items: form.items.map((item) => ({
      stock_in_id: item.stock_in_id,
      from_item_id: item.from_item_id,
      to_item_id: item.to_item_id,
      grams: item.grams,
      from_touch: item.from_touch,
      req_touch: item.req_touch,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      added_at: item.added_at || null,
    })),
  }
}

function toItemConversionPayload(form: ItemConversionFormValues) {
  return {
    user_id: form.user_id,
    items: form.items.map((item) => ({
      stock_in_id: item.stock_in_id,
      source_item_id: item.source_item_id,
      target_item_id: item.target_item_id,
      source_grams: item.source_grams,
      source_touch: item.source_touch,
      target_touch: item.target_touch,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      added_at: item.added_at || null,
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
    items: form.items.map((item) => ({
      item_id: item.item_id,
      grams: item.grams,
      touch: item.touch,
      no_of_pcs: item.no_of_pcs,
      amount_pcs: item.amount_pcs ?? 0,
      waste_total: item.waste_total ?? 0,
      remarks: item.remarks || null,
      item_remarks: item.item_remarks || null,
      added_at: item.added_at || null,
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
  // the `-in` counterpart above posts to a separate `numeric-waste-in`
  // route (now registered — see NumericWastageInModal.vue's comment).
  postNumericWasteOut: (form: NumericWastageOutFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<NumericWastageOutResultRow[]>>(
        `${RESOURCE}/numeric-waste`,
        toNumericWastageOutPayload(form),
        { 'X-User-ID': String(actingUserId) },
      )
      .then((r) => r.data),

  postAutoEntry: (form: AutoEntryFormValues, actingUserId: number) =>
    api
      .post<ApiResponse<AutoEntryResultRow[]>>(
        `${RESOURCE}/auto-entry`,
        toAutoEntryPayload(form),
        { 'X-User-ID': String(actingUserId) },
      )
      .then((r) => r.data),
}
