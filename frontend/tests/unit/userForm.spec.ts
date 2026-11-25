import { describe, expect, it } from 'vitest'
import {
  addDefaultPrimaryItem,
  applyRoleDefaults,
  employeeIdsOf,
  flagGroupsFor,
  FULL_USER_FORM_PROFILE,
  headIdsOf,
  makeEmptyUserForm,
  sameIds,
  userToForm,
} from '@/admin/lib/userForm'
import { profileForRole, profileHas } from '@/admin/lib/userFormProfiles'
import type { UserDetail, UserFormProfile, UserFormProfileCatalog } from '@/types'

const profile = (role: string, sections: UserFormProfile['sections'], extra: Partial<UserFormProfile> = {}) =>
  ({
    role,
    is_default: false,
    lock_role_on_edit: false,
    signs_in: true,
    default_category: null,
    default_primary_item: null,
    default_flags: [],
    joining_date_defaults_to_today: false,
    sections,
    flags: [],
    ...extra,
  }) as UserFormProfile

const catalog: UserFormProfileCatalog = {
  sections: ['heads', 'items'],
  flags: [],
  default: {
    role: null,
    is_default: true,
    lock_role_on_edit: false,
    signs_in: true,
    default_category: null,
    default_primary_item: null,
    default_flags: [],
    joining_date_defaults_to_today: false,
    sections: ['heads', 'items'],
    flags: [],
  },
  by_role_id: {
    '3': profile('HEAD', ['head_users', 'salary']),
    '7': profile('ADMIN', ['heads'], { lock_role_on_edit: true }),
    '1': profile('CUSTOMER', ['heads', 'items'], {
      signs_in: false,
      default_category: 'PURITY',
      default_primary_item: 'GOLD',
    }),
  },
}

describe('the CUSTOMER profile', () => {
  it("carries the customer's defaults and no sign-in; other roles sign in", () => {
    expect(profileForRole(catalog, '1')).toMatchObject({
      signs_in: false,
      default_category: 'PURITY',
      default_primary_item: 'GOLD',
    })
    expect(FULL_USER_FORM_PROFILE.signs_in).toBe(true)
    expect(FULL_USER_FORM_PROFILE.default_category).toBeNull()
    // The full form has no role-only sections (fixed 2026-11-25).
    expect(FULL_USER_FORM_PROFILE.sections).not.toContain('head_users')
    expect(FULL_USER_FORM_PROFILE.sections).not.toContain('additional_phone')
    expect(FULL_USER_FORM_PROFILE.sections).not.toContain('employee')
    expect(FULL_USER_FORM_PROFILE.sections).toContain('heads')
  })
})

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

describe('role defaults on a new user', () => {
  const salary = profile('SALARY', ['additional_phone', 'items', 'salary', 'joining_date'], {
    signs_in: false,
    default_category: 'GRAMS',
    default_primary_item: 'GOLD',
    default_flags: ['is_salary_person'],
    joining_date_defaults_to_today: true,
    flags: ['is_salary_person'],
  })
  const customer = profile('CUSTOMER', ['items'], { signs_in: false, default_category: 'PURITY', default_primary_item: 'GOLD' })
  const head = profile('HEAD', ['salary'], { flags: ['is_salary_person'] })
  const items = [
    { item_id: 1, item_name: 'Metal' },
    { item_id: 2, item_name: ' gold ' },
  ]

  it('starts a SALARY user on GRAMS, GOLD, salary person YES and today', () => {
    const form = makeEmptyUserForm()
    applyRoleDefaults(form, salary, null, items, '2026-11-23')
    expect(form.category_name).toBe('GRAMS')
    expect(form.is_salary_person).toBe(true)
    expect(form.joining_date).toBe('2026-11-23')
    expect(form.item_mappings).toEqual([{ item_id: 2, item_grams_total: '0', item_purity_total: '0', is_primary: 1 }])
  })

  it('lets the category and permissions follow a role change, but keeps what the admin chose', () => {
    const form = makeEmptyUserForm()
    applyRoleDefaults(form, customer, null, [], '2026-11-23')
    expect(form.category_name).toBe('PURITY')

    applyRoleDefaults(form, salary, customer, [], '2026-11-23')
    expect(form.category_name).toBe('GRAMS')
    expect(form.is_salary_person).toBe(true)

    // Away from SALARY: its default permission goes off again.
    applyRoleDefaults(form, head, salary, [], '2026-11-23')
    expect(form.is_salary_person).toBe(false)
    expect(form.category_name).toBe('GRAMS') // HEAD has no default category

    // A category the admin picked stays.
    const picked = makeEmptyUserForm()
    picked.category_name = 'BOTH'
    applyRoleDefaults(picked, salary, customer, [], '2026-11-23')
    expect(picked.category_name).toBe('BOTH')
  })

  it('keeps a joining date already set and skips a missing item', () => {
    const form = makeEmptyUserForm()
    form.joining_date = '2026-01-01'
    applyRoleDefaults(form, salary, null, [{ item_id: 1, item_name: 'Metal' }], '2026-11-23')
    expect(form.joining_date).toBe('2026-01-01')
    expect(form.item_mappings).toEqual([])
  })
})

describe('the HEAD defaults', () => {
  it('starts a new head with GOLD as the primary item, grams and purity 0', () => {
    const head = profile('HEAD', ['items'], { default_category: 'BOTH', default_primary_item: 'GOLD' })
    const form = makeEmptyUserForm()
    applyRoleDefaults(form, head, null, [{ item_id: 1, item_name: 'Metal' }, { item_id: 9, item_name: 'GOLD' }], '2026-11-24')
    expect(form.item_mappings).toEqual([{ item_id: 9, item_grams_total: '0', item_purity_total: '0', is_primary: 1 }])

    // Switching roles doesn't add a second row.
    applyRoleDefaults(form, head, head, [{ item_id: 9, item_name: 'GOLD' }], '2026-11-24')
    expect(form.item_mappings).toHaveLength(1)
  })

  it('starts a new head on BOTH, and replaces another role’s default when switched to', () => {
    const head = profile('HEAD', ['salary'], { default_category: 'BOTH' })
    const customer = profile('CUSTOMER', ['items'], { signs_in: false, default_category: 'PURITY' })

    const form = makeEmptyUserForm()
    applyRoleDefaults(form, head, null, [], '2026-11-24')
    expect(form.category_name).toBe('BOTH')

    applyRoleDefaults(form, customer, head, [], '2026-11-24')
    expect(form.category_name).toBe('PURITY')
    applyRoleDefaults(form, head, customer, [], '2026-11-24')
    expect(form.category_name).toBe('BOTH')
  })
})

describe('the primary item when the items list arrives late', () => {
  it('adds GOLD once, only when no items are listed', () => {
    const head = profile('HEAD', ['items'], { default_primary_item: 'GOLD' })
    const form = makeEmptyUserForm()
    addDefaultPrimaryItem(form, head, [])
    expect(form.item_mappings).toEqual([])
    addDefaultPrimaryItem(form, head, [{ item_id: 9, item_name: 'GOLD' }])
    addDefaultPrimaryItem(form, head, [{ item_id: 9, item_name: 'GOLD' }])
    expect(form.item_mappings).toEqual([{ item_id: 9, item_grams_total: '0', item_purity_total: '0', is_primary: 1 }])
  })
})
