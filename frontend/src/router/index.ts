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
        {
          path: 'bank-details',
          name: 'bank-details',
          component: () => import('@/views/BankDetailsView.vue'),
        },
        {
          path: 'cash-transactions',
          name: 'cash-transactions',
          component: () => import('@/views/CashInOutView.vue'),
        },
        {
          path: 'sale-gold',
          name: 'sale-gold',
          component: () => import('@/views/SaleGoldView.vue'),
        },
        {
          path: 'cash-to-gold',
          name: 'cash-to-gold',
          component: () => import('@/views/CashToGoldView.vue'),
        },
        {
          path: 'gold-to-cash',
          name: 'gold-to-cash',
          component: () => import('@/views/GoldToCashView.vue'),
        },
        {
          path: 'stock-out',
          name: 'stock-out',
          component: () => import('@/views/StockOutView.vue'),
        },
        {
          path: 'stock-in',
          name: 'stock-in',
          component: () => import('@/views/StockInView.vue'),
        },
        {
          path: 'gms-in',
          name: 'gms-in',
          component: () => import('@/views/GmsInView.vue'),
        },
        {
          path: 'numeric-wastage-in',
          name: 'numeric-wastage-in',
          component: () => import('@/views/NumericWastageInView.vue'),
        },
        { path: 'pages', name: 'pages', component: () => import('@/views/PagesView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(authGuard)

export default router
