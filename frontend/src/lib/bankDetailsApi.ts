import { api as appApi, type ApiClient, type ApiResponse } from './api'
import type { BankDetail, BankDetailFormValues } from '@/types'

const RESOURCE = '/bank-details'

// Written against ApiClient so the admin console reuses it with its own
// client (admin/lib/resources.ts). `bankDetailsApi` below is the app's.
export function createBankDetailsApi(api: ApiClient) {
  return {
    list: () => api.get<ApiResponse<BankDetail[]>>(RESOURCE).then((r) => r.data),

    get: (id: number) =>
      api.get<ApiResponse<BankDetail>>(`${RESOURCE}/${id}`).then((r) => r.data),

    create: (payload: BankDetailFormValues) =>
      api.post<ApiResponse<BankDetail>>(RESOURCE, payload).then((r) => r.data),

    update: (id: number, payload: Partial<BankDetailFormValues>) =>
      api.put<ApiResponse<BankDetail>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

    remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
  }
}

export const bankDetailsApi = createBankDetailsApi(appApi)
