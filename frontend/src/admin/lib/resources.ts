import { createCustomerTouchApi } from '@/lib/customerTouchApi'
import { createItemsApi } from '@/lib/itemsApi'
import { createPhoneBookApi } from '@/lib/phoneBookApi'
import { createRolesApi } from '@/lib/rolesApi'
import { createUserDetailsApi } from '@/lib/userDetailsApi'
import { adminApi } from './adminApi'

// The shared resources the admin console uses, bound to its client. Same
// names as the app's instances on purpose: an admin screen picks its realm
// by importing from here rather than from '@/lib/...'.
//
// Items and customer touches are read-only here (the Users form's pickers);
// heads manage them in the app. For a change that needs the special
// password, build the resource on adminApiWithSpecialPassword(...) instead,
// e.g. createUserDetailsApi(adminApiWithSpecialPassword(pw)).remove(id).
export const userDetailsApi = createUserDetailsApi(adminApi)
export const itemsApi = createItemsApi(adminApi)
export const rolesApi = createRolesApi(adminApi)
export const customerTouchApi = createCustomerTouchApi(adminApi)
// The phone book (2026-11-24): admins create, edit and delete contacts.
export const phoneBookApi = createPhoneBookApi(adminApi)
