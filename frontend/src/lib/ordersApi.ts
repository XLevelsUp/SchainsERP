import { api, type ApiResponse } from './api'
import type {
  ApiPagination,
  Order,
  OrderCreate,
  OrderListQuery,
  OrderListResult,
  OrderUpdate,
} from '@/types'

const RESOURCE = '/orders'

function buildQuery(query: OrderListQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

// Addressed by the numeric order_id (apiResource default binding), unlike
// /settings which is keyed by its string key.
export const ordersApi = {
  // Paginated and filtered server-side, newest first. Rows carry ids only:
  // customer and item names are resolved from the caller's own lookups.
  list: (query: OrderListQuery = {}): Promise<OrderListResult> =>
    api
      .get<ApiResponse<Order[]> & { pagination: ApiPagination }>(`${RESOURCE}${buildQuery(query)}`)
      .then((r) => ({ rows: r.data, pagination: r.pagination })),

  get: (orderId: number) =>
    api.get<ApiResponse<Order>>(`${RESOURCE}/${orderId}`).then((r) => r.data),

  // 201 with the saved row, including the database-set added_at.
  create: (payload: OrderCreate) =>
    api.post<ApiResponse<Order>>(RESOURCE, payload).then((r) => r.data),

  update: (orderId: number, payload: OrderUpdate) =>
    api.put<ApiResponse<Order>>(`${RESOURCE}/${orderId}`, payload).then((r) => r.data),

  remove: (orderId: number) =>
    api.delete<ApiResponse<null>>(`${RESOURCE}/${orderId}`).then((r) => r.data),
}
