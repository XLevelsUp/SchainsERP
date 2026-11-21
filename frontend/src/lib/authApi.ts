import { api, type ApiResponse } from './api'
import type { AuthMeResult, AuthUserPayload, LoginResult } from '@/types'

export interface LoginPayload {
  user_name: string
  password: string
}

// What a session store needs from its realm's auth endpoints. The app's
// authApi below and the admin console's adminAuthApi both satisfy it, so
// both stores share stores/sessionState.ts. TAccount is the realm's account
// as the server sends it; me() always carries is_active.
export interface SessionAuthApi<TAccount> {
  login(payload: LoginPayload): Promise<{ account: TAccount; token: string }>
  logout(): Promise<unknown>
  me(): Promise<TAccount & { is_active: boolean }>
}

export const authApi: SessionAuthApi<AuthUserPayload> = {
  // POST /api/v1/login — the only route outside the `auth:api` middleware.
  login: (payload: LoginPayload) =>
    api
      .post<ApiResponse<LoginResult>>('/login', payload)
      .then((r) => ({ account: r.data.user, token: r.data.token })),

  // POST /api/v1/logout — revokes the token the request was made with
  // (AuthController::logout). Returns no data, only the success envelope.
  logout: () => api.post<ApiResponse<null>>('/logout', {}),

  // GET /api/v1/me — the cheapest way to ask Passport whether the stored
  // token is still good (PR #38). A dead token 401s here exactly as it would
  // on any other route, so api.ts clears the session and bounces on its own.
  me: () => api.get<ApiResponse<AuthMeResult>>('/me').then((r) => r.data),
}
