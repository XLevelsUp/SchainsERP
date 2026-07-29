export type CategoryName = 'GRAMS' | 'PURITY' | 'BOTH'

// A user record as returned by the backend (password_hash/otp/report_password are hidden by the model).
export interface UserDetail {
  user_id: number
  name: string
  user_name: string
  address: string
  signature: string
  code: string
  phone_no: string
  remarks: string | null
  proff: string
  role_id: string
  customer_commants: string | null
  mailing_name: string
  category_name: CategoryName
  system_id: string
  is_active: boolean
  is_delete: boolean
  is_billable: boolean
  added_at?: string
  updated_at?: string
}

// One item assignment sent inside the user create request.
export interface ItemMappingInput {
  item_id: number | null
  item_grams_total: string
  item_purity_total: string
  is_primary: number
}

// Head / cash-head assignment inputs (each just references a head user id).
export interface HeadMappingInput {
  head_id: number | null
}

// The full payload the create endpoint accepts.
export interface UserDetailFormValues {
  name: string
  user_name: string
  password: string
  address: string
  signature: string
  code: string
  phone_no: string
  remarks: string
  proff: string
  role_id: string
  system_id: string
  mailing_name: string
  customer_commants: string
  category_name: CategoryName
  is_active: boolean
  is_delete: boolean
  is_billable: boolean
  item_mappings: ItemMappingInput[]
  head_mappings: HeadMappingInput[]
  cash_head_mappings: HeadMappingInput[]
}