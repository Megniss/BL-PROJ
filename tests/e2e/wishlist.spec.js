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

test('redzu, kas meklē manu grāmatu, un varu piedāvāt apmaiņu', async ({ page }) => {
  // līga meklē mazo princi un paslēpj savu sarakstu
  await loginAndWait(page, 'liga@e2e.test')
  const wish = page.getByTestId('wishlist')
  await wish.locator('#wish-title').fill('Mazais Princis')
  await wish.locator('#wish-author').fill('Sent Ekziperi')
  await wish.getByRole('button', { name: '+ Add' }).click()
  await expect(wish.getByTestId('wish-item')).toContainText('MazaisPrincis')

  await page.goto('/settings')
  const toggle = page.locator('.privacy-toggle', { hasText: 'wishlist' })
  // pagaida kamēr iestatījumi ielādēti
  await page.waitForLoadState('networkidle')
  await toggle.click()
  await page.waitForResponse(r => r.url().includes('/api/profile/privacy'))
  await expect(page.getByTestId('privacy-wishlist')).not.toBeChecked()

  // annai redz tikai skaitu, bez vārda
  await logout(page)
  await loginAndWait(page, 'anna@e2e.test')
  const card = page.locator('.book-card', { hasText: 'MazaisPrincis' })
  await card.getByTestId('wanted-badge').click()
  const modal = page.locator('.modal-card', { hasText: 'Who is looking for this book' })
  await expect(modal).toContainText('+ 1 more with a private wishlist')
  await expect(modal.locator('.wanted-row')).toHaveCount(0)

  // līga atkal atļauj rādīt
  await logout(page)
  await loginAndWait(page, 'liga@e2e.test')
  await page.goto('/settings')
  // jāpagaida kamēr ielādē saglabāto vērtību, citādi klikšķis to pārraksta
  await expect(page.getByTestId('privacy-wishlist')).not.toBeChecked()
  await toggle.click()
  await page.waitForResponse(r => r.url().includes('/api/profile/privacy'))
  await expect(page.getByTestId('privacy-wishlist')).toBeChecked()

  // anna piedāvā savu grāmatu un pretī izvēlas Bille
  await logout(page)
  await loginAndWait(page, 'anna@e2e.test')
  await card.getByTestId('wanted-badge').click()
  await modal.locator('.wanted-row', { hasText: 'Līga Kalniņa' }).getByRole('link', { name: '⇄ Offer' }).click()

  await expect(page).toHaveURL(/\/users\/\d+\?offer=/)
  const offer = page.locator('.modal-card', { hasText: 'Offer a swap' })
  await expect(offer).toContainText('MazaisPrincis')
  // dune annai jau ir, tāpēc to izvēlē nerāda
  await expect(offer.locator('.swap-book-option', { hasText: 'Dune' })).toHaveCount(0)
  await offer.locator('.swap-book-option', { hasText: 'Bille' }).click()
  await offer.getByRole('button', { name: 'Send offer' }).click()
  await expect(page.getByText('Request Sent!')).toBeVisible()
  await page.getByRole('button', { name: 'Done' }).click()
  await expect(page.getByTestId('profile-wishlist')).toContainText('Offered')

  // līgai pienāk apmaiņas pieprasījums
  await logout(page)
  await loginAndWait(page, 'liga@e2e.test')
  await page.locator('.notif-bell').click()
  await expect(page.locator('.notif-swap')).toContainText('Anna Ozola')
})
