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
          path: 'customer-touch-mappings',
          name: 'customer-touch-mappings',
          component: () => import('@/views/CustomerTouchMappingsView.vue'),
        },
        {
          path: 'bank-details',
          name: 'bank-details',
          component: () => import('@/views/BankDetailsView.vue'),
        },
        {
          path: 'cash-management',
          name: 'cash-management',
          component: () => import('@/views/CashManagementView.vue'),
        },
        {
          path: 'sale-gold',
          name: 'sale-gold',
          component: () => import('@/views/SaleGoldView.vue'),
        },
        {
          path: 'purchase-gold',
          name: 'purchase-gold',
          component: () => import('@/views/PurchaseGoldView.vue'),
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
          path: 'stock',
          name: 'stock',
          component: () => import('@/views/StockManagementView.vue'),
        },
        {
          path: 'cash-transactions-report',
          name: 'cash-transactions-report',
          component: () => import('@/views/CashTransactionsReportView.vue'),
        },
        {
          path: 'stock-auto-entry',
          name: 'stock-auto-entry',
          component: () => import('@/views/StockAutoEntryView.vue'),
        },
        {
          path: 'items-obcb-report',
          name: 'items-obcb-report',
          component: () => import('@/views/ItemsObcbReportView.vue'),
        },
        {
          path: 'consolidated-report',
          name: 'consolidated-report',
          component: () => import('@/views/ConsolidatedReportView.vue'),
        },
        {
          path: 'metal-picker-test',
          name: 'metal-picker-test',
          component: () => import('@/views/MetalPickerTestView.vue'),
        },
        { path: 'pages', name: 'pages', component: () => import('@/views/PagesView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(authGuard)

export default router
