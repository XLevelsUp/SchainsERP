import { computed, ref, type Ref } from 'vue'
import { APP_PERMISSIONS } from '@/lib/appPermissions'
import type { LoginPayload, SessionAuthApi } from '@/lib/authApi'
import type { AuthSessionStore } from '@/lib/authSession'
import type { AuthSession, AuthUser, AuthUserPayload } from '@/types'

// Keeps only what the app needs from the login / me payload, flattening the
// role relation to its name.
export function toAuthUser(raw: AuthUserPayload): AuthUser {
  return {
    user_id: raw.user_id,
    name: raw.name,
    user_name: raw.user_name,
    role_id: raw.role_id,
    role_name: raw.role?.role ?? null,
    is_act_as_head_enabled: Boolean(raw.is_act_as_head_enabled),
    permissions: Object.fromEntries(APP_PERMISSIONS.map((key) => [key, Boolean(raw[key])])),
  }
}

/*
|--------------------------------------------------------------------------
| Session state shared by the two auth stores.
|--------------------------------------------------------------------------
| stores/auth.ts (the app, user_details accounts) and
| admin/stores/adminAuth.ts (the admin console, admin accounts) behave the
| same way; only the persisted session, the endpoints and the account shape
| differ. Call this inside a Pinia setup store, so the refs belong to it.
|
| The token is what actually grants access, so isAuthenticated is derived
| from it rather than from the user object.
|
| A restored session is verified on boot (validateSession) instead of
| waiting for the first real request to 401. The 401 path still exists and
| is still the safety net — validation is best-effort and never blocks the
| app from rendering.
|--------------------------------------------------------------------------
*/
export function useSessionState<TAccount, TUser>(
  session: AuthSessionStore<TUser>,
  authApi: SessionAuthApi<TAccount>,
  toUser: (account: TAccount) => TUser,
) {
  const stored = session.load()

  const user = ref(stored?.user ?? null) as Ref<TUser | null>
  const token = ref<string | null>(stored?.token ?? null)
  const isLoggingOut = ref(false)

  const isAuthenticated = computed(() => token.value !== null)

  // Local sign-out: forgets the session without calling the backend. Used by
  // the expired-token handler, where the token is already dead server-side.
  function clear() {
    user.value = null
    token.value = null
    session.clear()
  }

  async function login(payload: LoginPayload) {
    // Drop any stale session first, so a dead token can't ride along on the
    // login request and a failed attempt leaves this realm cleanly signed out.
    clear()

    const result = await authApi.login(payload)
    const next = toUser(result.account)

    user.value = next
    token.value = result.token
    session.save({ user: next, token: result.token })
  }

  async function logout() {
    if (isLoggingOut.value) return
    isLoggingOut.value = true
    try {
      // Best effort — revoke the token server-side so it can't be reused.
      await authApi.logout()
    } catch {
      // Offline, or the token was already rejected. Either way the local
      // session still goes below: never leave an operator stranded on a
      // screen they can no longer use.
    } finally {
      clear()
      isLoggingOut.value = false
    }
  }

  // Verifies a session restored from localStorage against the server, and
  // refreshes the cached user with whatever the server currently holds (a
  // rename or a role change made elsewhere lands here).
  //
  // Deliberately best-effort:
  //  - A dead token 401s, and api.ts has already cleared the session and
  //    fired the unauthorized handler by the time this catch runs. Nothing
  //    left to do here.
  //  - Any other failure (offline, a 500, the server asleep on Render's free
  //    tier) must NOT sign the operator out. They keep the session they had
  //    and the first real request decides.
  async function validateSession() {
    if (!token.value) return

    try {
      const me = await authApi.me()

      // is_active can flip while a session is open. Login blocks deactivated
      // accounts (403) but an already-issued app token keeps working, so
      // this is where the app finds out. (Admin routes check it on every
      // request and 401 instead.)
      if (!me.is_active) {
        await logout()
        return
      }

      const next = toUser(me)
      user.value = next
      session.save({ user: next, token: token.value })
    } catch {
      // See above — never clears the session on its own.
    }
  }

  // Adopts a session another tab wrote. The storage write already happened
  // in that tab and authSession has already updated its mirror, so this only
  // brings the reactive refs in line — calling save/clear here would write
  // the value straight back out again.
  function adoptExternalSession(next: AuthSession<TUser> | null) {
    user.value = next?.user ?? null
    token.value = next?.token ?? null
  }

  return {
    user,
    token,
    isAuthenticated,
    isLoggingOut,
    login,
    logout,
    clear,
    validateSession,
    adoptExternalSession,
  }
}
