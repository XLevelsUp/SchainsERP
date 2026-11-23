import type { CategoryName, UserFeatureFlagKey } from './userDetail'

// GET /api/v1/admin/user-form-profiles (App\Support\UserFormProfiles):
// which parts of the admin console's user form each role gets. The backend
// enforces the same profiles on save.
//
// Always on the form whatever the role: role, name, user name, phone,
// password and category; name and password not for a role that doesn't sign
// in (signs_in false).
export type UserFormSection =
  | 'contact' // address, signature, mailing name
  | 'remarks'
  | 'customer_comments'
  | 'heads' // the heads a user reports to (stock)
  | 'head_users' // for a head: the other heads they work with (stock), HEAD users only
  | 'cash_heads' // the heads a user reports to (cash)
  | 'items' // assigned items; totals always read-only
  | 'balances' // grand totals, cash and RTGS balance, read-only
  | 'customer_touches'
  | 'image' // the legacy "Image Url" (image_url)
  | 'profile_image' // "Profile Picture"
  | 'proof' // Aadhar number, Aadhar front & back images
  | 'salary' // per-day salary, with the is_salary_person flag
  | 'joining_date'
  | 'report_password'
  | 'account' // billable / deleted
  | 'flags' // the per-user permissions

export interface UserFormProfile {
  // Upper-case role name; null for the fallback.
  role: string | null
  // true when the role has no profile of its own: the full form.
  is_default: boolean
  // The role can't be changed once the user exists (ADMIN).
  lock_role_on_edit: boolean
  // false for records that never sign in (CUSTOMER, 2026-11-23): the form
  // has no Name or Password; the backend copies the user name into the name
  // and sets a random password.
  signs_in: boolean
  // What a new user of this role starts with on the form (CUSTOMER: PURITY
  // and GOLD as the primary item); the admin can change both.
  default_category: CategoryName | null
  default_primary_item: string | null
  sections: UserFormSection[]
  flags: UserFeatureFlagKey[]
}

export interface UserFormProfileCatalog {
  sections: UserFormSection[]
  flags: UserFeatureFlagKey[]
  // For a role that isn't in by_role_id (e.g. created after loading).
  default: UserFormProfile
  // Keyed by String(role id).
  by_role_id: Record<string, UserFormProfile>
}
