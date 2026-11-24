// The phone book (2026-11-24, App\Http\Controllers\Api\PhoneBookController).
//
// Its own list of contacts, not the users table: every user was copied in
// once, and each user the admin console creates is added. Editing or
// deleting a contact never changes a user.
//
//  - Admin console: GET/POST /admin/phone-book, PUT/DELETE /admin/phone-book/{id}.
//  - App: GET /phone-book and /phone-book/{id}, read-only (the "Phone book
//    shown" permission).

export interface PhoneBookRole {
  id: number | null
  role_name: string | null
}

export interface PhoneBookContact {
  id: number
  name: string
  // Exactly 10 digits for new and edited contacts; numbers copied from
  // users are kept as they were until their next save.
  phone_no: string
  role_id: number | null
  is_active: boolean
  // The user this contact was copied from, if any (null once that user is
  // deleted).
  user_id: number | null
  // That user's profile picture; null for other contacts.
  profile_image_url: string | null
  // Loaded on every list and show.
  role?: PhoneBookRole
  created_at?: string | null
  updated_at?: string | null
}

// The modal's fields (POST / PUT body).
export interface PhoneBookFormValues {
  name: string
  phone_no: string
  role_id: number | null
  is_active: boolean
}

export type PhoneBookSort = 'name' | '-name' | 'phone_no' | '-phone_no' | 'is_active' | '-is_active'

export interface PhoneBookQuery {
  page?: number
  per_page?: number
  // Name or phone, case-insensitive, anywhere in the value.
  search?: string
  // The console's column filters.
  name?: string
  phone_no?: string
  is_active?: boolean
  role_id?: number
  sort?: PhoneBookSort
  // Every contact on one page (the console loads the whole list).
  all?: boolean
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
