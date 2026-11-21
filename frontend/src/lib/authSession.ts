import type { AdminUser, AuthSession, AuthUser } from '@/types'

/*
|--------------------------------------------------------------------------
| Persisted auth sessions — storage only, no framework dependencies.
|--------------------------------------------------------------------------
| api.ts needs the bearer token on every request, and the Pinia auth stores
| need the same session to drive the UI. Having api.ts import a store would
| close a cycle (store -> authApi -> api -> store), so both depend on this
| module instead. Each session owns its localStorage key and nothing else
| touches it.
|
| It also carries the "token was rejected" callback, for the same reason:
| api.ts must be able to signal an expired session without knowing about
| Pinia or vue-router. main.ts registers the handlers that act on it.
|
| There are two sessions, one per realm:
|   appSession   — POST /api/v1/login: a user_details account (heads,
|                  employees, ...) using the operational app.
|   adminSession — POST /api/v1/admin/login: an admin account (table
|                  `admins`, not a head) using the admin console.
| They are stored, expire and sign out independently, and neither token is
| accepted by the other realm's API.
|--------------------------------------------------------------------------
*/

type UnauthorizedHandler = () => void
type SessionChangedHandler<TUser> = (session: AuthSession<TUser> | null) => void

export interface AuthSessionStore<TUser> {
  load(): AuthSession<TUser> | null
  save(session: AuthSession<TUser>): void
  clear(): void
  getToken(): string | null
  setUnauthorizedHandler(handler: UnauthorizedHandler | null): void
  notifyUnauthorized(): void
  setSessionChangedHandler(handler: SessionChangedHandler<TUser> | null): void
}

export function createAuthSession<TUser>(
  storageKey: string,
  // Checks the stored user has the shape this realm expects, so a session
  // written by an older build (or the other realm) is discarded, not trusted.
  isUser: (value: unknown) => value is TUser,
): AuthSessionStore<TUser> {
  // In-memory mirror so a request doesn't hit localStorage on every call.
  let current: AuthSession<TUser> | null = null
  let hydrated = false
  let unauthorizedHandler: UnauthorizedHandler | null = null
  let sessionChangedHandler: SessionChangedHandler<TUser> | null = null

  function parseSession(raw: string | null): AuthSession<TUser> | null {
    if (!raw) return null
    try {
      const parsed: unknown = JSON.parse(raw)
      if (typeof parsed !== 'object' || parsed === null) return null
      const candidate = parsed as { token?: unknown; user?: unknown }
      // Anything that isn't a token-bearing session is discarded, which also
      // covers sessions written before PR #32 (they stored the pre-token
      // response shape). Those users get bounced to the sign-in screen once,
      // which is correct — there is no token to recover from them.
      if (typeof candidate.token !== 'string' || candidate.token.length === 0) return null
      if (!isUser(candidate.user)) return null
      return { token: candidate.token, user: candidate.user }
    } catch {
      return null
    }
  }

  function readStorage(): AuthSession<TUser> | null {
    try {
      return parseSession(localStorage.getItem(storageKey))
    } catch {
      // Private mode or site data blocked — run in memory for this tab only.
      return null
    }
  }

  function load(): AuthSession<TUser> | null {
    if (!hydrated) {
      current = readStorage()
      hydrated = true
    }
    return current
  }

  function save(session: AuthSession<TUser>): void {
    current = session
    hydrated = true
    try {
      localStorage.setItem(storageKey, JSON.stringify(session))
    } catch {
      // Not persistable — the in-memory copy still serves this tab.
    }
  }

  function clear(): void {
    current = null
    hydrated = true
    try {
      localStorage.removeItem(storageKey)
    } catch {
      // Nothing was persisted in the first place.
    }
  }

  /*
  |------------------------------------------------------------------------
  | Cross-tab session sync
  |------------------------------------------------------------------------
  | Signing out in one tab used to leave every other tab holding a stale
  | in-memory token until its next request 401'd. The `storage` event fires
  | only in the tabs that did NOT make the change, which is exactly the set
  | that needs correcting — so no echo-suppression is needed here.
  |
  | It handles sign-in as well as sign-out: if another tab signs in (or a
  | different operator takes over), this tab adopts the new token rather
  | than keeping a dead one and bouncing on its next call.
  |------------------------------------------------------------------------
  */
  if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
      // key === null means the whole store was cleared (localStorage.clear()),
      // which takes this session with it; any other key is somebody else's —
      // including the other realm's session.
      if (event.key !== null && event.key !== storageKey) return
      if (event.storageArea && event.storageArea !== localStorage) return

      const next = event.key === null ? null : parseSession(event.newValue)

      // Nothing to do when the token is unchanged — writes that only touch
      // the user object (or a re-save of the same session) would otherwise
      // churn the store and, on a null token, re-trigger a redirect
      // mid-navigation.
      if (next?.token === current?.token) return

      current = next
      hydrated = true
      sessionChangedHandler?.(next)
    })
  }

  return {
    load,
    save,
    clear,
    getToken: () => load()?.token ?? null,
    setUnauthorizedHandler: (handler) => {
      unauthorizedHandler = handler
    },
    notifyUnauthorized: () => unauthorizedHandler?.(),
    setSessionChangedHandler: (handler) => {
      sessionChangedHandler = handler
    },
  }
}

function hasNumber(value: unknown, key: string): boolean {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as Record<string, unknown>)[key] === 'number'
  )
}

const isAuthUser = (value: unknown): value is AuthUser => hasNumber(value, 'user_id')
const isAdminUser = (value: unknown): value is AdminUser => hasNumber(value, 'id')

// The key predates the admin console; kept so open app sessions survive.
export const appSession = createAuthSession<AuthUser>('schainserp:auth', isAuthUser)
// v2: admins became their own accounts (2026-11-20); a session stored by the
// short-lived "admin = head" build has a different user shape.
export const adminSession = createAuthSession<AdminUser>('schainserp:admin-auth:v2', isAdminUser)
