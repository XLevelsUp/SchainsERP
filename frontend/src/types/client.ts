export type ClientCategory = 'GRAMS' | 'PURITY' | 'BOTH'

// Maps to the `user_details` table (also used for login) — there is no
// dedicated "clients" resource on the backend.
export interface Client {
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
  mailing_name: string
  category_name: ClientCategory
  system_id: string
  is_active: boolean
  added_at: string
}

export interface ClientFormValues {
  name: string
  user_name: string
  password: string
  address: string
  signature: string
  code: string
  phone_no: string
  remarks?: string
  proff: string
  role_id: string
  mailing_name: string
  category_name: ClientCategory
  system_id: string
  is_active: boolean
}
