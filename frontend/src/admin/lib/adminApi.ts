import { API_BASE_URL, createApiClient, type ApiClient } from '@/lib/api'
import { adminSession } from '@/lib/authSession'

/*
|--------------------------------------------------------------------------
| The admin console's HTTP client.
|--------------------------------------------------------------------------
| Same request code as the app's `api` (lib/api.ts), bound to the admin
| realm instead: /api/v1/admin (schainbackend/routes/admin.php) and the
| admin session's token. A 401 here clears only the admin session, and
| main.ts sends the admin to /admin/login.
|
| Every admin screen talks to the backend through this client, never the
| app's: the backend refuses app tokens on admin routes and admin tokens on
| app routes (App\Support\AdminAccess).
|--------------------------------------------------------------------------
*/
const ADMIN_BASE_URL = `${API_BASE_URL}/admin`

export const adminApi = createApiClient({ baseUrl: ADMIN_BASE_URL, session: adminSession })

// The header RequireSpecialPassword reads (App\Support\AdminAccess).
export const SPECIAL_PASSWORD_HEADER = 'X-Special-Password'

/**
 * A one-off client for a single sensitive change (assigning heads, deleting
 * a user) that sends the admin's special password with it. Build it at the
 * moment of the change and let it go; the password is never stored.
 *
 * A missing or wrong password comes back as a 422 on `special_password`,
 * which the screens show on their special password field.
 */
export function adminApiWithSpecialPassword(specialPassword: string): ApiClient {
  return createApiClient({
    baseUrl: ADMIN_BASE_URL,
    session: adminSession,
    headers: { [SPECIAL_PASSWORD_HEADER]: specialPassword },
  })
}
