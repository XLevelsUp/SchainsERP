// GET /system-logs, /system-logs/{id} and /system-logs/filters — the audit
// trail added in commit 3d048b3c (SystemLogController, SystemLogResource).
//
// Rows are written by SystemLogService::log() from the stock and cash
// services, and only when a transaction is created. Edits, deletes, logins,
// and user/role/master-data changes are not logged yet.

export interface SystemLogUser {
  user_id: number
  id: number
  name: string
  user_name: string | null
  role_id: number | null
}

export interface SystemLogCustomer {
  customer_id: number
  id: number
  name: string | null
  customer_name: string | null
}

export interface SystemLog {
  id: number
  // 'STOCK' or 'CASH' today; stored upper-cased.
  module: string
  // e.g. 'STOCK_IN', 'GMS_OUT', 'CASH_AUTO_ENTRY', 'FITEM_IN'.
  action: string
  // The resource's alias of `action` — always the same value.
  sub_category: string
  // The acting operator (who keyed the entry).
  user_id: number | null
  // Always eager-loaded by index and show; null if the user row is gone.
  user?: SystemLogUser | null
  // Derived server-side from the payload's customer_id / recipient_id /
  // given_to, so for stock rows it is the receiving party, not always a
  // customer.
  customer_id: number | null
  // Effectively always null: the resource reads customer_name /
  // recipient_name / given_to_name from metadata, and no log call writes
  // any of them. Party names have to be resolved from the id.
  customer_name: string | null
  customer: SystemLogCustomer | null
  // Primary key of the first row the transaction created — multi-item
  // entries only point at their first row.
  reference_id: number | null
  reference_table: string | null
  // Server-written sentence; parties appear as "User #<id>".
  description: string | null
  // The request body as submitted, with password/token keys redacted.
  payload: Record<string, unknown> | null
  // Per-action summary (items_count, given_by/given_to, sender_id/
  // recipient_id, total_cash/total_grams…). Keys differ by action.
  metadata: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  // "YYYY-MM-DD HH:mm:ss" in the app timezone.
  created_at: string | null
  created_at_human: string | null
}

export type SystemLogSortOrder = 'asc' | 'desc'

export interface SystemLogQuery {
  page?: number
  // Clamped server-side to 1–250; defaults to 25.
  per_page?: number
  // Single value or comma-separated; matched case-insensitively.
  module?: string
  action?: string
  // "YYYY-MM-DD"; a bare date widens to 00:00:00 / 23:59:59.
  from_date?: string
  to_date?: string
  user_id?: number
  customer_id?: number
  // ILIKE over description, action, module, reference table/id, IP and the
  // acting user's name/user_name.
  search?: string
  sort_by?: 'id' | 'module' | 'action' | 'user_id' | 'created_at' | 'reference_id'
  sort_order?: SystemLogSortOrder
}

// Unlike most paginated endpoints here, the rows are the envelope's `data`
// directly and the paginator fields sit beside it in `pagination`.
export interface SystemLogPagination {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
}

export interface SystemLogFilterUser {
  id: number
  user_id: number
  name: string
  user_name: string | null
  label: string
  value: number
}

export interface SystemLogFilterCustomer {
  id: number
  customer_id: number
  name: string
  customer_name: string
  user_name: string | null
  label: string
  value: number
}

export interface SystemLogFilters {
  modules: string[]
  // PHP serialises an empty array as `[]`, so on an empty table this is an
  // array, not an object — index it with a fallback.
  actions_by_module: Record<string, string[]>
  all_actions: string[]
  reference_tables: string[]
  // Roles 1 (CUSTOMER) and 4 (PURCHASE) only.
  customers: SystemLogFilterCustomer[]
  // Users who have logged entries plus active users, capped at 100.
  users: SystemLogFilterUser[]
  date_range: { min_date: string | null; max_date: string | null }
}
