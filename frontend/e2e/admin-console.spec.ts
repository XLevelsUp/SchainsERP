import { expect, test } from '@playwright/test'
import { adminCredentials, pick, shot, signInToAdminConsole } from './support'

// The admin console: its own sign-in, the Users list, the role-driven user
// form and the Roles page. Read-only — the form is opened and cancelled.

test('the admin console has its own sign-in page', async ({ page }, testInfo) => {
  await page.goto('/admin')
  await expect(page).toHaveURL(/\/admin\/login/)
  await expect(page.getByRole('heading', { name: 'Admin console' })).toBeVisible()
  await expect(page.locator('#admin_user_name')).toBeVisible()
  await shot(page, testInfo, 'admin-login')
})

test.describe('signed in', () => {
  test.skip(!adminCredentials.user, 'Set E2E_ADMIN_USER / E2E_ADMIN_PASSWORD in .env.e2e')

  test('a wrong password is refused on the page', async ({ page }) => {
    await page.goto('/admin/login')
    await page.locator('#admin_user_name').fill(adminCredentials.user)
    await page.locator('#admin_password').fill(`${adminCredentials.password}-wrong`)
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page.getByText('Invalid username or password')).toBeVisible()
    await expect(page).toHaveURL(/\/admin\/login/)
  })

  test('the Users list loads with its filters', async ({ page }, testInfo) => {
    await signInToAdminConsole(page)
    await expect(page.getByRole('heading', { name: 'Users', exact: true })).toBeVisible()
    await expect(page.getByPlaceholder('User name…')).toBeVisible()
    await expect(page.getByText(/Showing \d+–\d+ of \d+ users|No users/)).toBeVisible()
    await shot(page, testInfo, 'admin-users-list')
  })

  test('the user form follows the role: HEAD, then ADMIN', async ({ page }, testInfo) => {
    await signInToAdminConsole(page)
    await page.getByRole('button', { name: 'New user' }).click()
    await expect(page.getByRole('heading', { name: 'New user' })).toBeVisible()
    await expect(page.getByText('Pick a role to see the rest of the form')).toBeVisible()

    await pick(page, 'user_role', 'HEAD')
    await expect(page.getByText('HEAD form', { exact: false })).toBeVisible()
    for (const label of ['Head users', 'Aadhar number', 'Per day salary (₹)', 'Report password', 'Item change in stock (head)', 'Phone book shown']) {
      await expect(page.getByText(label, { exact: true }).first()).toBeVisible()
    }
    // Not on the HEAD form.
    await expect(page.getByText('Customer touches', { exact: true })).toHaveCount(0)
    await expect(page.getByText('Head lists', { exact: true })).toHaveCount(0)
    await shot(page, testInfo, 'admin-user-form-head')

    // Head users lists HEAD users only (2026-11-23).
    await page.locator('#user_head_users').click()
    const headUserOptions = page.getByRole('listbox').getByRole('option')
    await expect(headUserOptions.first()).toBeVisible()
    const labels = await headUserOptions.allInnerTexts()
    expect(labels.length).toBeGreaterThan(0)
    for (const label of labels) expect(label).toContain('(HEAD)')
    await shot(page, testInfo, 'admin-user-form-head-users')
    await page.keyboard.press('Escape')

    // Phone: digits only, at most 10, and a short number is stopped before
    // anything is sent (the rest of the form is empty, so nothing saves).
    const phone = page.locator('#user_phone')
    await phone.fill('98a76 54')
    await expect(phone).toHaveValue('987654')
    await page.getByRole('button', { name: 'Create user' }).click()
    await expect(page.getByText('Phone number must be exactly 10 digits.')).toBeVisible()
    await shot(page, testInfo, 'admin-user-form-phone-error')
    await phone.fill('012345678999')
    await expect(phone).toHaveValue('0123456789')

    const hasAdminRole = await page.locator('#user_role').click().then(async () => {
      const option = page.getByRole('option', { name: 'ADMIN', exact: true })
      const count = await option.count()
      if (count) await option.click()
      else await page.keyboard.press('Escape')
      return count > 0
    })
    if (hasAdminRole) {
      await expect(page.getByText('ADMIN form', { exact: false })).toBeVisible()
      for (const label of ['Head lists', 'Customer touches', 'Customer comments']) {
        await expect(page.getByText(label, { exact: true }).first()).toBeVisible()
      }
      await expect(page.getByText('Report password', { exact: true })).toHaveCount(0)
      await shot(page, testInfo, 'admin-user-form-admin')
    }

    // Leave without saving.
    await page.getByRole('button', { name: 'Cancel' }).click()
    await expect(page.getByRole('heading', { name: 'Users', exact: true })).toBeVisible()
  })

  // Read-only: the form is opened and cancelled.
  test('the CUSTOMER form: no name or password, PURITY by default, its six switches', async ({ page }, testInfo) => {
    await signInToAdminConsole(page)
    await page.getByRole('button', { name: 'New user' }).click()
    await pick(page, 'user_role', 'CUSTOMER')

    await expect(page.getByText('CUSTOMER form', { exact: false })).toBeVisible()
    await expect(page.locator('#user_full_name')).toHaveCount(0)
    await expect(page.locator('#user_password')).toHaveCount(0)
    await expect(page.locator('#user_category')).toContainText('Purity')
    for (const label of [
      'Head lists', 'Customer comments', 'Customer touches',
      'Cash reminder shown', 'WhatsApp share needed in stock', 'Delivery item shown (head)',
      'Delivery checks shown in stock (employee)', 'Cash amount on thermal print in stock', 'Customer touch shown',
    ]) {
      await expect(page.getByText(label, { exact: true }).first()).toBeVisible()
    }
    for (const label of ['Cash heads', 'Report password', 'Create order shown']) {
      await expect(page.getByText(label, { exact: true })).toHaveCount(0)
    }
    await shot(page, testInfo, 'admin-user-form-customer')

    await page.getByRole('button', { name: 'Cancel' }).click()
    await expect(page.getByRole('heading', { name: 'Users', exact: true })).toBeVisible()
  })

  test('the Roles page lists the roles', async ({ page }, testInfo) => {
    await signInToAdminConsole(page)
    await page.getByRole('link', { name: 'Roles' }).click()
    await expect(page.getByRole('heading', { name: 'Roles', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'HEAD', exact: true })).toBeVisible()
    await shot(page, testInfo, 'admin-roles')
  })

  // Read-only: the prompt is opened and cancelled, so no role changes.
  test('a role status asks for the special password before switching', async ({ page }, testInfo) => {
    await signInToAdminConsole(page)
    await page.getByRole('link', { name: 'Roles' }).click()
    await page.getByRole('button', { name: 'Deactivate HEAD' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog.getByText('Deactivate role')).toBeVisible()
    await expect(dialog.getByText(/Users with this role can't sign in/)).toBeVisible()
    await expect(dialog.getByLabel(/special password/i)).toBeVisible()
    await shot(page, testInfo, 'admin-roles-status-confirm')

    await dialog.getByRole('button', { name: 'Cancel' }).click()
    await expect(dialog).toBeHidden()
    await expect(page.getByRole('button', { name: 'Deactivate HEAD' })).toBeVisible()
  })
})
