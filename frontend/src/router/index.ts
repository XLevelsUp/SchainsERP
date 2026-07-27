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
        { path: 'clients', name: 'clients', component: () => import('@/views/ClientsView.vue') },
        { path: 'pages', name: 'pages', component: () => import('@/views/PagesView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(authGuard)

export default router
