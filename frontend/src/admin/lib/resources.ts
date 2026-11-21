import { createItemsApi } from '@/lib/itemsApi'
import { createRolesApi } from '@/lib/rolesApi'
import { createUserDetailsApi } from '@/lib/userDetailsApi'
import { adminApi } from './adminApi'

// The shared resources the admin console uses, bound to its client. Same
// names as the app's instances on purpose: an admin screen picks its realm
// by importing from here rather than from '@/lib/...'.
//
// Roles and items are read-only here (the Users form's pickers); heads
// manage them in the app. For a change that needs the special password,
// build the resource on adminApiWithSpecialPassword(...) instead, e.g.
// createUserDetailsApi(adminApiWithSpecialPassword(pw)).remove(id).
export const userDetailsApi = createUserDetailsApi(adminApi)
export const itemsApi = createItemsApi(adminApi)
export const rolesApi = createRolesApi(adminApi)
