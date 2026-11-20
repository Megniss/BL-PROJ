import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

test('iknedēļas apkopojumu var ieslēgt un tas saglabājas', async ({ page }) => {
  await loginAndWait(page, 'digest@e2e.test')
  await page.goto('/settings')

  const toggle = page.getByTestId('digest-toggle')
  // pēc noklusējuma izslēgts (gdpr)
  await expect(toggle).not.toBeChecked()

  const saved = page.waitForResponse(r => r.url().includes('/api/profile/privacy') && r.ok())
  await page.locator('.privacy-toggle', { has: toggle }).click()
  await saved

  await page.reload()
  await expect(page.getByTestId('digest-toggle')).toBeChecked()
})
