import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

test('pievienojot grāmatu, ieteikums aizpilda nosaukumu un autoru', async ({ page }) => {
  await loginAndWait(page, 'liga@e2e.test')
  await page.getByRole('button', { name: '+ Add Book' }).click()

  await page.fill('#book-title', 'dun')
  await page.locator('.autocomplete-item', { hasText: 'Dune' }).click()
  await expect(page.locator('#book-title')).toHaveValue('Dune')
  await expect(page.locator('#book-author')).toHaveValue('FrankHerbert')

  await page.selectOption('#book-language', 'English')
  await page.locator('.modal-card button[type=submit]').click()

  await expect(page.locator('.book-card', { hasText: 'Dune' })).toBeVisible()
})

test('grāmatu, kas jau ir, ieteikumos nevar izvēlēties', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.getByRole('button', { name: '+ Add Book' }).click()

  await page.fill('#book-title', 'dun')
  const item = page.locator('.autocomplete-item', { hasText: 'Dune' })
  await expect(item).toHaveClass(/disabled/)
  await expect(item).toContainText('You already have it')
})
