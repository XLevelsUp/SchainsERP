import { api as appApi, type ApiClient, type ApiResponse } from './api'
import type { FitemBox } from '@/types'

const RESOURCE = '/fitem-boxes'

// The backend requires box_name, item_id and added_by. Send a clean payload.
type FitemBoxPayload = {
  box_name: string
  item_id: number
  is_active: boolean
  added_by: number
  updated_by: number | null
}

// Written against ApiClient so the admin console reuses it with its own
// client (admin/lib/resources.ts). `fitemBoxesApi` below is the app's.
export function createFitemBoxesApi(api: ApiClient) {
  return {
    list: () => api.get<ApiResponse<FitemBox[]>>(RESOURCE).then((r) => r.data),

    get: (id: number) => api.get<ApiResponse<FitemBox>>(`${RESOURCE}/${id}`).then((r) => r.data),

    create: (payload: FitemBoxPayload) =>
      api.post<ApiResponse<FitemBox>>(RESOURCE, payload).then((r) => r.data),

    update: (id: number, payload: Partial<FitemBoxPayload>) =>
      api.put<ApiResponse<FitemBox>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

    remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
  }
}

export const fitemBoxesApi = createFitemBoxesApi(appApi)
