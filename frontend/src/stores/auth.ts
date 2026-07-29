import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { AuthUser } from '@/types'

const AUTH_STORAGE_KEY = 'schainserp:auth'

function loadStoredUser(): AuthUser | null {
  const raw = localStorage.getItem(AUTH_STORAGE_KEY)
  return raw ? (JSON.parse(raw) as AuthUser) : null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(loadStoredUser())
  const isAuthenticated = ref(user.value !== null)

  function login(authUser: AuthUser) {
    user.value = authUser
    isAuthenticated.value = true
    localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(authUser))
  }

  function logout() {
    user.value = null
    isAuthenticated.value = false
    localStorage.removeItem(AUTH_STORAGE_KEY)
  }

  return { user, isAuthenticated, login, logout }
})
