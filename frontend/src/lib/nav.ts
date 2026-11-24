import {
  LayoutDashboard,
  Package,
  Users,
  Hand,
  Landmark,
  SlidersHorizontal,
  ScrollText,
  ArrowLeftRight,
  Coins,
  Banknote,
  CircleDollarSign,
  Wallet,
  FileText,
  ChartBar,
  ArrowUpDown,
  Activity,
  CalendarClock,
  CalendarRange,
  BookUser,
  FlaskConical,
  MoreHorizontal,
  ClipboardList,
  Building2,
  History,
  Store,
  UserRound,
} from 'lucide-vue-next'
import type { AppPermission } from '@/types'
import type { NavGroup, NavItem } from '@/types/nav'

// The sidebar a signed-in user gets (lib/appPermissions.ts): a link behind a
// permission they don't have is left out, and so is a group left empty.
export function visibleNavItems(
  items: NavItem[],
  can: (permission: AppPermission) => boolean,
): NavItem[] {
  return items.flatMap((item): NavItem[] => {
    if (item.type === 'link') {
      return !item.permission || can(item.permission) ? [item] : []
    }
    const children = item.children.filter((c) => !c.permission || can(c.permission))
    return children.length ? [{ ...item, children }] : []
  })
}

/**
 * The company group, in both logins (2026-11-24): headed with the company
 * name (the `company_name` setting). Its pages aren't built yet; each opens
 * ComingSoonView. `base` is '' in the app and '/admin' in the console.
 */
export const COMPANY_PAGES = [
  { slug: 'roles-history', label: 'Roles History', icon: History },
  { slug: 'customers', label: 'Customers', icon: Users },
  { slug: 'employees', label: 'Employees', icon: UserRound },
  { slug: 'retailers', label: 'Retailers', icon: Store },
  { slug: 'customer-fitem', label: 'Customer FITEM', icon: Package },
] as const

export function companyGroup(companyName: string, base: '' | '/admin'): NavGroup {
  return {
    type: 'group',
    label: companyName,
    icon: Building2,
    children: COMPANY_PAGES.map((p) => ({ type: 'link', label: p.label, to: `${base}/company/${p.slug}`, icon: p.icon })),
  }
}

/**
 * The app's sidebar, in the order agreed on 2026-11-24: Stocks, Cash
 * Management, Consolidated Report, Items OB CB, One Day Action Report, the
 * company group, ID Wise, Phone Book. Every other screen sits in "More" at
 * the bottom, so nothing becomes unreachable.
 */
export function buildNavItems(companyName: string): NavItem[] {
  return [
    { type: 'link', label: 'Stocks', to: '/stock', icon: Package },
    { type: 'link', label: 'Cash Management', to: '/cash-management', icon: ArrowLeftRight },
    { type: 'link', label: 'Consolidated Report', to: '/consolidated-report', icon: ChartBar },
    { type: 'link', label: 'Items OB CB', to: '/items-obcb-report', icon: ChartBar },
    { type: 'link', label: 'One Day Action Report', to: '/one-day-action', icon: CalendarClock },
    companyGroup(companyName, ''),
    { type: 'link', label: 'ID Wise', to: '/id-wise-report', icon: ArrowUpDown },
    {
      type: 'link',
      label: 'Phone Book',
      to: '/phone-book',
      icon: BookUser,
      permission: 'is_phone_book_need_shown',
    },
    {
      type: 'group',
      label: 'More',
      icon: MoreHorizontal,
      // Users, Head Employee Mappings, Roles and Items are in the admin
      // console (admin/lib/nav.ts).
      children: [
        { type: 'link', label: 'Dashboard', to: '/', icon: LayoutDashboard },
        { type: 'link', label: 'Clients', to: '/clients', icon: Users },
        { type: 'link', label: 'Fitem Boxes', to: '/fitem-boxes', icon: Package },
        { type: 'link', label: 'System Settings', to: '/system-settings', icon: SlidersHorizontal },
        { type: 'link', label: 'Customer Touch', to: '/customer-touch', icon: Hand },
        { type: 'link', label: 'Customer Touch Mappings', to: '/customer-touch-mappings', icon: Hand },
        { type: 'link', label: 'Bank Details', to: '/bank-details', icon: Landmark },
        { type: 'link', label: 'System Logs', to: '/system-logs', icon: ScrollText },
        { type: 'link', label: 'Sale Gold', to: '/sale-gold', icon: Coins },
        { type: 'link', label: 'Purchase Gold', to: '/purchase-gold', icon: Wallet },
        { type: 'link', label: 'Cash To Gold', to: '/cash-to-gold', icon: Banknote },
        { type: 'link', label: 'Gold To Cash', to: '/gold-to-cash', icon: CircleDollarSign },
        { type: 'link', label: 'Stock Auto Entry', to: '/stock-auto-entry', icon: ArrowLeftRight },
        { type: 'link', label: 'Cash Transactions Report', to: '/cash-transactions-report', icon: ChartBar },
        { type: 'link', label: 'Metal Live', to: '/metal-live', icon: Activity },
        { type: 'link', label: 'Daywise Grand', to: '/daywise-grand', icon: CalendarRange },
        { type: 'link', label: 'Orders', to: '/orders', icon: ClipboardList },
        { type: 'link', label: 'Pages', to: '/pages', icon: FileText },
        { type: 'link', label: 'Metal Picker (Test)', to: '/metal-picker-test', icon: FlaskConical },
      ],
    },
  ]
}
