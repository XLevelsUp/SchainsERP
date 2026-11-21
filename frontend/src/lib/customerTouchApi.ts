import { api as appApi, type ApiClient, type ApiResponse } from './api'
import type { CustomerTouch, CustomerTouchFormValues } from '@/types'

const RESOURCE = '/customer-touch'

// Written against ApiClient so the admin console reuses it with its own
// client (admin/lib/resources.ts). `customerTouchApi` below is the app's.
export function createCustomerTouchApi(api: ApiClient) {
  return {
    list: () => api.get<ApiResponse<CustomerTouch[]>>(RESOURCE).then((r) => r.data),

    get: (id: number) =>
      api.get<ApiResponse<CustomerTouch>>(`${RESOURCE}/${id}`).then((r) => r.data),

    create: (payload: CustomerTouchFormValues) =>
      api.post<ApiResponse<CustomerTouch>>(RESOURCE, payload).then((r) => r.data),

    update: (id: number, payload: Partial<CustomerTouchFormValues>) =>
      api.put<ApiResponse<CustomerTouch>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

    remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
  }
}

export const customerTouchApi = createCustomerTouchApi(appApi)
