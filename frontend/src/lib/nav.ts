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
  Coins,
  Banknote,
  CircleDollarSign,
  Wallet,
  FileText,
  ChartBar,
  FlaskConical,
  MoreHorizontal,
} from 'lucide-vue-next'
import type { NavItem } from '@/types/nav'

export const navItems: NavItem[] = [
  { type: 'link', label: 'Dashboard', to: '/', icon: LayoutDashboard },
  { type: 'link', label: 'Stock', to: '/stock', icon: Package },
  { type: 'link', label: 'Cash Management', to: '/cash-management', icon: ArrowLeftRight },
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
      { type: 'link', label: 'Sale Gold', to: '/sale-gold', icon: Coins },
      { type: 'link', label: 'Purchase Gold', to: '/purchase-gold', icon: Wallet },
      { type: 'link', label: 'Cash To Gold', to: '/cash-to-gold', icon: Banknote },
      { type: 'link', label: 'Gold To Cash', to: '/gold-to-cash', icon: CircleDollarSign },
      { type: 'link', label: 'Cash Transactions Report', to: '/cash-transactions-report', icon: ChartBar },
      { type: 'link', label: 'Pages', to: '/pages', icon: FileText },
    ],
  },
  {
    type: 'group',
    label: 'Test Features',
    icon: FlaskConical,
    children: [
      { type: 'link', label: 'Metal Picker (Test)', to: '/metal-picker-test', icon: FlaskConical },
    ],
  },
]
