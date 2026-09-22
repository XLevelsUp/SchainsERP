import type { UserFeatureFlagKey } from '@/types'

/*
|--------------------------------------------------------------------------
| Per-user feature flags — the catalogue
|--------------------------------------------------------------------------
| user_details carries 44 boolean `is_*` columns. UserDetailController's
| store() and update() validate every one of them as `sometimes|boolean`,
| so all 44 are settable — that changed in PR #43–#45, before which they
| were readable but not writable.
|
| Four are deliberately NOT listed here:
|   is_active, is_delete, is_billable  — account state, already first-class
|                                        fields on the user form.
|   is_otp_verified                    — system state written by the login
|                                        flow, not an operator setting.
|                                        Exposing a checkbox for it would
|                                        invite someone to mark an account
|                                        verified by hand.
|
| That leaves the 40 below. They are grouped because a flat wall of 40
| checkboxes is unusable, and labelled in business terms because the column
| names are not self-explanatory ("is_need_pink_box_shown").
|
| The labels are OUR reading of each column name — no backend documentation
| for them exists. Where a name is genuinely ambiguous the label stays close
| to the column so nobody is misled by a confident-sounding guess.
|--------------------------------------------------------------------------
*/

export interface UserFeatureFlag {
  key: UserFeatureFlagKey
  label: string
}

export interface UserFeatureFlagGroup {
  title: string
  description: string
  flags: UserFeatureFlag[]
}

export const USER_FEATURE_FLAG_GROUPS: UserFeatureFlagGroup[] = [
  {
    title: 'Calculations',
    description: 'Which calculation engines run for this user.',
    flags: [
      { key: 'is_gold_cal_enabled', label: 'Gold calculation' },
      { key: 'is_cash_cal_enabled', label: 'Cash calculation' },
      { key: 'is_wastage_cal_enabled', label: 'Wastage calculation' },
      { key: 'is_customerfitem_cal_enabled', label: 'Customer f-item calculation (out)' },
      { key: 'is_customerfitem_cal_in_enabled', label: 'Customer f-item calculation (in)' },
    ],
  },
  {
    title: 'Stock & items',
    description: 'Stock entry options and the fields shown on item rows.',
    flags: [
      { key: 'is_metal_stock_shown', label: 'Show metal stock' },
      { key: 'is_delivery_item_shown', label: 'Show delivery item' },
      { key: 'is_polish_needed', label: 'Polish required' },
      { key: 'is_polish_chk_need_shown', label: 'Show polish check' },
      { key: 'is_delivery_chk_need_shown', label: 'Show delivery check' },
      { key: 'is_wa_delivery_stock_needed', label: 'WhatsApp delivery stock' },
      { key: 'is_cus_fitem_pur_in_shown', label: 'Show customer f-item purchase (in)' },
      { key: 'is_cus_fitem_pur_out_shown', label: 'Show customer f-item purchase (out)' },
      { key: 'is_need_grosswgt_print_shown', label: 'Print gross weight' },
      { key: 'is_die_num_search_need_to_shown', label: 'Die number search' },
    ],
  },
  {
    title: 'Orders',
    description: 'Order creation and status visibility.',
    flags: [
      { key: 'is_create_order_shown', label: 'Show create order' },
      { key: 'is_create_order_need_to_shown', label: 'Create order required' },
      { key: 'is_need_order_status_shown', label: 'Show order status' },
      {
        key: 'is_need_show_order_display_in_head_login',
        label: 'Show order display in head login',
      },
    ],
  },
  {
    title: 'Cash',
    description: 'Cash management access and receipt options.',
    flags: [
      { key: 'is_cash_mngmt_need_to_shown', label: 'Show cash management' },
      { key: 'is_cashamt_thermal_shown', label: 'Show cash amount on thermal print' },
      { key: 'is_need_role_wise_cash_rpt_shown', label: 'Role-wise cash report' },
    ],
  },
  {
    title: 'Reports',
    description: 'Which reports this user can reach.',
    flags: [
      { key: 'is_ob_cb_rpt_need_to_shown', label: 'OB & CB report' },
      { key: 'is_con_box_rpt_need_to_shown', label: 'Consolidated box report' },
      { key: 'is_box_tot_rpt_need_to_shown', label: 'Box total report' },
      { key: 'is_day_grand_rpt_need_shown', label: 'Day grand total report' },
      { key: 'is_complete_history_need_shown', label: 'Complete history' },
      { key: 'is_tally_need_to_shown', label: 'Tally' },
    ],
  },
  {
    title: 'Customer',
    description: 'Customer-facing panels and fields.',
    flags: [
      { key: 'is_customer_touch_need_shown', label: 'Show customer touch' },
      { key: 'is_customer_cmts_need_to_shown', label: 'Show customer comments' },
      { key: 'is_need_to_retailer_shown', label: 'Show retailer' },
    ],
  },
  {
    title: 'Workflow',
    description: 'Worker, task and general screen options.',
    flags: [
      { key: 'is_worker_need_to_shown', label: 'Show worker' },
      { key: 'is_emp_group_task_need_to_shown', label: 'Employee group task' },
      { key: 'is_salary_person', label: 'Salary person' },
      { key: 'is_outside_need_to_shown', label: 'Show outside' },
      { key: 'is_gallery_need_to_shown', label: 'Show gallery' },
      { key: 'is_need_pink_box_shown', label: 'Show pink box' },
      { key: 'is_remainder_shown', label: 'Show reminders' },
    ],
  },
  {
    title: 'Security',
    description: 'Extra checks applied to this account.',
    flags: [
      { key: 'is_admin_login_otp_need_to_shown', label: 'Require OTP on admin login' },
      { key: 'is_freeze_entry_need_to_shown', label: 'Allow freeze entry' },
    ],
  },
]

// Flat list, derived so the two can never drift apart.
export const USER_FEATURE_FLAG_KEYS: UserFeatureFlagKey[] = USER_FEATURE_FLAG_GROUPS.flatMap((g) =>
  g.flags.map((f) => f.key),
)

// Every flag defaults to false on a new user — the column defaults are false
// in the migration and the controller sends `false` when a key is absent.
export function emptyUserFeatureFlags(): Record<UserFeatureFlagKey, boolean> {
  return Object.fromEntries(USER_FEATURE_FLAG_KEYS.map((k) => [k, false])) as Record<
    UserFeatureFlagKey,
    boolean
  >
}
