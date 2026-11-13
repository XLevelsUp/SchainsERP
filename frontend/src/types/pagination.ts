// Paginator fields that sit beside the envelope's `data` array:
// { success, data: T[], pagination: ApiPagination }. Used by
// GET /system-logs and GET /orders (built from Laravel's LengthAwarePaginator).
export interface ApiPagination {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
}
