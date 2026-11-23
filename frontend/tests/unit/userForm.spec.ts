import { describe, expect, it } from 'vitest'
import {
  employeeIdsOf,
  flagGroupsFor,
  headIdsOf,
  makeEmptyUserForm,
  sameIds,
  userToForm,
} from '@/admin/lib/userForm'
import { profileForRole, profileHas } from '@/admin/lib/userFormProfiles'
import type { UserDetail, UserFormProfile, UserFormProfileCatalog } from '@/types'

const profile = (role: string, sections: UserFormProfile['sections'], extra: Partial<UserFormProfile> = {}) =>
  ({ role, is_default: false, lock_role_on_edit: false, sections, flags: [], ...extra }) as UserFormProfile

const catalog: UserFormProfileCatalog = {
  sections: ['heads', 'items'],
  flags: [],
  default: { role: null, is_default: true, lock_role_on_edit: false, sections: ['heads', 'items'], flags: [] },
  by_role_id: {
    '3': profile('HEAD', ['head_users', 'salary']),
    '7': profile('ADMIN', ['heads'], { lock_role_on_edit: true }),
  },
}

describe('user form helpers', () => {
  it('compares head sets regardless of order and blanks', () => {
    expect(headIdsOf([{ head_id: 5 }, { head_id: null }, { head_id: 2 }])).toEqual([2, 5])
    expect(employeeIdsOf([{ employee_id: 9 }, { employee_id: 1 }])).toEqual([1, 9])
    expect(sameIds([1, 2], [1, 2])).toBe(true)
    expect(sameIds([1, 2], [2])).toBe(false)
  })

  it('maps a fetched user into the form, mappings and HEAD fields included', () => {
    const user = {
      user_id: 8,
      name: 'Nihaa',
      user_name: 'nihaa',
      phone_no: '9000000000',
      role_id: 3,
      category_name: 'BOTH',
      is_active: true,
      is_delete: false,
      is_billable: false,
      is_item_change_need_shown: true,
      is_phone_book_need_shown: 1,
      head_employee_mappings: [{ head_id: 4 }],
      head_user_mappings: [{ employee_id: 8 }, { employee_id: 2 }],
      customer_touch_mappings: [{ customer_touch_id: 1, is_active: 0 }],
      item_mappings: [{ item_id: 3, item_grams_total: '25.0000', item_purity_total: null, is_primary: 1 }],
      aadhar_number: '1234',
      per_day_salary: 650.5,
      joining_date: '2026-05-30',
    } as unknown as UserDetail

    const form = userToForm(user)
    expect(form.role_id).toBe('3')
    expect(form.is_item_change_need_shown).toBe(true)
    expect(form.is_phone_book_need_shown).toBe(true)
    expect(form.is_create_order_shown).toBe(false)
    expect(form.head_mappings).toEqual([{ head_id: 4 }])
    expect(form.head_user_mappings).toEqual([{ employee_id: 8 }, { employee_id: 2 }])
    expect(form.customer_touch_mappings).toEqual([{ customer_touch_id: 1, is_active: false }])
    expect(form.item_mappings[0]).toEqual({ item_id: 3, item_grams_total: '25.0000', item_purity_total: '0', is_primary: 1 })
    expect(form.aadhar_number).toBe('1234')
    expect(form.per_day_salary).toBe('650.5')
    expect(form.joining_date).toBe('2026-05-30')
    // Files and secrets never come back from the server.
    expect(form.report_password).toBe('')
    expect(form.image_file).toBeNull()
  })

  it('starts a new user with every permission off and no role', () => {
    const form = makeEmptyUserForm()
    expect(form.role_id).toBe('')
    expect(form.is_item_change_need_shown).toBe(false)
    expect(form.head_user_mappings).toEqual([])
  })

  it('cuts the permission groups down to a profile', () => {
    const groups = flagGroupsFor(['is_item_change_need_shown', 'is_phone_book_need_shown'])
    expect(groups.flatMap((g) => g.flags.map((f) => f.key))).toEqual([
      'is_item_change_need_shown',
      'is_phone_book_need_shown',
    ])
    expect(groups.every((g) => g.flags.length > 0)).toBe(true)
  })
})

describe('role profiles', () => {
  it('finds a role’s profile, and the full form for a role it doesn’t know', () => {
    expect(profileForRole(catalog, 3)?.role).toBe('HEAD')
    expect(profileForRole(catalog, '7')?.lock_role_on_edit).toBe(true)
    expect(profileForRole(catalog, 99)?.is_default).toBe(true)
    expect(profileForRole(catalog, '')).toBeNull()
    expect(profileForRole(null, 3)).toBeNull()
  })

  it('answers whether a profile has a section', () => {
    expect(profileHas(profileForRole(catalog, 3), 'salary')).toBe(true)
    expect(profileHas(profileForRole(catalog, 3), 'heads')).toBe(false)
    expect(profileHas(null, 'heads')).toBe(false)
  })
})
