import { defineStore } from 'pinia'
import { computed } from 'vue'
import { authApi } from '@/lib/authApi'
import { appSession } from '@/lib/authSession'
import type { AppPermission } from '@/types'
import { toAuthUser, useSessionState } from './sessionState'

/*
|--------------------------------------------------------------------------
| Auth store — the operational app's session (POST /api/v1/login).
|--------------------------------------------------------------------------
| Login, logout, boot validation and cross-tab adoption live in
| useSessionState, shared with the admin console's store
| (admin/stores/adminAuth.ts). The two are different accounts: heads and
| other user_details users sign in here, admins in the admin console.
|--------------------------------------------------------------------------
*/
export const useAuthStore = defineStore('auth', () => {
  const state = useSessionState(appSession, authApi, toAuthUser)

  // Mirrors the backend's access rules (ScopesRecordAccess, EnsureCanActAsHead):
  // heads see and do everything; the "can act as head" permission lets a user
  // enter values for any head but not see balances. Display only: the API
  // enforces all of it.
  const isHead = computed(() => (state.user.value?.role_name ?? '').toUpperCase() === 'HEAD')
  const canActAsHead = computed(
    () => isHead.value || state.user.value?.is_act_as_head_enabled === true,
  )

  // What the app shows this user (lib/appPermissions.ts): only the features
  // an admin switched on. A missing value reads as off, as on the backend.
  function can(permission: AppPermission): boolean {
    return state.user.value?.permissions?.[permission] === true
  }

  return { ...state, isHead, canActAsHead, can }
})
