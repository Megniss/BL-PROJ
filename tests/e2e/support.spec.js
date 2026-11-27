import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

test('atbalsta iemeslu var atrast rakstot un izvēlēties ar tastatūru', async ({ page }) => {
  await loginAndWait(page, 'support@e2e.test')
  await page.goto('/support')
  await page.getByRole('button', { name: '+ New Complaint' }).click()

  const reason = page.getByTestId('support-reason')
  // atver sarakstu - redz visus 9
  await reason.click()
  await expect(page.locator('.reason-item')).toHaveCount(9)

  // meklē arī pēc paskaidrojuma ("never came" ir tikai hint tekstā)
  await reason.fill('never came')
  await expect(page.locator('.reason-item')).toHaveCount(1)
  await reason.press('Enter')
  await expect(reason).toHaveValue('Did not receive a book')

  // temata lauka nav, teksta laukā jautājums pēc iemesla
  await expect(page.getByPlaceholder('Subject')).toHaveCount(0)
  const body = page.getByPlaceholder('Which book, from whom, and when was the swap accepted?')
  await body.fill('Anna nepiegādāja grāmatu')
  await page.getByRole('button', { name: 'Send', exact: true }).click()

  await expect(page.locator('.thread-user-link')).toHaveText('Did not receive a book')
  await expect(page.locator('.convo-item').first()).toContainText('📦')
})
