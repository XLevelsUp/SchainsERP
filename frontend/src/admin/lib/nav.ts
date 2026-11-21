import { KeyRound, Network, UserCog } from 'lucide-vue-next'
import type { NavItem } from '@/types/nav'

// The admin console's sidebar: creating users and assigning them to heads
// (agreed 2026-11-20), plus the admin's own passwords.
export const adminNavItems: NavItem[] = [
  { type: 'link', label: 'Users', to: '/admin/users', icon: UserCog },
  { type: 'link', label: 'Head Employee Mappings', to: '/admin/head-employee-mappings', icon: Network },
  { type: 'link', label: 'My account', to: '/admin/account', icon: KeyRound },
]
