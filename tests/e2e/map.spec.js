import { test, expect } from '@playwright/test'
import { open, loginAndWait } from './helpers.js'

// attālina, līdz visi punkti apvienojas vienā
async function zoomOutToOne(page) {
  const map = page.locator('.books-map')
  await expect(map.locator('.leaflet-marker-icon').first()).toBeVisible()
  for (let i = 0; i < 8 && await map.locator('.leaflet-marker-icon').count() > 1; i++) {
    await map.locator('.leaflet-control-zoom-out').click()
    await page.waitForTimeout(400)
  }
  await expect(map.locator('.leaflet-marker-icon')).toHaveCount(1)
}

async function hoverPopup(page) {
  await page.locator('.books-map .leaflet-marker-icon').first().hover()
  const popup = page.locator('.leaflet-popup-content')
  await expect(popup).toBeVisible()
  return popup
}

test('kartē redz grāmatas, nevis lietotājus, un dublikāti apvienoti', async ({ page }) => {
  await open(page, '/')
  await page.locator('.books-map').scrollIntoViewIfNeeded()
  await zoomOutToOne(page)

  const popup = await hoverPopup(page)
  const harry = popup.locator('li', { hasText: 'HarryPotter' })
  await expect(harry).toHaveCount(1)
  await expect(harry).toContainText('×2')
  await expect(popup).not.toContainText('Jānis')

  // pele aiziet prom - logs aizveras
  await page.mouse.move(5, 5)
  await expect(popup).toHaveCount(0)
})

test('savas grāmatas kartē neredz, un grāmata atver tās logu', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.goto('/browse')
  await page.getByRole('button', { name: 'Map', exact: true }).click()
  await zoomOutToOne(page)

  const popup = await hoverPopup(page)
  await expect(popup).not.toContainText('MazaisPrincis')
  await expect(popup).not.toContainText('Dune')

  await popup.locator('li', { hasText: 'Hobits' }).click()
  await expect(page.locator('.book-detail-card')).toContainText('Hobits')
  await expect(page.locator('.copy-row', { hasText: 'Jānis Bērziņš' })).toBeVisible()
})

test('lietotājs var savas grāmatas kartē paslēpt', async ({ page }) => {
  await loginAndWait(page, 'liga@e2e.test')
  await page.goto('/settings')
  await page.locator('.privacy-toggle', { hasText: 'public map' }).click()
  await page.waitForResponse(r => r.url().includes('/api/profile/privacy'))

  await page.evaluate(() => localStorage.clear())
  await open(page, '/')
  await page.locator('.books-map').scrollIntoViewIfNeeded()
  await zoomOutToOne(page)

  const popup = await hoverPopup(page)
  await expect(popup).not.toContainText('Bille')
})
