import { api, type ApiResponse } from './api'
import type {
  AvailableLotPage,
  AvailableLotQuery,
  AvailableMetalRow,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Available stock lots — GET /stock-details/available-lots (PR #42)
|--------------------------------------------------------------------------
| The generic sibling of availableMetalsApi. Same purpose, no name check on
| the item, so Item Change and Item Conversion can attach a stock_in_id for
| rows that are not Metal.
|
| `list` deliberately returns AvailableMetalRow[], not the raw response:
| MetalPickerModal, MetalPickerSelection and every consumer of the picker
| already speak that shape, and the two endpoints differ only in field
| names (stock_id/balance here vs id/balance_grams there). Normalising once
| here keeps a second row type — and a second picker — from existing.
|
| No date/time parameters are exposed on purpose. See types/availableLot.ts:
| that branch of the backend query does not run on PostgreSQL.
|--------------------------------------------------------------------------
*/

function toMetalRow(lot: AvailableLotPage['data'][number]): AvailableMetalRow {
  return {
    id: lot.stock_id,
    grams: Number(lot.grams),
    touch: Number(lot.touch),
    purity: Number(lot.purity),
    // The picker renders this straight into a table cell, and the backend
    // nulls it when the giver is gone.
    party_name: lot.party_name ?? '—',
    balance_grams: Number(lot.balance),
  }
}

export const availableLotsApi = {
  list: (params: AvailableLotQuery) => {
    const qs = new URLSearchParams({
      user_id: String(params.user_id),
      item_id: String(params.item_id),
    })
    // The backend defaults to 50. Lot pickers are drawn down by hand, so a
    // page that large is effectively "all of them" for this screen.
    if (params.page_size !== undefined) qs.set('page_size', String(params.page_size))

    return api
      .get<ApiResponse<AvailableLotPage>>(`/stock-details/available-lots?${qs.toString()}`)
      .then((r) => r.data.data.map(toMetalRow))
  },
}
