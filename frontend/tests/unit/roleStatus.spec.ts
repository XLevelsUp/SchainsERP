import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminApiWithSpecialPassword } from '@/admin/lib/adminApi'
import { api, ApiError } from '@/lib/api'
import { appSession } from '@/lib/authSession'
import { toAuthUser } from '@/stores/sessionState'
import { createRolesApi, isRoleActive } from '@/lib/rolesApi'
import type { AuthUserPayload } from '@/types'

// Switching a role on or off from the admin console's Roles page (agreed
// 2026-11-23): the request carries the special password, a rejected one
// keeps the prompt open, and an app user whose role was switched off is
// signed out by the backend's 401.

function respondWith(status: number, body: unknown) {
  const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status }))
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.unstubAllGlobals()
  appSession.clear()
})

describe('role status', () => {
  it('sends the new status with the special password', async () => {
    const fetchMock = respondWith(200, {
      success: true,
      message: 'Role EMPLOYEE deactivated.',
      data: { id: 7, role: 'EMPLOYEE', is_active: false, users_count: 3 },
    })

    const result = await createRolesApi(adminApiWithSpecialPassword('pw-1')).setStatus(7, false)

    expect(result).toEqual({ id: 7, role: 'EMPLOYEE', is_active: false, users_count: 3 })
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/admin\/roles\/7\/status$/)
    expect(init.method).toBe('PUT')
    expect(JSON.parse(init.body as string)).toEqual({ is_active: false })
    expect((init.headers as Record<string, string>)['X-Special-Password']).toBe('pw-1')
  })

  it('surfaces a wrong special password on its field', async () => {
    respondWith(422, {
      message: 'The special password is incorrect.',
      errors: { special_password: ['The special password is incorrect.'] },
    })

    const error = await createRolesApi(adminApiWithSpecialPassword('nope'))
      .setStatus(7, false)
      .catch((err: unknown) => err)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).errors?.special_password?.[0]).toBe('The special password is incorrect.')
  })

  it('treats a role with no saved status as active', () => {
    expect(isRoleActive({ id: 1, role: 'HEAD', touch: null })).toBe(true)
    expect(isRoleActive({ id: 1, role: 'HEAD', touch: null, is_active: false })).toBe(false)
  })

  it('signs an app user out when the backend ends the session', async () => {
    const payload = { user_id: 9, name: 'Emp', user_name: 'emp', role_id: '2', role: { role: 'EMPLOYEE' } }
    appSession.save({ user: toAuthUser(payload as AuthUserPayload), token: 'live-token' })
    respondWith(401, { success: false, message: 'Your role has been deactivated. Please contact your administrator.' })

    await expect(api.get('/me')).rejects.toMatchObject({ status: 401 })
    expect(appSession.getToken()).toBeNull()
  })
})
