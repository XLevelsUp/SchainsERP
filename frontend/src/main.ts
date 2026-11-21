import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { REALMS, realmOf, type AuthRealm } from './router/realms'
import { adminSession, appSession, type AuthSessionStore } from '@/lib/authSession'
import { useAuthStore } from '@/stores/auth'
import { useAdminAuthStore } from '@/admin/stores/adminAuth'
import type { AuthSession } from '@/types'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// A superseded navigation rejects here. The route guard still keeps an
// unauthenticated operator out of the protected routes, so swallowing it is
// safe in both directions.
const ignoreNavigationFailure = () => {}

// Sends the operator to the realm's sign-in screen, keeping the page they
// were on as ?redirect so they land back on it. Only when they're actually
// on one of that realm's pages: an admin token expiring must not pull
// someone out of a stock entry in the app, and the other way round.
function sendToLogin(realm: AuthRealm) {
  const current = router.currentRoute.value
  if (realmOf(current) !== realm) return
  if (current.name === REALMS[realm].loginRoute) return

  router
    .replace({ name: REALMS[realm].loginRoute, query: { redirect: current.fullPath } })
    .catch(ignoreNavigationFailure)
}

interface RealmStore<TUser> {
  clear(): void
  adoptExternalSession(session: AuthSession<TUser> | null): void
}

// Hooks one realm's session up to its store and the router. Registered
// after Pinia and the router so both exist when the handlers first fire.
function wireRealm<TUser>(realm: AuthRealm, session: AuthSessionStore<TUser>, store: () => RealmStore<TUser>) {
  // api.ts drops the stored session the moment the backend rejects its
  // token; this syncs the store and moves the operator to the sign-in.
  session.setUnauthorizedHandler(() => {
    store().clear()
    sendToLogin(realm)
  })

  // Another tab signed this realm in or out. authSession has already updated
  // its own mirror, so the store only needs bringing in line — and then this
  // tab follows the same way it would have if the change had happened here:
  // out to the sign-in screen on sign-out, on into the realm on sign-in.
  session.setSessionChangedHandler((next) => {
    store().adoptExternalSession(next)

    if (!next) {
      sendToLogin(realm)
      return
    }

    const current = router.currentRoute.value
    if (current.name !== REALMS[realm].loginRoute) return

    const redirect = current.query.redirect
    router
      .replace(typeof redirect === 'string' && redirect ? redirect : { name: REALMS[realm].homeRoute })
      .catch(ignoreNavigationFailure)
  })
}

wireRealm('app', appSession, useAuthStore)
wireRealm('admin', adminSession, useAdminAuthStore)

app.mount('#app')

// Verify sessions restored from localStorage against their realm's /me.
// Fired after mount, not awaited before it: the app renders immediately
// either way, and a dead token takes the normal 401 path above. A live one
// just refreshes the cached user in place. A realm with no stored token
// makes no request.
useAuthStore().validateSession()
useAdminAuthStore().validateSession()
