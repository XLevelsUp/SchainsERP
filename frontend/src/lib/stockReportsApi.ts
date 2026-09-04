import { api, type ApiResponse } from './api'
import type {
  ConsolidatedQuery,
  ConsolidatedResult,
  ItemsObcbQuery,
  ItemsObcbResult,
} from '@/types'

// Both routes sit inside the `v1/stock` prefix group in routes/api.php.
const RESOURCE = '/stock/reports'

function buildQuery<T extends object>(params: T): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const stockReportsApi = {
  // Running OB/CB ledger. Scoped to the acting user as the head (the
  // controller resolves that from the bearer token), narrowed by the
  // optional prefixed `employee_id`.
  getItemsObcb: (query: ItemsObcbQuery) =>
    api
      .get<ApiResponse<ItemsObcbResult>>(`${RESOURCE}/items-obcb${buildQuery(query)}`)
      .then((r) => r.data),

  // Outward/inward summary plus both detail lists in one response.
  getConsolidated: (query: ConsolidatedQuery) =>
    api
      .get<ApiResponse<ConsolidatedResult>>(`${RESOURCE}/consolidated${buildQuery(query)}`)
      .then((r) => r.data),
}
