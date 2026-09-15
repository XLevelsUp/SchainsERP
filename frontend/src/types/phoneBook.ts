// GET /phone-book — PR #37, testing doc sections 48-52.
//
// A paginated, searchable projection of `user_details`. It is the same
// table the Users and Clients screens read and the stock/cash pickers
// select from — not a separate contacts entity — so a row here can be a
// head, an employee, a customer or a retailer.
//
// Only the read half is modelled. `PhoneBookController::store()` mints a
// real ERP login for every contact it creates (`user_name` = "pb_" plus a
// timestamp, password a hardcoded constant), `update()` writes to the same
// shared row the Users screen edits, and `destroy()` sets is_delete /
// is_active on it. Those are deliberately not wrapped here — see
// PENDING_WORK.md #27.

export interface PhoneBookRole {
  id: number | null
  // Always null today: the resource reads `role_name`, but the roles table
  // column is `role` (PENDING_WORK.md #26).
  role_name: string | null
}

export interface PhoneBookContact {
  user_id: number
  name: string
  // `phone_no` here, not the `phone_number` the flattened user-details list
  // returns for the same column.
  phone_no: string
  // A raw storage path such as "profile_images/abc.jpg", not a URL —
  // unlike GET /user-details/{id}, which returns a resolved
  // profile_image_url alongside the record.
  profile_image: string | null
  is_active: boolean
  // Only present when the controller eager-loaded the relation, which the
  // index does and the show does not.
  role?: PhoneBookRole
}

export type PhoneBookSort = 'name' | '-name' | 'phone_no' | '-phone_no' | 'is_active' | '-is_active'

export interface PhoneBookQuery {
  page?: number
  per_page?: number
  // Matched against name and phone_no with ILIKE, so it is
  // case-insensitive and matches anywhere in the value.
  search?: string
  is_active?: boolean
  role_id?: number
  sort?: PhoneBookSort
}

// Laravel's paginator, unwrapped one level by the controller: the outer
// `data` is this object, and the rows are in its own `data`.
export interface PhoneBookPage {
  current_page: number
  data: PhoneBookContact[]
  total: number
  per_page: number
  last_page: number
  next_page_url: string | null
  prev_page_url: string | null
}
