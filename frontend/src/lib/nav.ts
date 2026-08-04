import {
  LayoutDashboard,
  Gem,
  Package,
  ShieldCheck,
  UserCog,
  Users,
  Hand,
  FileText,
} from 'lucide-vue-next'
import type { NavItem } from '@/types/nav'

export const navItems: NavItem[] = [
  { label: 'Dashboard', to: '/', icon: LayoutDashboard },
  { label: 'Items', to: '/items', icon: Gem },
  { label: 'Fitem Boxes', to: '/fitem-boxes', icon: Package },
  { label: 'Roles', to: '/roles', icon: ShieldCheck },
  { label: 'Users', to: '/users', icon: UserCog },
  { label: 'Clients', to: '/clients', icon: Users },
  { label: 'Customer Touch', to: '/customer-touch', icon: Hand },
  { label: 'Pages', to: '/pages', icon: FileText },
]
