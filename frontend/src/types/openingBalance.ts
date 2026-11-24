// Opening balance updates (2026-11-24, App\Services\OpeningBalanceService):
// the user form's Grams / Purity (per item) and GRAMS / PURITY / CASH / RTGS
// modals. closing = previous + value (negative reduces). An item's grams or
// purity also moves the grand total. Always the special password.
//
//  - Existing user: POST /admin/user-details/{id}/opening-balance.
//  - New user: sent with the create request as `opening_balances`.

export type OpeningBalanceField = 'ITEM_GRAMS' | 'ITEM_PURITY' | 'GRAMS' | 'PURITY' | 'CASH' | 'RTGS'

export interface OpeningBalanceInput {
  field: OpeningBalanceField
  // Required for ITEM_GRAMS / ITEM_PURITY; null otherwise.
  item_id: number | null
  // A decimal string, up to 4 decimals, not 0.
  value: string
  remarks: string
}

export interface OpeningBalanceUpdate {
  id: number
  user_id: number
  item_id: number | null
  field: OpeningBalanceField
  remarks: string | null
  previous_balance: string
  value_update: string
  closing_balance: string
  admin_id: number | null
  created_at: string
}

// The user's balances after the update (strings: bcmath on the server).
export interface OpeningBalanceResult {
  update: OpeningBalanceUpdate
  balances: {
    grams_grand_total: string
    purity_grand_total: string
    rak_cash_balance: string
    rak_rtgs_balance: string
    item: { item_id: number; item_grams_total: string; item_purity_total: string } | null
  }
}
