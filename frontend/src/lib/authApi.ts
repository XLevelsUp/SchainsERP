import { api, type ApiResponse } from './api'
import type { LoginResult } from '@/types'

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
}
