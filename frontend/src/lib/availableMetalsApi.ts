import { api, type ApiResponse } from './api'
import type { AvailableMetalQuery, AvailableMetalRow } from '@/types'

export const availableMetalsApi = {
  // GET /stock-details/available-metals — 400s if item_id isn't literally
  // named "Metal" (backend-enforced, see AvailableMetalRow's comment).
  list: (params: AvailableMetalQuery) =>
    api
      .get<ApiResponse<AvailableMetalRow[]>>(
        `/stock-details/available-metals?item_id=${params.item_id}&user_id=${params.user_id}`,
      )
      .then((r) => r.data),
}
