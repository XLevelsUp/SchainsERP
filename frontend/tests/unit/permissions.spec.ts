import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { markRaw } from 'vue'
import type { RouteLocationNormalized, RouteLocationNormalizedLoaded } from 'vue-router'
import { appSession } from '@/lib/authSession'
import { visibleNavItems } from '@/lib/nav'
import { authGuard } from '@/router/guards'
import { useAuthStore } from '@/stores/auth'
import { toAuthUser } from '@/stores/sessionState'
import type { AuthUserPayload } from '@/types'
import type { NavItem } from '@/types/nav'

// The app shows a signed-in user only what their permissions allow
// (lib/appPermissions.ts): session → can() → sidebar and routes.

function signIn(permissions: Partial<Record<string, boolean | number>>) {
  const payload = { user_id: 6, name: 'Head', user_name: 'head', role_id: '3', role: { role: 'HEAD' }, ...permissions }
  appSession.save({ user: toAuthUser(payload as AuthUserPayload), token: 't' })
  setActivePinia(createPinia())
  return useAuthStore()
}

const icon = markRaw({ render: () => null })

describe('permissions', () => {
  beforeEach(() => {
    appSession.clear()
  })

  it('reads them from the login payload; anything missing is off', () => {
    const user = toAuthUser({
      user_id: 1,
      name: 'A',
      user_name: 'a',
      role_id: '3',
      is_item_change_need_shown: 1,
      is_phone_book_need_shown: true,
      is_create_order_shown: 0,
    } as AuthUserPayload)
    expect(user.permissions).toMatchObject({
      is_item_change_need_shown: true,
      is_phone_book_need_shown: true,
      is_create_order_shown: false,
      is_cash_mngmt_need_to_shown: false,
    })
  })

  it('can() answers from the session', () => {
    const auth = signIn({ is_phone_book_need_shown: true })
    expect(auth.can('is_phone_book_need_shown')).toBe(true)
    expect(auth.can('is_item_conversion_need_shown')).toBe(false)
  })

  it('leaves out sidebar links and emptied groups the user may not see', () => {
    const items: NavItem[] = [
      { type: 'link', label: 'Dashboard', to: '/', icon },
      { type: 'link', label: 'Phone Book', to: '/phone-book', icon, permission: 'is_phone_book_need_shown' },
      {
        type: 'group',
        label: 'Gated',
        icon,
        children: [{ type: 'link', label: 'Only', to: '/x', icon, permission: 'is_create_order_shown' }],
      },
    ]
    const labels = (can: (p: string) => boolean) => visibleNavItems(items, can).map((i) => i.label)
    expect(labels(() => false)).toEqual(['Dashboard'])
    expect(labels((p) => p === 'is_phone_book_need_shown')).toEqual(['Dashboard', 'Phone Book'])
    expect(labels(() => true)).toEqual(['Dashboard', 'Phone Book', 'Gated'])
  })

  it('sends a user away from a page behind a permission they don’t have', () => {
    const route = (meta: Record<string, unknown>) =>
      ({ fullPath: '/phone-book', meta: { realm: 'app', requiresAuth: true, ...meta } }) as unknown as RouteLocationNormalized
    const from = {} as RouteLocationNormalizedLoaded
    const guard = (to: RouteLocationNormalized) => authGuard.call(undefined, to, from, () => {})

    signIn({})
    expect(guard(route({ permission: 'is_phone_book_need_shown' }))).toEqual({ name: 'dashboard' })
    expect(guard(route({}))).toBeUndefined()

    signIn({ is_phone_book_need_shown: true })
    expect(guard(route({ permission: 'is_phone_book_need_shown' }))).toBeUndefined()
  })

  it('still sends a signed-out user to sign in first', () => {
    setActivePinia(createPinia())
    const to = { fullPath: '/phone-book', meta: { realm: 'app', requiresAuth: true, permission: 'is_phone_book_need_shown' } }
    expect(authGuard.call(undefined, to as unknown as RouteLocationNormalized, {} as RouteLocationNormalizedLoaded, () => {})).toEqual({
      name: 'login',
      query: { redirect: '/phone-book' },
    })
  })
})
