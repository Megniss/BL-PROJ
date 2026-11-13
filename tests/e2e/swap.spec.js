import { test, expect } from '@playwright/test'
import { loginAndWait, logout } from './helpers.js'

// skeleton kartītēm arī ir .book-card klase, tāpēc :not(.skeleton-card)
test('apmaiņas pieprasījums nonāk pie īpašnieka', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.goto('/browse?search=Hobits')
  await page.locator('.book-card:not(.skeleton-card)').first().click()

  await page.locator('.copy-row', { hasText: 'Jānis Bērziņš' }).locator('.btn-swap').click()
  await page.locator('.swap-book-option', { hasText: 'Dune' }).click()
  await page.getByRole('button', { name: 'Send Request' }).click()
  await expect(page.getByText('Request Sent!')).toBeVisible()

  await logout(page)
  await loginAndWait(page, 'janis@e2e.test')
  await page.locator('.notif-bell').click()
  await expect(page.locator('.notif-swap')).toContainText('Anna Ozola')
})
