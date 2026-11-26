import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

// login dzīvo tikai kamēr logs atvērts
test('jauna cilne paņem login, bet pēc visu logu aizvēršanas jāielogojas no jauna', async ({ page, context }) => {
  await loginAndWait(page, 'anna@e2e.test')

  // otra cilne, kamēr pirmā vēl atvērta - joprojām ielogots
  const second = await context.newPage()
  await second.goto('/dashboard')
  await expect(second).toHaveURL(/\/dashboard/)

  // aizver visas cilnes un atver no jauna - izlogots
  await page.close()
  await second.close()
  const fresh = await context.newPage()
  await fresh.goto('/dashboard')
  await expect(fresh).toHaveURL(/\/login/)
})

// pārlādējot lapu login paliek
test('pārlādējot lapu paliek ielogots', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.reload()
  await expect(page).toHaveURL(/\/dashboard/)
})
