// apiResource('orders') → OrderController → OrderDetail (PR #43–#45).
// Table `order_details`, primary key `order_id`, $timestamps = false.
//
// ⚠️ This endpoint has NO server-side validation. Every method pipes
// $request->all() into a $guarded = [] model:
//   store():  OrderDetail::create($request->all())
//   update(): $order->update($request->all())
// There is no FormRequest, no status enum, and no existence check on
// customer_id or item_id. The frontend is the only thing keeping this table
// clean — validate before sending, and do not assume the backend will
// reject anything.

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

// The `status` column is a plain string with a DB default of 'PENDING'.
// Nothing server-side constrains it, and no other vocabulary exists anywhere
// in the backend, so these values are the FRONTEND's proposal rather than a
// contract. Flagged to backend as ask #34; the table had zero rows when this
// was written, so whatever is used first effectively becomes the convention.
export const ORDER_STATUSES = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'] as const

export type OrderStatus = (typeof ORDER_STATUSES)[number]

export interface OrderCreate {
  customer_id: number
  item_id: number
  grams: number
  status: string
}

export type OrderUpdate = Partial<OrderCreate>
