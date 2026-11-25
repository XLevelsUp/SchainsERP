import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { adminRoutes } from '@/admin/routes'
import { authGuard } from './guards'
import { COMPANY_PAGES } from '@/lib/nav'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: AuthLayout,
      meta: { realm: 'app', guestOnly: true },
      children: [{ path: '', name: 'login', component: () => import('@/views/LoginView.vue') }],
    },
    {
      path: '/',
      component: AppShell,
      meta: { realm: 'app', requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        // Items are managed in the admin console only (2026-11-24); heads read
        // them through every item dropdown.
        {
          path: 'fitem-boxes',
          name: 'fitem-boxes',
          component: () => import('@/views/FitemBoxesView.vue'),
        },
        // Users, Head Employee Mappings and Roles live in the admin console
        // (admin/routes.ts): admins create users, assign heads and create roles.
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
          path: 'metal-live',
          name: 'metal-live',
          component: () => import('@/views/LiveMetalBalanceView.vue'),
        },
        {
          path: 'consolidated-report',
          name: 'consolidated-report',
          component: () => import('@/views/ConsolidatedReportView.vue'),
        },
        {
          path: 'id-wise-report',
          name: 'id-wise-report',
          component: () => import('@/views/IdWiseReportView.vue'),
        },
        {
          path: 'one-day-action',
          name: 'one-day-action',
          component: () => import('@/views/OneDayActionView.vue'),
        },
        // The history of the Stocks page's Item Conversion / Item Change
        // (2026-11-25), each behind its feature's permission.
        {
          path: 'item-conversion-group-history',
          name: 'item-conversion-group-history',
          meta: { permission: 'is_item_conversion_need_shown' },
          component: () => import('@/views/ItemConversionGroupHistoryView.vue'),
        },
        {
          path: 'item-change-history',
          name: 'item-change-history',
          meta: { permission: 'is_item_change_need_shown' },
          component: () => import('@/views/ItemChangeHistoryView.vue'),
        },
        {
          path: 'daywise-grand',
          name: 'daywise-grand',
          component: () => import('@/views/DaywiseGrandView.vue'),
        },
        {
          path: 'phone-book',
          name: 'phone-book',
          meta: { permission: 'is_phone_book_need_shown' },
          component: () => import('@/views/PhoneBookView.vue'),
        },
        {
          path: 'system-settings',
          name: 'system-settings',
          component: () => import('@/views/SystemSettingsView.vue'),
        },
        {
          path: 'orders',
          name: 'orders',
          component: () => import('@/views/OrdersView.vue'),
        },
        {
          path: 'system-logs',
          name: 'system-logs',
          component: () => import('@/views/SystemLogsView.vue'),
        },
        {
          path: 'metal-picker-test',
          name: 'metal-picker-test',
          component: () => import('@/views/MetalPickerTestView.vue'),
        },
        { path: 'pages', name: 'pages', component: () => import('@/views/PagesView.vue') },
        // The company group's pages (2026-11-24): not built yet.
        ...COMPANY_PAGES.map((p) => ({
          path: `company/${p.slug}`,
          name: `company-${p.slug}`,
          meta: { title: p.label },
          component: () => import('@/views/ComingSoonView.vue'),
        })),
      ],
    },
    // The admin console (admin accounts create users and assign them to
    // heads) has its own sign-in and layout under /admin — admin/routes.ts.
    ...adminRoutes,
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(authGuard)

export default router
