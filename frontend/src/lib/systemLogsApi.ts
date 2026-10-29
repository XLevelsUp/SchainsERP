import { api, type ApiResponse } from './api'
import type {
  SystemLog,
  SystemLogFilters,
  SystemLogPagination,
  SystemLogQuery,
} from '@/types'

const RESOURCE = '/system-logs'

// Read-only: the backend exposes no write routes — rows are written as a
// side effect of the stock and cash transaction endpoints.

function buildQuery(params: SystemLogQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const systemLogsApi = {
  // GET /system-logs — rows in `data`, paginator fields in `pagination`.
  list: (params: SystemLogQuery = {}) =>
    api
      .get<ApiResponse<SystemLog[]> & { pagination: SystemLogPagination }>(
        `${RESOURCE}${buildQuery(params)}`,
      )
      .then((r) => ({ rows: r.data, pagination: r.pagination })),

  // GET /system-logs/{id} — same SystemLogResource shape as a list row.
  get: (id: number) => api.get<ApiResponse<SystemLog>>(`${RESOURCE}/${id}`).then((r) => r.data),

  // GET /system-logs/filters — dropdown options for the filter bar.
  filters: () =>
    api.get<ApiResponse<SystemLogFilters>>(`${RESOURCE}/filters`).then((r) => r.data),
}
