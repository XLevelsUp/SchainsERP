import { expect, test } from '@playwright/test'
import { appCredentials, expectItemHistoryPage, expectOneDayActionPage, shot } from './support'

// The operational app: its sign-in, and that pages need a session and the
// right permission. Read-only.

test('the app sign-in page links to the admin console', async ({ page }, testInfo) => {
  await page.goto('/login')
  await expect(page.locator('#app_user_name')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Admin console' })).toHaveAttribute('href', '/admin/login')
  await shot(page, testInfo, 'app-login')
})

test('a page needs a session first', async ({ page }) => {
  await page.goto('/phone-book')
  await expect(page).toHaveURL(/\/login\?redirect=/)
})

test.describe('signed in', () => {
  test.skip(!appCredentials.user, 'Set E2E_APP_USER / E2E_APP_PASSWORD in .env.e2e')

  test('the sidebar shows only what the user may use', async ({ page }, testInfo) => {
    await page.goto('/login')
    await page.locator('#app_user_name').fill(appCredentials.user)
    await page.locator('#app_password').fill(appCredentials.password)
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).not.toHaveURL(/\/login/)

    // The agreed order (2026-11-24); Phone Book only with its permission,
    // everything else under More, the company group named from the setting.
    const sidebar = page.getByRole('navigation').first()
    await expect(sidebar.getByRole('button', { name: 'NIHAA JEWELS' })).toBeVisible()
    const top = (await sidebar.locator(':scope > a, :scope > div > button').allInnerTexts()).map((t) => t.trim())
    expect(top.filter((t) => t !== 'Phone Book')).toEqual([
      'Stocks',
      'Cash Management',
      'Consolidated Report',
      'Items OB CB',
      'One Day Action Report',
      'NIHAA JEWELS',
      'ID Wise',
      'More',
    ])

    await sidebar.getByRole('button', { name: 'NIHAA JEWELS' }).click()
    await sidebar.getByRole('link', { name: 'Customer FITEM' }).click()
    await expect(page).toHaveURL(/\/company\/customer-fitem$/)
    await expect(page.getByText("This page isn't built yet.")).toBeVisible()

    // More opens itself while one of its pages (e.g. the Dashboard) is open.
    const more = sidebar.getByRole('button', { name: 'More', exact: true })
    if ((await more.getAttribute('aria-expanded')) !== 'true') await more.click()
    await expect(sidebar.getByRole('link', { name: 'Dashboard' })).toBeVisible()
    await shot(page, testInfo, 'app-signed-in')
  })

  // Read-only: the report is loaded and its sections folded, nothing saved.
  test('One Day Action shows the stock and cash sections for today', async ({ page }, testInfo) => {
    await page.goto('/login')
    await page.locator('#app_user_name').fill(appCredentials.user)
    await page.locator('#app_password').fill(appCredentials.password)
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).not.toHaveURL(/\/login/)

    await page.getByRole('navigation').first().getByRole('link', { name: 'One Day Action Report' }).click()
    await expect(page).toHaveURL(/\/one-day-action$/)
    await expect(page.getByRole('heading', { name: 'One Day Action Report' })).toBeVisible()
    // The app is scoped to the signed-in head: no Head filter.
    await expect(page.locator('#report_head')).toHaveCount(0)
    await expectOneDayActionPage(page)
    await shot(page, testInfo, 'app-one-day-action')
  })

  // Read-only. Each page follows its Stocks page feature's permission: with
  // it, the menu link and the page (no Head filter); without, no link, and
  // the address goes to the dashboard.
  test('the item history pages follow their permissions', async ({ page }, testInfo) => {
    await page.goto('/login')
    await page.locator('#app_user_name').fill(appCredentials.user)
    await page.locator('#app_password').fill(appCredentials.password)
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).not.toHaveURL(/\/login/)
    const sidebar = page.getByRole('navigation').first()
    await expect(sidebar.getByRole('link', { name: 'One Day Action Report' })).toBeVisible()

    const pages = [
      { label: 'Item Conversion Group History', path: '/item-conversion-group-history', prefix: 'icg' },
      { label: 'Item Change History', path: '/item-change-history', prefix: 'ich' },
    ] as const
    for (const { label, path, prefix } of pages) {
      const link = sidebar.getByRole('link', { name: label, exact: true })
      if (await link.count()) {
        await link.click()
        await expect(page).toHaveURL(new RegExp(`${path}$`))
        await expect(page.getByRole('heading', { name: label })).toBeVisible()
        await expect(page.locator('#report_head')).toHaveCount(0)
        await expectItemHistoryPage(page, prefix)
        await shot(page, testInfo, `app-${prefix}-history`)
      } else {
        await page.goto(path)
        await expect(page).not.toHaveURL(new RegExp(`${path}$`))
        await expect(page.getByRole('heading', { name: label })).toHaveCount(0)
      }
    }
    await shot(page, testInfo, 'app-item-history-permissions')
  })
})
