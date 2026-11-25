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

// The admin console's saved session (its localStorage), kept after the first
// sign-in. The backend allows 5 sign-ins a minute per account (the
// admin-login rate limit), and the suite has more console tests than that,
// so later tests reuse the session. One worker (playwright.config.ts), so
// this module state is shared across a run.
let adminSession: { name: string; value: string }[] | null = null

export async function signInToAdminConsole(page: Page) {
  if (adminSession) {
    await page.addInitScript((items) => {
      for (const { name, value } of items) window.localStorage.setItem(name, value)
    }, adminSession)
    await page.goto('/admin/users')
    await expect(page).toHaveURL(/\/admin\/users$/)
    return
  }

  await page.goto('/admin/login')
  await page.locator('#admin_user_name').fill(adminCredentials.user)
  await page.locator('#admin_password').fill(adminCredentials.password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/admin\/users$/)

  const origin = new URL(page.url()).origin
  adminSession = (await page.context().storageState()).origins.find((o) => o.origin === origin)?.localStorage ?? null
}

/** Picks an option in a BaseSelect (a button that opens a teleported listbox). */
export async function pick(page: Page, selectId: string, option: string) {
  await page.locator(`#${selectId}`).click()
  await page.getByRole('option', { name: option, exact: true }).click()
}

/**
 * One Day Action (2026-11-25), in both logins: the legacy filters, today by
 * default, and the two sections with their totals.
 */
export async function expectOneDayActionPage(page: Page) {
  for (const id of ['oda_user', 'oda_item', 'oda_stock_type', 'oda_given_by', 'oda_given_to', 'oda_role']) {
    await expect(page.locator(`#${id}`)).toBeVisible()
  }
  const now = new Date()
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  await expect(page.locator('#oda_from_date')).toHaveValue(today)
  await expect(page.locator('#oda_to_date')).toHaveValue('')

  // Both sections start closed, their Totals showing.
  const stockToggle = page.getByRole('button', { name: /^Stock Report/ })
  const cashToggle = page.getByRole('button', { name: /^Cash Management Report/ })
  for (const toggle of [stockToggle, cashToggle]) {
    await expect(toggle).toContainText(/Total: \d+/)
    await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  }
  await expect(page.getByRole('columnheader', { name: 'Stock ID' })).toBeHidden()
  await expect(page.getByRole('columnheader', { name: 'Txn ID' })).toBeHidden()

  // Each opens on its own and closes again.
  await stockToggle.click()
  await expect(stockToggle).toHaveAttribute('aria-expanded', 'true')
  await expect(page.getByRole('columnheader', { name: 'Stock ID' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Txn ID' })).toBeHidden()
  await cashToggle.click()
  await expect(page.getByRole('columnheader', { name: 'Txn ID' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Category' })).toBeVisible()
  await stockToggle.click()
  await expect(page.getByRole('columnheader', { name: 'Stock ID' })).toBeHidden()
  await stockToggle.click()
}

/** Today as a date input holds it (local time). */
export function todayInput(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

/**
 * Item Change History / Item Conversion Group History (2026-11-25), in both
 * logins: Item and From / To Date filters, the current month by default,
 * then a search over the whole history (read-only). `prefix` is the page's
 * field prefix.
 */
export async function expectItemHistoryPage(page: Page, prefix: 'ich' | 'icg') {
  await expect(page.locator(`#${prefix}_item`)).toBeVisible()
  await expect(page.locator(`#${prefix}_from_date`)).toHaveValue(`${todayInput().slice(0, 8)}01`)
  await expect(page.locator(`#${prefix}_to_date`)).toHaveValue(todayInput())

  await page.locator(`#${prefix}_from_date`).fill('2026-01-01')
  await page.locator(`#${prefix}_to_date`).fill(todayInput())
  const search = page.getByRole('button', { name: 'Search', exact: true })
  await search.click()
  await expect(search).toBeEnabled()
}

/** The number in a "Label: 12" badge. */
export async function badgeCount(page: Page, label: string): Promise<number> {
  const badge = page.getByText(new RegExp(`^\\s*${label}: \\d+\\s*$`))
  await expect(badge).toBeVisible()
  return Number((await badge.innerText()).replace(/\D/g, ''))
}
