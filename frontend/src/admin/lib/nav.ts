import {
  ArrowUpDown,
  BookUser,
  CalendarClock,
  ChartBar,
  Gem,
  Hand,
  HandCoins,
  KeyRound,
  Network,
  ShieldCheck,
  UserCog,
} from 'lucide-vue-next'
import { companyGroup } from '@/lib/nav'
import type { NavItem } from '@/types/nav'

/**
 * The admin console's sidebar, in the order agreed on 2026-11-24: Roles,
 * Phone Book, Users, ID Wise, One Day Action Report, Consolidated Report,
 * Items OB CB, the company group, Customer Touch, Salary — then Head
 * Employee Mappings and My account. Salary and the company group's pages
 * aren't built yet (ComingSoonView).
 */
export function buildAdminNavItems(companyName: string): NavItem[] {
  return [
    { type: 'link', label: 'Roles', to: '/admin/roles', icon: ShieldCheck },
    { type: 'link', label: 'Phone Book', to: '/admin/phone-book', icon: BookUser },
    { type: 'link', label: 'Users', to: '/admin/users', icon: UserCog },
    { type: 'link', label: 'ID Wise', to: '/admin/id-wise-report', icon: ArrowUpDown },
    { type: 'link', label: 'One Day Action Report', to: '/admin/one-day-action', icon: CalendarClock },
    { type: 'link', label: 'Consolidated Report', to: '/admin/consolidated-report', icon: ChartBar },
    { type: 'link', label: 'Items OB CB', to: '/admin/items-obcb-report', icon: ChartBar },
    companyGroup(companyName, '/admin'),
    { type: 'link', label: 'Customer Touch', to: '/admin/customer-touch', icon: Hand },
    // The Items page (2026-11-24), console only.
    { type: 'link', label: 'Items', to: '/admin/items', icon: Gem },
    { type: 'link', label: 'Salary', to: '/admin/salary', icon: HandCoins },
    { type: 'link', label: 'Head Employee Mappings', to: '/admin/head-employee-mappings', icon: Network },
    { type: 'link', label: 'My account', to: '/admin/account', icon: KeyRound },
  ]
}
