import type { NavigationGuardWithThis } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { REALMS, authStoreFor, realmOf } from './realms'

// Each route is checked against its own realm's session: an admin page
// needs an admin-console sign-in, whatever the app session holds, and the
// other way round.
export const authGuard: NavigationGuardWithThis<undefined> = (to) => {
  const realm = realmOf(to)
  const isAuthenticated = authStoreFor(realm).isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: REALMS[realm].loginRoute, query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && isAuthenticated) {
    return { name: REALMS[realm].homeRoute }
  }

  // An app page behind a permission the signed-in user doesn't have
  // (lib/appPermissions.ts): their dashboard instead.
  if (realm === 'app' && to.meta.permission && isAuthenticated && !useAuthStore().can(to.meta.permission)) {
    return { name: REALMS.app.homeRoute }
  }
}
