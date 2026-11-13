// Mirrors the `data` envelope of AuthController::login().
//
// PR #32 (1fae310) reshaped this response: `data` used to be flat
// (user_id/name/user_name/role_id) and is now `{ user, token, token_type }`
// with a Laravel Passport bearer token, because every route outside
// /api/v1/login moved behind the `auth:api` middleware.
export interface AuthUser {
  user_id: number
  name: string
  user_name: string
  // user_details.role_id is a varchar(50) in the schema, not an FK integer —
  // the backend's OpenAPI example showing `1` is wrong. Matches UserDetail.
  role_id: string
  // Role name (HEAD, EMPLOYEE, CUSTOMER, PURCHASE), taken from the `role`
  // relation login and /me load. Optional: sessions saved before 2026-11-12
  // don't have it until /me refreshes them on the next boot.
  role_name?: string | null
  // "Can act as head" permission: enter stock and cash values for any head,
  // without seeing balances. Heads don't need it.
  is_act_as_head_enabled?: boolean
}

// What login and /me actually send for the user: the full user_details row
// with its `role` relation loaded. Only the fields the app keeps are listed.
export interface AuthUserPayload {
  user_id: number
  name: string
  user_name: string
  role_id: string
  role?: { role: string } | null
  is_act_as_head_enabled?: boolean | number | null
}

export interface LoginResult {
  user: AuthUserPayload
  token: string
  // Always "Bearer". Kept here to document the response; not persisted,
  // since api.ts has no other scheme to switch to.
  token_type: string
}

// The slice of the login result we persist between page loads.
export interface AuthSession {
  user: AuthUser
  token: string
}

// Mirrors the `data` envelope of AuthController::me() (PR #38).
//
// The same four fields login returns, plus `is_active`. Login now rejects a
// deactivated account outright (403), but the flag can flip while a session
// is already open, so it comes back here too.
export interface AuthMeResult extends AuthUserPayload {
  is_active: boolean
}
