import type { ApiResponse } from '@/lib/api'
import type { LoginPayload, SessionAuthApi } from '@/lib/authApi'
import type {
  AdminLoginResult,
  AdminPasswordChange,
  AdminSpecialPasswordChange,
  AdminUser,
} from '@/types'
import { adminApi } from './adminApi'

// Admin\AdminAuthController. Admin accounts (table `admins`), not heads; the
// token works only on /api/v1/admin routes.
export const adminAuthApi: SessionAuthApi<AdminUser> = {
  // POST /api/v1/admin/login — 401 bad credentials, 403 deactivated, 429
  // after 5 tries a minute.
  login: (payload: LoginPayload) =>
    adminApi
      .post<ApiResponse<AdminLoginResult>>('/login', payload)
      .then((r) => ({ account: r.data.admin, token: r.data.token })),

  // POST /api/v1/admin/logout — revokes the admin token only.
  logout: () => adminApi.post<ApiResponse<null>>('/logout', {}),

  // GET /api/v1/admin/me — 401 once the admin account is deactivated.
  me: () => adminApi.get<ApiResponse<AdminUser>>('/me').then((r) => r.data),
}

// Admin\AdminAccountController — the signed-in admin's own passwords. Each
// change needs the current value of the secret being changed (422 on the
// `current_*` field otherwise), and the two secrets must differ.
export const adminAccountApi = {
  changePassword: (payload: AdminPasswordChange) =>
    adminApi.put<ApiResponse<AdminUser>>('/account/password', payload).then((r) => r.data),

  changeSpecialPassword: (payload: AdminSpecialPasswordChange) =>
    adminApi
      .put<ApiResponse<AdminUser>>('/account/special-password', payload)
      .then((r) => r.data),
}
