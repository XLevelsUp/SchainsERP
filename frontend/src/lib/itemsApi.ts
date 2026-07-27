import { api, type ApiResponse } from './api'
import type { Item, ItemFormValues } from '@/types'

const RESOURCE = '/items'

export const itemsApi = {
  list: () => api.get<ApiResponse<Item[]>>(RESOURCE).then((r) => r.data),

  get: (id: number) => api.get<ApiResponse<Item>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (payload: ItemFormValues) =>
    api.post<ApiResponse<Item>>(RESOURCE, payload).then((r) => r.data),

  update: (id: number, payload: Partial<ItemFormValues>) =>
    api.put<ApiResponse<Item>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}