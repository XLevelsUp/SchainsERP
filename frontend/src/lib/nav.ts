import { LayoutDashboard, Users, FileText } from 'lucide-vue-next'
import type { NavItem } from '@/types/nav'

export const navItems: NavItem[] = [
  { label: 'Dashboard', to: '/', icon: LayoutDashboard },
  { label: 'Clients', to: '/clients', icon: Users },
  { label: 'Pages', to: '/pages', icon: FileText },
]
