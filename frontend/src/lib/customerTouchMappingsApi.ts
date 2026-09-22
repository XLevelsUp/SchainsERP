import { api, type ApiResponse } from './api'
import type {
  CustomerTouchMappingCreate,
  CustomerTouchMappingUpdate,
  CustomerTouchUserMapping,
} from '@/types'

const RESOURCE = '/customer-touch-user-mappings'

export const customerTouchMappingsApi = {
  // GET — optional ?user_id= narrows to one user's mappings. Relations
  // (`user`, `customer_touch`) are eager-loaded on this endpoint only.
  list: (userId?: number) => {
    const qs = userId !== undefined ? `?user_id=${userId}` : ''
    return api
      .get<ApiResponse<CustomerTouchUserMapping[]>>(`${RESOURCE}${qs}`)
      .then((r) => r.data)
  },

  // POST — 201. Unlike update(), store() eager-loads `user` and
  // `customerTouch` before returning, so the created row can be pushed
  // straight into the table without a refetch or a name lookup.
  //
  // The table has a unique constraint on (user_id, customer_touch_id); a
  // duplicate pair comes back as a 500 with the driver message rather than
  // a 422, because store() catches \Exception and reports it as one.
  create: (payload: CustomerTouchMappingCreate) =>
    api.post<ApiResponse<CustomerTouchUserMapping>>(RESOURCE, payload).then((r) => r.data),

  // PUT — partial update; only the keys sent are written. The response is
  // the bare model WITHOUT its relations, so callers should merge the
  // scalars into the row they already hold rather than replacing it.
  update: (id: number, payload: CustomerTouchMappingUpdate) =>
    api
      .put<ApiResponse<CustomerTouchUserMapping>>(`${RESOURCE}/${id}`, payload)
      .then((r) => r.data),

  // DELETE — hard delete, not a soft flag. `is_active: false` is the
  // reversible option; this row is gone.
  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`).then((r) => r.data),
}
