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
// Extends Partial<UserFeatureFlags> so every one of the 40 toggles is
// readable off a fetched user (CustomerContextPanel already reads three of
// them) without listing them twice. Optional because the list endpoint's
// trimmed rows do not carry them.
export interface UserDetail extends Partial<UserFeatureFlags> {
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
  // Per-user feature toggles. GET /user-details/{id} and index() return all
  // of them (UserDetail hides only password_hash/report_password/otp), and
  // since PR #43–#45 store()/update() validate every one as
  // `sometimes|boolean`, so they are now writable too — the note that used
  // to sit here saying they were read-only is obsolete.
  //
  // Optional because older responses and the list endpoint's trimmed rows
  // may not carry them.
}

// The 40 operator-settable feature toggles, keyed by column name.
//
// Excludes is_active / is_delete / is_billable (first-class account fields)
// and is_otp_verified (system state written by the login flow). See
// lib/userFeatureFlags.ts for the grouped catalogue and labels.
export type UserFeatureFlagKey =
  | 'is_gold_cal_enabled'
  | 'is_cash_cal_enabled'
  | 'is_wastage_cal_enabled'
  | 'is_customerfitem_cal_enabled'
  | 'is_customerfitem_cal_in_enabled'
  | 'is_metal_stock_shown'
  | 'is_delivery_item_shown'
  | 'is_polish_needed'
  | 'is_polish_chk_need_shown'
  | 'is_delivery_chk_need_shown'
  | 'is_wa_delivery_stock_needed'
  | 'is_cus_fitem_pur_in_shown'
  | 'is_cus_fitem_pur_out_shown'
  | 'is_need_grosswgt_print_shown'
  | 'is_die_num_search_need_to_shown'
  | 'is_create_order_shown'
  | 'is_create_order_need_to_shown'
  | 'is_need_order_status_shown'
  | 'is_need_show_order_display_in_head_login'
  | 'is_cash_mngmt_need_to_shown'
  | 'is_cashamt_thermal_shown'
  | 'is_need_role_wise_cash_rpt_shown'
  | 'is_ob_cb_rpt_need_to_shown'
  | 'is_con_box_rpt_need_to_shown'
  | 'is_box_tot_rpt_need_to_shown'
  | 'is_day_grand_rpt_need_shown'
  | 'is_complete_history_need_shown'
  | 'is_tally_need_to_shown'
  | 'is_customer_touch_need_shown'
  | 'is_customer_cmts_need_to_shown'
  | 'is_need_to_retailer_shown'
  | 'is_worker_need_to_shown'
  | 'is_emp_group_task_need_to_shown'
  | 'is_salary_person'
  | 'is_outside_need_to_shown'
  | 'is_gallery_need_to_shown'
  | 'is_need_pink_box_shown'
  | 'is_remainder_shown'
  | 'is_admin_login_otp_need_to_shown'
  | 'is_freeze_entry_need_to_shown'

export type UserFeatureFlags = Record<UserFeatureFlagKey, boolean>

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
// Extends UserFeatureFlags (required, not partial) so the form always sends
// a complete set. The controller treats a missing key as `false` anyway
// — `$request->input('is_x', false)` — so sending all 40 explicitly is the
// only way to be sure a cleared checkbox is actually written as false.
export interface UserDetailFormValues extends UserFeatureFlags {
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