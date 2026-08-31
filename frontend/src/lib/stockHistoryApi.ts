import { api, type ApiResponse } from './api'
import type { StockHistoryQuery, StockHistoryResult } from '@/types'

export const stockHistoryApi = {
  // GET /stock-details/history
  list: (query: StockHistoryQuery) => {
    const params = new URLSearchParams()
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    })
    return api
      .get<ApiResponse<StockHistoryResult>>(`/stock-details/history?${params.toString()}`)
      .then((r) => r.data)
  },
}
