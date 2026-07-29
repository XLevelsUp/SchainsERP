import { api, type ApiResponse } from './api'
import type { Client, ClientFormValues } from '@/types'

const RESOURCE = '/user-details'

// Note: the backend's UserDetailController has no update action, so there
// is no `update` here — only list, create, and remove are supported.
export const clientsApi = {
  list: () => api.get<ApiResponse<Client[]>>(RESOURCE).then((r) => r.data),

  create: (payload: ClientFormValues) =>
    api.post<ApiResponse<Client>>(RESOURCE, payload).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}
