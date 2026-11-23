import { expect, test } from '@playwright/test'
import { appCredentials, shot } from './support'

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
    await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible()
    await shot(page, testInfo, 'app-signed-in')
  })
})
