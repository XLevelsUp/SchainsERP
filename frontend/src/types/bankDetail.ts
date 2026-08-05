export interface BankDetail {
  bank_id: number
  bank_name: string
  current_balance: number
  is_active: boolean
  added_at?: string
}

export interface BankDetailFormValues {
  bank_name: string
  current_balance: number | null
  is_active: boolean
}
