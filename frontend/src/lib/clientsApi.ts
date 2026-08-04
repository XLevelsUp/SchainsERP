import { api, type ApiResponse } from './api'
import type { Client, ClientFormValues } from '@/types'

const RESOURCE = '/user-details'

export const clientsApi = {
  list: () => api.get<ApiResponse<Client[]>>(RESOURCE).then((r) => r.data),

  create: (payload: ClientFormValues) =>
    api.post<ApiResponse<Client>>(RESOURCE, payload).then((r) => r.data),

  update: (id: number, payload: Partial<ClientFormValues>) =>
    api.put<ApiResponse<Client>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}
