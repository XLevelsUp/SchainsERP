import { describe, expect, it } from 'vitest'
import { buildAdminNavItems } from '@/admin/lib/nav'
import { companyNameFrom, DEFAULT_COMPANY_NAME } from '@/lib/companyName'
import { buildNavItems, visibleNavItems } from '@/lib/nav'
import type { NavItem } from '@/types/nav'

const labels = (items: NavItem[]) => items.map((i) => i.label)
const group = (items: NavItem[], label: string) => {
  const found = items.find((i) => i.type === 'group' && i.label === label)
  if (!found || found.type !== 'group') throw new Error(`no group ${label}`)
  return found
}

const COMPANY_CHILDREN = ['Roles History', 'Customers', 'Employees', 'Retailers', 'Customer FITEM']

describe('the side menus (2026-11-24)', () => {
  it('orders the app menu as agreed, the company group named from the setting, the rest under More', () => {
    const items = buildNavItems('NIHAA JEWELS')
    expect(labels(items)).toEqual([
      'Stocks',
      'Cash Management',
      'Consolidated Report',
      'Items OB CB',
      'One Day Action Report',
      'Item Conversion Group History',
      'Item Change History',
      'NIHAA JEWELS',
      'ID Wise',
      'Phone Book',
      'More',
    ])
    expect(group(items, 'NIHAA JEWELS').children.map((c) => [c.label, c.to])).toEqual([
      ['Roles History', '/company/roles-history'],
      ['Customers', '/company/customers'],
      ['Employees', '/company/employees'],
      ['Retailers', '/company/retailers'],
      ['Customer FITEM', '/company/customer-fitem'],
    ])
    // Nothing became unreachable; Items moved to the console (2026-11-24).
    expect(group(items, 'More').children.map((c) => c.to)).toEqual(
      expect.arrayContaining(['/', '/system-settings', '/customer-touch', '/metal-live', '/orders']),
    )
  })

  it('no longer offers Items in the app', () => {
    const more = group(buildNavItems('X'), 'More')
    expect(more.children.map((c) => c.to)).not.toContain('/items')
  })

  it('still hides Phone Book without its permission', () => {
    const items = visibleNavItems(buildNavItems('X'), () => false)
    expect(labels(items)).not.toContain('Phone Book')
    expect(labels(visibleNavItems(buildNavItems('X'), () => true))).toContain('Phone Book')
  })

  it('shows each item history page only with its Stocks page feature (2026-11-25)', () => {
    const shown = (granted: string[]) =>
      labels(visibleNavItems(buildNavItems('X'), (p) => granted.includes(p))).filter((l) => l.startsWith('Item '))
    expect(shown([])).toEqual([])
    expect(shown(['is_item_conversion_need_shown'])).toEqual(['Item Conversion Group History'])
    expect(shown(['is_item_change_need_shown'])).toEqual(['Item Change History'])
    expect(shown(['is_item_conversion_need_shown', 'is_item_change_need_shown'])).toEqual([
      'Item Conversion Group History',
      'Item Change History',
    ])
    // The console's links have no permission: admins see every head's.
    const admin = buildAdminNavItems('X').filter((i) => i.label.startsWith('Item ') && i.type === 'link')
    expect(admin.map((i) => (i.type === 'link' ? [i.to, i.permission] : []))).toEqual([
      ['/admin/item-conversion-group-history', undefined],
      ['/admin/item-change-history', undefined],
    ])
  })

  it('orders the admin menu as agreed, with Salary and the company group', () => {
    const items = buildAdminNavItems('NIHAA JEWELS')
    expect(labels(items)).toEqual([
      'Roles',
      'Phone Book',
      'Users',
      'ID Wise',
      'One Day Action Report',
      'Item Conversion Group History',
      'Item Change History',
      'Consolidated Report',
      'Items OB CB',
      'NIHAA JEWELS',
      'Customer Touch',
      'Items',
      'Salary',
      'Head Employee Mappings',
      'My account',
    ])
    const company = group(items, 'NIHAA JEWELS')
    expect(company.children.map((c) => c.label)).toEqual(COMPANY_CHILDREN)
    expect(company.children.every((c) => c.to.startsWith('/admin/company/'))).toBe(true)
  })

  it('falls back to a plain name when the setting is missing or not text', () => {
    expect(companyNameFrom(' NIHAA JEWELS ')).toBe('NIHAA JEWELS')
    expect(companyNameFrom('')).toBe(DEFAULT_COMPANY_NAME)
    expect(companyNameFrom(null)).toBe(DEFAULT_COMPANY_NAME)
    expect(companyNameFrom([1])).toBe(DEFAULT_COMPANY_NAME)
  })
})
