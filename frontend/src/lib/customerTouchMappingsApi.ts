import { api, type ApiResponse } from './api'
import type { CustomerTouchMappingUpdate, CustomerTouchUserMapping } from '@/types'

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

  // PUT — partial update; only the keys sent are written. The response is
  // the bare model WITHOUT its relations, so callers should merge the
  // scalars into the row they already hold rather than replacing it.
  update: (id: number, payload: CustomerTouchMappingUpdate) =>
    api
      .put<ApiResponse<CustomerTouchUserMapping>>(`${RESOURCE}/${id}`, payload)
      .then((r) => r.data),
}
