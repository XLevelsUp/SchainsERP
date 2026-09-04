import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload } from '@/lib/authApi'
import { clearSession, loadSession, saveSession } from '@/lib/authSession'
import type { AuthUser } from '@/types'

/*
|--------------------------------------------------------------------------
| Auth store — the app-facing view of the session owned by authSession.ts.
|--------------------------------------------------------------------------
| The token is what actually grants access, so isAuthenticated is derived
| from it rather than from the user object.
|
| There is no session-validation call on boot: the backend exposes no /me
| (or equivalent) endpoint, so a token that expired while the tab was closed
| is only discovered on the first real request. api.ts turns that 401 into a
| clean bounce to /login, which is the best available behaviour until the
| backend adds one — flagged as a backend follow-up.
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

  return { user, token, isAuthenticated, isLoggingOut, login, logout, clear }
})
