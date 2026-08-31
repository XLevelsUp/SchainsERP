export type CategoryName = 'GRAMS' | 'PURITY' | 'BOTH'

// GET /user-details (index) — PR #15 changed this to a flattened, formatted
// row shape that's unrelated to the full UserDetail below: `id` not
// `user_id`, `gm`/`purity` as fixed-3-decimal strings (not
// grams_grand_total/purity_grand_total numbers), no `user_name`. GET
// /user-details/{id} (show) was NOT changed the same way — it still
// returns the full UserDetail shape below plus a few of these same fields
// merged on top. So list and show diverge; anything needing the full
// record for a specific user (editing, balances) has to call get(id), not
// rely on a row from list().
//
// Later update: the endpoint is now module-aware via ?module=stock|cash
// (see userDetailsApi.list()). gm/purity only come back for module=stock;
// hand_cash/rtgs_cash only come back for module=cash — hence optional here,
// never both populated on the same row.
export interface UserDetailListItem {
  id: number
  name: string
  full_name: string
  type: string | null
  category_name: CategoryName
  phone_number: string
  profile_img: string | null
  is_active: boolean
  // module=stock (or omitted — the backend defaults to 'stock')
  gm?: string
  purity?: string
  last_txn_date?: string | null
  // module=cash
  hand_cash?: string
  rtgs_cash?: string
  last_cash_txn_date?: string | null
}

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
  rak_cash_balance: number
  rak_rtgs_balance: number
  grams_grand_total: number
  purity_grand_total: number
  added_at?: string
  updated_at?: string
  // Per-user UI-visibility toggles — user_details has ~30 of these
  // "is_..._shown" columns (see the create_user_details_table migration),
  // only the ones actually read by CustomerContextPanel are modeled here.
  // GET /user-details/{id} returns them, but UserDetailController's
  // store()/update() validation does NOT accept them — there is currently
  // no API to set them, only to read them.
  is_customer_touch_need_shown?: boolean
  is_customer_cmts_need_to_shown?: boolean
  is_need_to_retailer_shown?: boolean
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