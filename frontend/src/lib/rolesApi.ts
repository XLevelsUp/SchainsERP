import { api, type ApiResponse } from './api'
import type { Role, RoleFormValues } from '@/types'

const RESOURCE = '/roles'

export const rolesApi = {
  list: () => api.get<ApiResponse<Role[]>>(RESOURCE).then((r) => r.data),

  get: (id: number) => api.get<ApiResponse<Role>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (payload: RoleFormValues) =>
    api.post<ApiResponse<Role>>(RESOURCE, payload).then((r) => r.data),

  update: (id: number, payload: Partial<RoleFormValues>) =>
    api.put<ApiResponse<Role>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}