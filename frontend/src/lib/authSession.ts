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

function readStorage(): AuthSession | null {
  let raw: string | null
  try {
    raw = localStorage.getItem(STORAGE_KEY)
  } catch {
    // Private mode or site data blocked — run in memory for this tab only.
    return null
  }
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
