import { api, type ApiResponse } from './api'
import type { Order, OrderCreate, OrderUpdate } from '@/types'

const RESOURCE = '/orders'

// Addressed by the numeric order_id (apiResource default binding), unlike
// /settings which is keyed by its string key.
export const ordersApi = {
  // Returns OrderDetail::all() — no pagination, no filtering, no eager
  // loading. Customer and item names are NOT included, so callers resolve
  // them from their own lookups.
  list: () => api.get<ApiResponse<Order[]>>(RESOURCE).then((r) => r.data),

  get: (orderId: number) =>
    api.get<ApiResponse<Order>>(`${RESOURCE}/${orderId}`).then((r) => r.data),

  // 201. customer_id/item_id/status are validated for real as of PR #48 —
  // see the Order type's comment for what still isn't (grams, and the
  // remarks/due_date bug).
  create: (payload: OrderCreate) =>
    api.post<ApiResponse<Order>>(RESOURCE, payload).then((r) => r.data),

  update: (orderId: number, payload: OrderUpdate) =>
    api.put<ApiResponse<Order>>(`${RESOURCE}/${orderId}`, payload).then((r) => r.data),

  remove: (orderId: number) =>
    api.delete<ApiResponse<null>>(`${RESOURCE}/${orderId}`).then((r) => r.data),
}
