import type { UserFeatureFlagKey } from './userDetail'

// GET /api/v1/admin/user-form-profiles (App\Support\UserFormProfiles):
// which parts of the admin console's user form each role gets. The backend
// enforces the same profiles on save.
//
// Always on the form whatever the role: role, name, user name, phone,
// password and category.
export type UserFormSection =
  | 'contact' // address, signature, mailing name
  | 'remarks'
  | 'customer_comments'
  | 'heads' // the heads a user reports to (stock)
  | 'head_users' // for a head: the users under them (stock)
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
