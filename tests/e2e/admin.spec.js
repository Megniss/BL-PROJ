import { test, expect } from '@playwright/test'
import { login, ADMIN_PASSWORD } from './helpers.js'

test('admins pēc login izvēlas paneli un ievada admin paroli', async ({ page }) => {
  await login(page, 'admin@e2e.test')

  const choice = page.locator('.modal-card', { hasText: 'Where to?' })
  await choice.getByRole('button', { name: 'Admin panel' }).click()

  await page.fill('#admin-unlock-pwd', 'nepareiza-parole')
  await page.getByRole('button', { name: 'Open' }).click()
  await expect(page.getByText('Admin password is incorrect.')).toBeVisible()

  await page.fill('#admin-unlock-pwd', ADMIN_PASSWORD)
  await page.getByRole('button', { name: 'Open' }).click()
  await expect(page).toHaveURL(/\/admin/)
  await expect(page.getByRole('heading', { name: 'Admin Panel' })).toBeVisible()
})

test('parastam lietotājam admin panelis nav pieejams', async ({ page }) => {
  await login(page, 'anna@e2e.test')
  await expect(page).toHaveURL(/\/dashboard/)

  await page.goto('/admin')
  await expect(page).not.toHaveURL(/\/admin/)
})
