// apiResource('orders') → OrderController → OrderDetail (PR #43–#45,
// validated in PR #48 — ask #34 largely closed, see the ⚠️ below for what's
// still open). Table `order_details`, primary key `order_id`,
// $timestamps = false.
//
// store()/update() now validate for real: customer_id/item_id must exist
// (checked against user_details/items), status is a real enum (see
// ORDER_STATUSES below — this is no longer a frontend guess, it's read off
// the live validation rule), grams is `nullable|numeric` (the frontend still
// requires it client-side — an order with no weight isn't useful here, this
// is just stricter than the backend needs).
//
// ⚠️ NEW backend bug (found 2026-09-30, confirmed live in a rolled-back
// transaction): store()/update() also validate `remarks` (nullable|string)
// and `due_date` (nullable|date), but `order_details` has no such columns —
// only order_id/customer_id/item_id/grams/status/added_at exist. Sending
// either crashes with an uncaught QueryException
// (SQLSTATE[42703]: column "remarks" of relation "order_details" does not
// exist) — there's no try/catch in the controller, so it surfaces as a raw
// 500, and APP_DEBUG=true in production means the SQL error text reaches
// the client. DO NOT add remarks/due_date fields to the order form until
// the backend either adds the migration columns or drops those two rules —
// this frontend simply never sends them today, which is why nothing here
// has hit the bug yet.
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

// Read directly off OrderController::store()'s `status` validation rule
// (`required|in:PENDING,PROCESSING,COMPLETED,CANCELLED`) as of PR #48 — a
// real contract now, not a guess. Correcting this matters: an earlier
// version of this file guessed 'IN_PROGRESS' where the backend actually
// wants 'PROCESSING'. Before PR #48 that silently worked (no validation
// existed yet); after it, 'IN_PROGRESS' would be rejected with a 422.
export const ORDER_STATUSES = ['PENDING', 'PROCESSING', 'COMPLETED', 'CANCELLED'] as const

export type OrderStatus = (typeof ORDER_STATUSES)[number]

export interface OrderCreate {
  customer_id: number
  item_id: number
  grams: number
  status: string
}

export type OrderUpdate = Partial<OrderCreate>
