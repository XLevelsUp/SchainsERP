import type { AuthSession } from '@/types'

/*
|--------------------------------------------------------------------------
| Persisted auth session — storage only, no framework dependencies.
|--------------------------------------------------------------------------
| api.ts needs the bearer token on every request, and the Pinia auth store
| needs the same session to drive the UI. Having api.ts import the store
| would close a cycle (store -> authApi -> api -> store), so both depend on
| this module instead. It owns the localStorage key and nothing else does.
|
| It also carries the "token was rejected" callback, for the same reason:
| api.ts must be able to signal an expired session without knowing about
| Pinia or vue-router. main.ts registers the handler that acts on it.
|--------------------------------------------------------------------------
*/

const STORAGE_KEY = 'schainserp:auth'

// In-memory mirror so a request doesn't hit localStorage on every call.
let current: AuthSession | null = null
let hydrated = false

function isAuthSession(value: unknown): value is AuthSession {
  if (typeof value !== 'object' || value === null) return false
  const candidate = value as Partial<AuthSession>
  if (typeof candidate.token !== 'string' || candidate.token.length === 0) return false
  const user = candidate.user
  return typeof user === 'object' && user !== null && typeof user.user_id === 'number'
}

function parseSession(raw: string | null): AuthSession | null {
  if (!raw) return null
  try {
    const parsed: unknown = JSON.parse(raw)
    // Anything that isn't a token-bearing session is discarded, which also
    // covers sessions written before PR #32 (they stored the pre-token
    // response shape). Those users get bounced to /login once, which is
    // correct — there is no token to recover from them.
    return isAuthSession(parsed) ? parsed : null
  } catch {
    return null
  }
}

function readStorage(): AuthSession | null {
  try {
    return parseSession(localStorage.getItem(STORAGE_KEY))
  } catch {
    // Private mode or site data blocked — run in memory for this tab only.
    return null
  }
}

export function loadSession(): AuthSession | null {
  if (!hydrated) {
    current = readStorage()
    hydrated = true
  }
  return current
}

export function saveSession(session: AuthSession): void {
  current = session
  hydrated = true
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(session))
  } catch {
    // Not persistable — the in-memory copy still serves this tab.
  }
}

export function clearSession(): void {
  current = null
  hydrated = true
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    // Nothing was persisted in the first place.
  }
}

export function getToken(): string | null {
  return loadSession()?.token ?? null
}

type UnauthorizedHandler = () => void

let unauthorizedHandler: UnauthorizedHandler | null = null

export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  unauthorizedHandler = handler
}

export function notifyUnauthorized(): void {
  unauthorizedHandler?.()
}

/*
|--------------------------------------------------------------------------
| Cross-tab session sync
|--------------------------------------------------------------------------
| Signing out in one tab used to leave every other tab holding a stale
| in-memory token until its next request 401'd. The `storage` event fires
| only in the tabs that did NOT make the change, which is exactly the set
| that needs correcting — so no echo-suppression is needed here.
|
| It handles sign-in as well as sign-out: if another tab signs in (or a
| different operator takes over), this tab adopts the new token rather than
| keeping a dead one and bouncing on its next call.
|
| Same single-handler shape as setUnauthorizedHandler above, and for the
| same reason — this module must not know about Pinia or vue-router.
| main.ts registers the handler that acts on it.
|--------------------------------------------------------------------------
*/

type SessionChangedHandler = (session: AuthSession | null) => void

let sessionChangedHandler: SessionChangedHandler | null = null

export function setSessionChangedHandler(handler: SessionChangedHandler | null): void {
  sessionChangedHandler = handler
}

if (typeof window !== 'undefined') {
  window.addEventListener('storage', (event) => {
    // key === null means the whole store was cleared (localStorage.clear()),
    // which takes our session with it; any other key is somebody else's.
    if (event.key !== null && event.key !== STORAGE_KEY) return
    if (event.storageArea && event.storageArea !== localStorage) return

    const next = event.key === null ? null : parseSession(event.newValue)

    // Nothing to do when the token is unchanged — writes that only touch the
    // user object (or a re-save of the same session) would otherwise churn
    // the store and, on a null token, re-trigger a redirect mid-navigation.
    if (next?.token === current?.token) return

    current = next
    hydrated = true
    sessionChangedHandler?.(next)
  })
}
