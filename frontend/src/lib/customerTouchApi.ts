import { api, type ApiResponse } from './api'
import type { CustomerTouch, CustomerTouchFormValues } from '@/types'

const RESOURCE = '/customer-touch'

export const customerTouchApi = {
  list: () => api.get<ApiResponse<CustomerTouch[]>>(RESOURCE).then((r) => r.data),

  get: (id: number) =>
    api.get<ApiResponse<CustomerTouch>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (payload: CustomerTouchFormValues) =>
    api.post<ApiResponse<CustomerTouch>>(RESOURCE, payload).then((r) => r.data),

  update: (id: number, payload: Partial<CustomerTouchFormValues>) =>
    api.put<ApiResponse<CustomerTouch>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}
