// WARNING: bank_name/current_balance are stale. The bank_details migration
// in PR #13 renamed these columns to account_name/ledger_balance, but
// BankDetailController's store()/update() still validate/write
// bank_name/current_balance — those two endpoints now 500 with an
// "unknown column" SQL error. index()/show() still work (they just
// return whichever columns exist), so GET responses actually carry
// account_name/ledger_balance, not bank_name/current_balance — added
// here as optional so code reading a live response can use the real
// field without widening the broken create/update payload shape.
export interface BankDetail {
  bank_id: number
  bank_name: string
  current_balance: number
  is_active: boolean
  added_at?: string
  account_name?: string
  ledger_balance?: number
}

export interface BankDetailFormValues {
  bank_name: string
  current_balance: number | null
  is_active: boolean
}
