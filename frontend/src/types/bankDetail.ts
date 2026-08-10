// PR #15 fixed BankDetailController::store()/update() to write account_name/
// ledger_balance (the real bank_details columns) instead of the stale
// bank_name/current_balance — index()/show() already returned the real
// columns before that. added_at was never a real column (the table only has
// Eloquent's default created_at/updated_at timestamps).
export interface BankDetail {
  bank_id: number
  account_name: string
  ledger_balance: number
  is_active: boolean
  created_at?: string
}

export interface BankDetailFormValues {
  account_name: string
  ledger_balance: number | null
  is_active: boolean
}
