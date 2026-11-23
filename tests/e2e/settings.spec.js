import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

test('katru e-pastu var ieslēgt atsevišķi un tas saglabājas', async ({ page }) => {
  await loginAndWait(page, 'digest@e2e.test')
  await page.goto('/settings')

  const popular = page.getByTestId('email-toggle-popular')
  // pēc noklusējuma viss izslēgts (gdpr)
  for (const type of ['nearby', 'popular', 'authors', 'rated']) {
    await expect(page.getByTestId('email-toggle-' + type)).not.toBeChecked()
  }

  const saved = page.waitForResponse(r => r.url().includes('/api/profile/emails') && r.ok())
  await page.locator('.privacy-toggle', { has: popular }).click()
  await saved

  await page.reload()
  await expect(page.getByTestId('email-toggle-popular')).toBeChecked()
  // pārējie palika izslēgti
  await expect(page.getByTestId('email-toggle-rated')).not.toBeChecked()
})
