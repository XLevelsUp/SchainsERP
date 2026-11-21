import { defineStore } from 'pinia'
import { adminSession } from '@/lib/authSession'
import { useSessionState } from '@/stores/sessionState'
import type { AdminUser } from '@/types'
import { adminAuthApi } from '../lib/adminAuthApi'

/*
|--------------------------------------------------------------------------
| Admin auth store — the admin console's session (POST /api/v1/admin/login).
|--------------------------------------------------------------------------
| An admin account (table `admins`), not a head: separate from the app's
| useAuthStore, with its own persisted session, token and sign-out.
|--------------------------------------------------------------------------
*/
export const useAdminAuthStore = defineStore('adminAuth', () =>
  useSessionState(adminSession, adminAuthApi, (admin: AdminUser) => admin),
)
