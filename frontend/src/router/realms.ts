import type { RouteMeta } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAdminAuthStore } from '@/admin/stores/adminAuth'

/*
|--------------------------------------------------------------------------
| The two realms the SPA hosts, and where each one's screens live.
|--------------------------------------------------------------------------
| app   — the operational app, for heads and their staff (user_details
|         accounts): stock, cash, reports, items, settings. Signs in at /login.
| admin — the admin console, for admin accounts (table `admins`, not heads):
|         creating users and assigning them to heads. Signs in at /admin/login.
|
| Each has its own session (lib/authSession.ts), store, API client and
| layout. The router guard and main.ts's session handlers look everything
| up here so neither hard-codes a realm.
|--------------------------------------------------------------------------
*/

export type AuthRealm = NonNullable<RouteMeta['realm']>

export const REALMS = {
  app: { loginRoute: 'login', homeRoute: 'dashboard', homePath: '/' },
  admin: { loginRoute: 'admin-login', homeRoute: 'admin-users', homePath: '/admin/users' },
} as const satisfies Record<AuthRealm, { loginRoute: string; homeRoute: string; homePath: string }>

export function realmOf(route: { meta: RouteMeta }): AuthRealm {
  return route.meta.realm ?? 'app'
}

export function authStoreFor(realm: AuthRealm) {
  return realm === 'admin' ? useAdminAuthStore() : useAuthStore()
}
