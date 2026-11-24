import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

// aicinājums ieslēgt e-pastus parādās vienreiz un aizved uz iestatījumiem
test('e-pastu aicinājums aizved uz iestatījumiem un vairs nerādās', async ({ page }) => {
  await loginAndWait(page, 'prompt@e2e.test')

  const prompt = page.getByTestId('email-prompt')
  await expect(prompt).toBeVisible()
  await expect(prompt).toContainText('Want book news by email?')

  await prompt.getByRole('button', { name: 'Choose in settings' }).click()
  await expect(page).toHaveURL(/\/settings#emails/)
  // aizritināja līdz e-pastu sadaļai
  await expect(page.locator('#emails')).toBeInViewport()

  // pēc pārlādes un citā lapā vairs nerādās
  await page.goto('/dashboard')
  await page.waitForTimeout(2500)
  await expect(prompt).toHaveCount(0)
})
