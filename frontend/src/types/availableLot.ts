// GET /stock-details/available-lots?user_id=&item_id= — added in PR #42
// (StockDetailsController::getAvailableStockLots).
//
// The generic counterpart to /available-metals, which 400s on anything not
// literally named "Metal". This one lists parent IN lots for ANY item, which
// is what finally lets Item Change and Item Conversion attach a stock_in_id
// for non-metal rows instead of posting null and losing the parent-lot
// draw-down (PENDING_WORK.md ask #8).
//
// DO NOT send `date`/`time`. That branch builds `HAVING grams - used_grams`
// referencing a SELECT alias, which PostgreSQL rejects:
//   SQLSTATE[42703] column "used_grams" does not exist
// Verified against the live database 2026-09-15. The live branch (no date,
// no time) is the one that works, and is all this app uses.

export interface AvailableLotRow {
  stock_id: number
  item_id: number
  balance: number
  grams: number
  touch: number
  purity: number
  added_at: string
  // Nullable server-side: `$lot->givenBy?->name` on a lot whose giver was
  // removed comes back as null.
  party_name: string | null
}

// Laravel paginator envelope, hand-rolled by the controller rather than
// returned by ->paginate() directly, so the field set is exactly this.
export interface AvailableLotPage {
  current_page: number
  data: AvailableLotRow[]
  total: number
  per_page: number
  last_page: number
  next_page_url: string | null
  prev_page_url: string | null
}

export interface AvailableLotQuery {
  user_id: number
  item_id: number
  page_size?: number
}
