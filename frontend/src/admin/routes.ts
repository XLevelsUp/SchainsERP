import type { RouteRecordRaw } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AdminShell from './components/AdminShell.vue'
import { COMPANY_PAGES } from '@/lib/nav'

// Same addresses as the app's report screens, under /admin.
const REPORT_PATHS = {
  'items-obcb': 'items-obcb-report',
  consolidated: 'consolidated-report',
  'one-day-action': 'one-day-action',
  'id-wise': 'id-wise-report',
} as const

// The admin console's screens, all under /admin. Admins create users,
// assign them to heads and create roles (agreed 2026-11-20); everything
// else stays with heads in the app. realm: 'admin' makes the guard check the admin session
// (see router/realms.ts).
export const adminRoutes: RouteRecordRaw[] = [
  {
    path: '/admin/login',
    component: AuthLayout,
    meta: { realm: 'admin', guestOnly: true },
    children: [
      { path: '', name: 'admin-login', component: () => import('./views/AdminLoginView.vue') },
    ],
  },
  {
    path: '/admin',
    component: AdminShell,
    meta: { realm: 'admin', requiresAuth: true },
    children: [
      { path: '', redirect: { name: 'admin-users' } },
      { path: 'users', name: 'admin-users', component: () => import('./views/UsersView.vue') },
      {
        path: 'head-employee-mappings',
        name: 'admin-head-employee-mappings',
        component: () => import('./views/HeadEmployeeMappingsView.vue'),
      },
      { path: 'roles', name: 'admin-roles', component: () => import('./views/RolesView.vue') },
      { path: 'phone-book', name: 'admin-phone-book', component: () => import('./views/PhoneBookView.vue') },
      // The heads' stock reports, for every head (2026-11-24).
      ...(['items-obcb', 'consolidated', 'one-day-action', 'id-wise'] as const).map((report) => ({
        path: REPORT_PATHS[report],
        name: `admin-${report}`,
        component: () => import('./views/reports/AdminReportPage.vue'),
        props: { report },
      })),
      {
        path: 'customer-touch',
        name: 'admin-customer-touch',
        component: () => import('./views/CustomerTouchAdminView.vue'),
      },
      // Not built yet: the company group's pages and Salary.
      ...COMPANY_PAGES.map((p) => ({
        path: `company/${p.slug}`,
        name: `admin-company-${p.slug}`,
        meta: { title: p.label },
        component: () => import('@/views/ComingSoonView.vue'),
      })),
      {
        path: 'salary',
        name: 'admin-salary',
        meta: { title: 'Salary' },
        component: () => import('@/views/ComingSoonView.vue'),
      },
      {
        path: 'account',
        name: 'admin-account',
        component: () => import('./views/AdminAccountView.vue'),
      },
    ],
  },
  { path: '/admin/:pathMatch(.*)*', redirect: '/admin' },
]
