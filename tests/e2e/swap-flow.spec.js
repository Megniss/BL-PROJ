import { test, expect } from '@playwright/test'
import { loginAndWait, logout } from './helpers.js'

// Edgars piedāvā Kaiju pret Zanes Faustu
async function sendRequest(page) {
  await loginAndWait(page, 'edgars@e2e.test')
  await page.goto('/browse?search=Fausts')
  await page.locator('.book-card:not(.skeleton-card)').first().click()
  await page.locator('.copy-row', { hasText: 'Zane Ozoliņa' }).locator('.btn-swap').click()
  await page.locator('.swap-book-option', { hasText: 'Kaija' }).click()
  await page.getByRole('button', { name: 'Send Request' }).click()
  await expect(page.getByText('Request Sent!')).toBeVisible()
}

// zanes saņemtais pieprasījums dashboardā
function incoming(page) {
  return page.locator('.card', { hasText: 'Edgars Kalns' }).filter({ has: page.getByRole('button', { name: 'Accept' }) })
}

test('iesniedzējs var atcelt pieprasījumu', async ({ page }) => {
  await sendRequest(page)
  await page.goto('/dashboard')

  const sent = page.locator('.card').filter({ hasText: 'Fausts' }).filter({ has: page.locator('.tag') })
  await expect(sent.locator('.tag')).toContainText('pending')
  page.once('dialog', d => d.accept())
  await sent.getByRole('button', { name: '✕ Cancel' }).click()

  // pieprasījums pazūd, piedāvātā grāmata atkal pieejama
  await expect(sent).toHaveCount(0)
  await expect(page.locator('.book-card', { hasText: 'Kaija' })).toContainText('Available')

  // īpašniekam pieprasījums vairs nav redzams
  await logout(page)
  await loginAndWait(page, 'zane@e2e.test')
  await expect(page.locator('.book-card', { hasText: 'Fausts' })).toBeVisible()
  await expect(incoming(page)).toHaveCount(0)
})

test('īpašnieks var noraidīt pieprasījumu', async ({ page }) => {
  await sendRequest(page)

  await logout(page)
  await loginAndWait(page, 'zane@e2e.test')
  await incoming(page).getByRole('button', { name: 'Decline' }).click()
  await expect(incoming(page)).toHaveCount(0)

  // zanei grāmata paliek
  await expect(page.locator('.book-card', { hasText: 'Fausts' })).toBeVisible()
})

test('pieņemta apmaiņa samaina grāmatas, un to var novērtēt', async ({ page }) => {
  await sendRequest(page)

  await logout(page)
  await loginAndWait(page, 'zane@e2e.test')
  await incoming(page).getByRole('button', { name: 'Accept' }).click()
  await expect(incoming(page)).toHaveCount(0)
  await expect(page.locator('.book-card', { hasText: 'Kaija' })).toBeVisible()
  await expect(page.locator('.book-card', { hasText: 'Fausts' })).toHaveCount(0)

  await logout(page)
  await loginAndWait(page, 'edgars@e2e.test')
  await expect(page.locator('.book-card', { hasText: 'Fausts' })).toBeVisible()

  await page.goto('/profile')
  const swap = page.locator('.card').filter({ hasText: 'Kaija' }).filter({ has: page.getByRole('button', { name: 'Rate' }) })
  await swap.getByRole('button', { name: 'Rate' }).click()

  const modal = page.locator('.modal-card', { hasText: 'Fausts' })
  await modal.locator('.star').nth(3).click()
  await modal.locator('#rating-review').fill('Laba grāmata, ātra apmaiņa.')
  await modal.getByRole('button', { name: 'Submit' }).click()
  await expect(modal).toHaveCount(0)

  // rate pogas vairs nav, rāda 4 zvaigznes
  await expect(page.getByRole('button', { name: 'Rate' })).toHaveCount(0)
  await expect(page.getByText('★★★★☆')).toBeVisible()
})
