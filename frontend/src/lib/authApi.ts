import { api, type ApiResponse } from './api'
import type { AuthUser } from '@/types'

export interface LoginPayload {
  user_name: string
  password: string
}

export const authApi = {
  login: (payload: LoginPayload) =>
    api.post<ApiResponse<AuthUser>>('/login', payload).then((r) => r.data),
}
