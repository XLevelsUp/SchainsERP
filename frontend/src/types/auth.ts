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
}

export interface LoginResult {
  user: AuthUser
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
