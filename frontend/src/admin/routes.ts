import type { RouteRecordRaw } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AdminShell from './components/AdminShell.vue'

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
      {
        path: 'account',
        name: 'admin-account',
        component: () => import('./views/AdminAccountView.vue'),
      },
    ],
  },
  { path: '/admin/:pathMatch(.*)*', redirect: '/admin' },
]
