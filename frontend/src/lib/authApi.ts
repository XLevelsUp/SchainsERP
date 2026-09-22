import { api, type ApiResponse } from './api'
import type { AuthMeResult, LoginResult } from '@/types'

export interface LoginPayload {
  user_name: string
  password: string
}

export const authApi = {
  // POST /api/v1/login — the only route outside the `auth:api` middleware.
  login: (payload: LoginPayload) =>
    api.post<ApiResponse<LoginResult>>('/login', payload).then((r) => r.data),

  // POST /api/v1/logout — revokes the token the request was made with
  // (AuthController::logout). Returns no data, only the success envelope.
  logout: () => api.post<ApiResponse<null>>('/logout', {}),

  // GET /api/v1/me — the cheapest way to ask Passport whether the stored
  // token is still good (PR #38). A dead token 401s here exactly as it would
  // on any other route, so api.ts clears the session and bounces on its own.
  me: () => api.get<ApiResponse<AuthMeResult>>('/me').then((r) => r.data),
}
