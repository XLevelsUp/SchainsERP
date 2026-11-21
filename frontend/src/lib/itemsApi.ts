import { api as appApi, type ApiClient, type ApiResponse } from './api'
import type { Item, ItemFormValues } from '@/types'

const RESOURCE = '/items'

// Written against ApiClient so the admin console reuses it with its own
// client (admin/lib/resources.ts). `itemsApi` below is the app's instance.
export function createItemsApi(api: ApiClient) {
  return {
    // mappedOnly: only the items mapped to the signed-in user
    // (users_items_mappings) — the Stock screen's item pickers.
    list: (options: { mappedOnly?: boolean } = {}) =>
      api
        .get<ApiResponse<Item[]>>(options.mappedOnly ? `${RESOURCE}?mapped_only=1` : RESOURCE)
        .then((r) => r.data),

    get: (id: number) => api.get<ApiResponse<Item>>(`${RESOURCE}/${id}`).then((r) => r.data),

    create: (payload: ItemFormValues) =>
      api.post<ApiResponse<Item>>(RESOURCE, payload).then((r) => r.data),

    update: (id: number, payload: Partial<ItemFormValues>) =>
      api.put<ApiResponse<Item>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

    remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
  }
}

export const itemsApi = createItemsApi(appApi)
