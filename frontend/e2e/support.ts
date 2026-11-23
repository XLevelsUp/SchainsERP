import { join } from 'node:path'
import { expect, type Page, type TestInfo } from '@playwright/test'

// Shared helpers for the browser tests. See playwright.config.ts: these run
// against the shared Aiven database, so tests read and never save.

export const adminCredentials = {
  user: process.env.E2E_ADMIN_USER ?? '',
  password: process.env.E2E_ADMIN_PASSWORD ?? '',
}

export const appCredentials = {
  user: process.env.E2E_APP_USER ?? '',
  password: process.env.E2E_APP_PASSWORD ?? '',
}

/**
 * Full-page screenshot under test-results/screens, for reviewing the page.
 * The shells are h-screen with a scrolling <main>, so a plain full-page
 * capture stops at the window; the layout is let out to its full height
 * first.
 */
export async function shot(page: Page, testInfo: TestInfo, name: string) {
  await page.evaluate(() => {
    document.querySelectorAll<HTMLElement>('.h-screen').forEach((el) => {
      el.style.height = 'auto'
      el.style.overflow = 'visible'
    })
    document.querySelectorAll<HTMLElement>('main').forEach((el) => {
      el.style.overflow = 'visible'
    })
  })
  await page.screenshot({ path: join(testInfo.config.rootDir, '..', 'test-results', 'screens', `${name}.png`), fullPage: true })
}

export async function signInToAdminConsole(page: Page) {
  await page.goto('/admin/login')
  await page.locator('#admin_user_name').fill(adminCredentials.user)
  await page.locator('#admin_password').fill(adminCredentials.password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/admin\/users$/)
}

/** Picks an option in a BaseSelect (a button that opens a teleported listbox). */
export async function pick(page: Page, selectId: string, option: string) {
  await page.locator(`#${selectId}`).click()
  await page.getByRole('option', { name: option, exact: true }).click()
}
