import type { CustomerTouch } from './customerTouch'

// GET /customer-touch-user-mappings, PUT|PATCH /customer-touch-user-mappings/{id}
// (PR #32, CustomerTouchUserMappingController). Many-to-many link between
// user_details and customer_touch, table customer_touch_user_mappings.
//
// The controller exposes ONLY index and update — there is no store and no
// destroy route, so mappings can be listed and edited but not created or
// removed through the API. Flagged to backend; the UI reflects it rather
// than pretending otherwise.

// The nested `user` is the raw UserDetail model as Eloquent serialises it,
// NOT the enriched shape GET /user-details/{id} returns. Only the fields
// this screen actually reads are declared here — the payload carries more.
export interface CustomerTouchMappingUser {
  user_id: number
  name: string
  user_name: string
}

export interface CustomerTouchUserMapping {
  id: number
  user_id: number
  customer_touch_id: number
  is_active: boolean
  // The table manages these itself (`useCurrent`/`useCurrentOnUpdate`);
  // the model has $timestamps = false and casts both to datetime, so they
  // serialise as ISO-8601.
  added_at: string
  updated_at: string
  // Eager-loaded by index() only. Eloquent snake-cases relation keys on
  // serialisation, so the `customerTouch` relation arrives as
  // `customer_touch`. update() returns the bare model, so both are absent
  // from that response — hence optional.
  user?: CustomerTouchMappingUser | null
  customer_touch?: CustomerTouch | null
}

// Every field is `sometimes` server-side, so a partial payload is valid and
// only the keys present are written.
export interface CustomerTouchMappingUpdate {
  user_id?: number
  customer_touch_id?: number
  is_active?: boolean
}
