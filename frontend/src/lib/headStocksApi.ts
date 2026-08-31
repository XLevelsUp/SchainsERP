import { api, type ApiResponse } from './api'
import type { HeadStockFilters, HeadStockSummary } from '@/types'

export const headStocksApi = {
  // GET /stock-details/head-stocks
  get: (userId: number, filters: HeadStockFilters = {}) => {
    const params = new URLSearchParams({ user_id: String(userId) })
    if (filters.head_txn_from_date) params.set('head_txn_from_date', filters.head_txn_from_date)
    if (filters.head_txn_from_time) params.set('head_txn_from_time', filters.head_txn_from_time)
    return api
      .get<ApiResponse<HeadStockSummary>>(`/stock-details/head-stocks?${params.toString()}`)
      .then((r) => r.data)
  },
}
