import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { authGuard } from './guards'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: AuthLayout,
      children: [{ path: '', name: 'login', component: () => import('@/views/LoginView.vue') }],
    },
    {
      path: '/',
      component: AppShell,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'items', name: 'items', component: () => import('@/views/ItemsView.vue') },
        {
          path: 'fitem-boxes',
          name: 'fitem-boxes',
          component: () => import('@/views/FitemBoxesView.vue'),
        },
        { path: 'roles', name: 'roles', component: () => import('@/views/RolesView.vue') },
        { path: 'users', name: 'users', component: () => import('@/views/UsersView.vue') },
        { path: 'clients', name: 'clients', component: () => import('@/views/ClientsView.vue') },
        {
          path: 'customer-touch',
          name: 'customer-touch',
          component: () => import('@/views/CustomerTouchView.vue'),
        },
        { path: 'pages', name: 'pages', component: () => import('@/views/PagesView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(authGuard)

export default router
