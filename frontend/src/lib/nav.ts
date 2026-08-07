import {
  LayoutDashboard,
  Gem,
  Package,
  Settings,
  ShieldCheck,
  UserCog,
  Users,
  Hand,
  Landmark,
  ArrowLeftRight,
  ArrowDownUp,
  Coins,
  PackageMinus,
  PackagePlus,
  FileText,
  MoreHorizontal,
} from 'lucide-vue-next'
import type { NavItem } from '@/types/nav'

export const navItems: NavItem[] = [
  { type: 'link', label: 'Dashboard', to: '/', icon: LayoutDashboard },
  { type: 'link', label: 'Clients', to: '/clients', icon: Users },
  {
    type: 'group',
    label: 'Settings',
    icon: Settings,
    children: [
      { type: 'link', label: 'Items', to: '/items', icon: Gem },
      { type: 'link', label: 'Fitem Boxes', to: '/fitem-boxes', icon: Package },
    ],
  },
  {
    type: 'group',
    label: 'Administration',
    icon: ShieldCheck,
    children: [
      { type: 'link', label: 'Roles', to: '/roles', icon: ShieldCheck },
      { type: 'link', label: 'Users', to: '/users', icon: UserCog },
      { type: 'link', label: 'Customer Touch', to: '/customer-touch', icon: Hand },
      { type: 'link', label: 'Bank Details', to: '/bank-details', icon: Landmark },
    ],
  },
  {
    type: 'group',
    label: 'Others',
    icon: MoreHorizontal,
    children: [
      { type: 'link', label: 'Cash Transactions', to: '/cash-transactions', icon: ArrowLeftRight },
      { type: 'link', label: 'Cash In / Out', to: '/cash-in-out', icon: ArrowDownUp },
      { type: 'link', label: 'Sale Gold', to: '/sale-gold', icon: Coins },
      { type: 'link', label: 'Stock Out', to: '/stock-out', icon: PackageMinus },
      { type: 'link', label: 'Stock In', to: '/stock-in', icon: PackagePlus },
      { type: 'link', label: 'GMS In', to: '/gms-in', icon: PackagePlus },
      { type: 'link', label: 'Numeric Wastage In', to: '/numeric-wastage-in', icon: PackagePlus },
      { type: 'link', label: 'Pages', to: '/pages', icon: FileText },
    ],
  },
]
