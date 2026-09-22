import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload } from '@/lib/authApi'
import { clearSession, loadSession, saveSession } from '@/lib/authSession'
import type { AuthSession, AuthUser } from '@/types'

/*
|--------------------------------------------------------------------------
| Auth store — the app-facing view of the session owned by authSession.ts.
|--------------------------------------------------------------------------
| The token is what actually grants access, so isAuthenticated is derived
| from it rather than from the user object.
|
| PR #38 added GET /me, so a restored session is now verified on boot
| (validateSession below) instead of waiting for the first real request to
| 401. The 401 path still exists and is still the safety net — validation is
| best-effort and never blocks the app from rendering.
|--------------------------------------------------------------------------
*/

export const useAuthStore = defineStore('auth', () => {
  const stored = loadSession()

  const user = ref<AuthUser | null>(stored?.user ?? null)
  const token = ref<string | null>(stored?.token ?? null)
  const isLoggingOut = ref(false)

  const isAuthenticated = computed(() => token.value !== null)

  // Local sign-out: forgets the session without calling the backend. Used by
  // the expired-token handler, where the token is already dead server-side.
  function clear() {
    user.value = null
    token.value = null
    clearSession()
  }

  async function login(payload: LoginPayload) {
    // Drop any stale session first, so a dead token can't ride along on the
    // login request and a failed attempt leaves the app cleanly signed out.
    clear()

    const result = await authApi.login(payload)

    user.value = result.user
    token.value = result.token
    saveSession({ user: result.user, token: result.token })
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
      // accounts (403) but an already-issued token keeps working, so this is
      // the only place the app finds out.
      if (!me.is_active) {
        await logout()
        return
      }

      const next = { user_id: me.user_id, name: me.name, user_name: me.user_name, role_id: me.role_id }
      user.value = next
      saveSession({ user: next, token: token.value })
    } catch {
      // See above — never clears the session on its own.
    }
  }

  // Adopts a session another tab wrote. The storage write already happened
  // in that tab and authSession has already updated its mirror, so this only
  // brings the reactive refs in line — calling saveSession/clearSession here
  // would write the value straight back out again.
  function adoptExternalSession(session: AuthSession | null) {
    user.value = session?.user ?? null
    token.value = session?.token ?? null
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
})
