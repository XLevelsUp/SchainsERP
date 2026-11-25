// Item Change History and Item Conversion Group History (2026-11-25):
// ItemHistoryReportService. App: GET /stock/reports/item-changes and
// /stock/reports/item-conversion-groups (the signed-in user's own records,
// plus a head's mapped employees'). Console: /admin/reports/… (every user's,
// or one head's with `head_id`).
//
// Decimals come back as 4-place strings (the decimal casts), or null when
// the linked stock row is gone.

export interface ItemHistoryQuery {
  // Admin console only: one head (with its mapped employees); left out, everyone.
  head_id?: number
  // Default to today server-side; a missing `to_date` means one day.
  from_date?: string
  to_date?: string
  // Either side of the change / conversion.
  item_id?: number
  page_no?: number
  // 1–1000. Item changes: rows per page; conversions: groups per page.
  page_size?: number
}

export interface ItemHistoryPagination {
  current_page: number
  per_page: number
  total_items: number
  total_pages: number
}

type Decimal = string | number | null

/* ------------------------------------------------------------- item change */

export interface ItemChangeRow {
  // item_change_history.change_id
  id: number
  added_at: string | null
  // Whose stock it was (the OUT row's given_by).
  user_id: number | null
  user_name: string | null
  from_item_id: number
  from_item_name: string | null
  to_item_id: number
  to_item_name: string | null
  grams: Decimal
  from_touch: Decimal
  req_touch: Decimal
  // What left the From item: grams × From touch.
  from_purity: Decimal
  // What entered the To item: grams × requested touch.
  purity: Decimal
  // The user's own remarks; the automatic "Item Change from: …" is null.
  remarks: string | null
  item_remarks: string | null
  out_stock_id: number | null
  in_stock_id: number | null
}

export interface ItemChangeResult {
  total_count: number
  records: ItemChangeRow[]
  // Over the whole search, not just this page.
  totals: { grams: string; from_purity: string; purity: string }
  pagination: ItemHistoryPagination
}

/* --------------------------------------------------------- item conversion */

export interface ItemConversionAlloy {
  alloy_item_id: number
  alloy_item_name: string | null
  alloy_percentage: string
  alloy_grams: string
}

export interface ItemConversionRow {
  // gold_conversions.id
  id: number
  added_at: string | null
  user_id: number | null
  user_name: string | null
  source_item_id: number
  source_item_name: string | null
  target_item_id: number
  target_item_name: string | null
  source_grams: Decimal
  source_touch: Decimal
  source_purity: Decimal
  target_touch: Decimal
  converted_grams: Decimal
  converted_purity: Decimal
  alloys: ItemConversionAlloy[]
  alloy_grams: string
  remarks: string | null
  item_remarks: string | null
  // The lines of one save share it.
  bill_id: number | null
  out_stock_id: number | null
  in_stock_id: number | null
}

/** One item pair (source → target) with all its conversions in the search. */
export interface ItemConversionGroup {
  // "<source_item_id>-<target_item_id>"
  key: string
  source_item_id: number
  source_item_name: string | null
  target_item_id: number
  target_item_name: string | null
  count: number
  totals: {
    source_grams: string
    source_purity: string
    converted_grams: string
    converted_purity: string
    alloy_grams: string
  }
  // Newest first.
  records: ItemConversionRow[]
}

export interface ItemConversionGroupResult {
  // Groups (item pairs) in the search; the paging is over groups.
  total_count: number
  total_conversions: number
  // In Orders No order: source item, then target item.
  groups: ItemConversionGroup[]
  pagination: ItemHistoryPagination
}
