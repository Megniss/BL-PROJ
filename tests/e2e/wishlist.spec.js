import { test, expect } from '@playwright/test'
import { loginAndWait, logout } from './helpers.js'

// pēdējais fails (alfabētiski), jo pievieno jaunu grāmatu un mainītu citu testu skaitus
test('vēlmju saraksts - kad kāds pievieno grāmatu, nāk paziņojums', async ({ page }) => {
  // zane pievieno grāmatu, kuras nevienam nav
  await loginAndWait(page, 'zane@e2e.test')
  const wish = page.getByTestId('wishlist')
  await wish.locator('#wish-title').fill('Sniegavīrs')
  await wish.locator('#wish-author').fill('Jo Nesbo')
  await wish.getByRole('button', { name: '+ Add' }).click()
  const item = wish.getByTestId('wish-item')
  await expect(item).toContainText('Sniegavīrs')
  await expect(item).toContainText('Waiting')

  // edgars pievieno tādu pašu
  await logout(page)
  await loginAndWait(page, 'edgars@e2e.test')
  await page.getByRole('button', { name: '+ Add Book' }).click()
  await page.fill('#book-title', 'Sniegavīrs')
  await page.fill('#book-author', 'Jo Nesbo')
  await page.selectOption('#book-genre', 'Detektīvs')
  await page.selectOption('#book-language', 'Latviešu')
  await page.locator('.modal-card button[type=submit]').click()
  await expect(page.locator('.book-card', { hasText: 'Sniegavīrs' })).toBeVisible()

  // zane redz paziņojumu un pieejamo eksemplāru
  await logout(page)
  await loginAndWait(page, 'zane@e2e.test')
  await expect(page.getByTestId('wish-item')).toContainText('1 available')

  await page.getByTestId('notif-bell').click()
  const notif = page.getByTestId('notif-item').first()
  await expect(notif).toContainText('"Sniegavīrs" from your wishlist is now available!')
  await expect(notif).toContainText('Added by: Edgars Kalns')

  // aizved uz grāmatu, logā redz edgaru un ka grāmata ir sarakstā
  await notif.click()
  await expect(page).toHaveURL(/\/browse\?title=Sniegav/)
  const modal = page.locator('.book-detail-card')
  await expect(modal).toContainText('Edgars Kalns')
  await expect(modal.getByTestId('wish-toggle')).toHaveText(/In your wishlist/)

  // noņem no saraksta tieši logā
  await modal.getByTestId('wish-toggle').click()
  await expect(modal.getByTestId('wish-toggle')).toHaveText(/Add to wishlist/)
})
