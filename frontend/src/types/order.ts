import type { ApiPagination } from './pagination'

// apiResource('orders') → OrderController → OrderDetail. Table
// `order_details`, primary key `order_id`, $timestamps = false. Columns:
// order_id, customer_id, item_id, grams, status, added_at.
//
// Access (since 2026-11-12): heads and employees see and manage every order;
// customers and retailers only orders where they are the customer, and get a
// 403 for anyone else's.
//
// store()/update() validate customer_id/item_id (must exist), status (the
// ORDER_STATUSES enum below) and grams (`nullable|numeric`; this screen still
// requires it and bounds it client-side). `remarks`/`due_date` were dropped
// from the rules on 2026-11-12: the table has no such columns, so they used
// to cause a 500. Sending them is now simply ignored.
export interface Order {
  order_id: number
  customer_id: number | null
  item_id: number | null
  // decimal(10,3) — Eloquent serialises it as a string on read.
  grams: number | string
  status: string
  // timestamp, useCurrent — set by the database on insert.
  added_at: string
}

// Read directly off OrderController's `status` validation rule.
export const ORDER_STATUSES = ['PENDING', 'PROCESSING', 'COMPLETED', 'CANCELLED'] as const

export type OrderStatus = (typeof ORDER_STATUSES)[number]

export interface OrderCreate {
  customer_id: number
  item_id: number
  grams: number
  status: string
}

export type OrderUpdate = Partial<OrderCreate>

// GET /orders query. Dates are YYYY-MM-DD, matched against added_at's date.
// per_page is 1–200 (default 50).
export interface OrderListQuery {
  status?: string
  customer_id?: number
  from_date?: string
  to_date?: string
  page?: number
  per_page?: number
}

export interface OrderListResult {
  rows: Order[]
  pagination: ApiPagination
}
