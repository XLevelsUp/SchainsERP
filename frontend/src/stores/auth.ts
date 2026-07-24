import { defineStore } from 'pinia'
import { ref } from 'vue'

const AUTH_STORAGE_KEY = 'schainserp:auth'

export const useAuthStore = defineStore('auth', () => {
  const isAuthenticated = ref(localStorage.getItem(AUTH_STORAGE_KEY) === 'true')
  const userEmail = ref<string | null>(localStorage.getItem(`${AUTH_STORAGE_KEY}:email`))

  function login(email: string) {
    // Stub: no backend yet. Replace with a real auth request later.
    isAuthenticated.value = true
    userEmail.value = email
    localStorage.setItem(AUTH_STORAGE_KEY, 'true')
    localStorage.setItem(`${AUTH_STORAGE_KEY}:email`, email)
  }

  function logout() {
    isAuthenticated.value = false
    userEmail.value = null
    localStorage.removeItem(AUTH_STORAGE_KEY)
    localStorage.removeItem(`${AUTH_STORAGE_KEY}:email`)
  }

  return { isAuthenticated, userEmail, login, logout }
})
