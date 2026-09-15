import { api, type ApiResponse } from './api'
import type { PhoneBookContact, PhoneBookPage, PhoneBookQuery } from '@/types'

const RESOURCE = '/phone-book'

// Read-only on purpose. `apiResource('phone-book')` also registers store,
// update and destroy, and they are not wrapped here because all three write
// to `user_details` — the same rows the Users screen owns:
//
//   store()    injects `user_name` = "pb_<unixtime><rand>" and
//              `password_hash` = bcrypt('phonebook123'), so every contact
//              created this way is a working ERP login with a shared,
//              hardcoded password. AuthController::login does not check
//              is_active, so switching the contact off would not close it.
//   update()   writes name/phone/role/address straight onto the shared row,
//              with no guard for which kind of record it is.
//   destroy()  sets is_delete = true and is_active = false on that row, so
//              "deleting a contact" can deactivate a head or a customer
//              that has live transactions.
//
// Flagged as PENDING_WORK.md #27. Contacts are created and edited on the
// Users screen, which goes through UserDetailController and sets real
// credentials.

function buildQuery(params: PhoneBookQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const phoneBookApi = {
  // GET /phone-book — paginated directory. The envelope's `data` is the
  // paginator object; the rows sit in `data.data`.
  list: (params: PhoneBookQuery = {}) =>
    api.get<ApiResponse<PhoneBookPage>>(`${RESOURCE}${buildQuery(params)}`).then((r) => r.data),

  // GET /phone-book/{id} — returns the full UserDetail row, not the
  // trimmed PhoneBookResource shape the index uses, and without the role
  // relation loaded.
  get: (id: number) =>
    api.get<ApiResponse<PhoneBookContact>>(`${RESOURCE}/${id}`).then((r) => r.data),
}
