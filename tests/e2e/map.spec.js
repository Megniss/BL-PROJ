import { test, expect } from '@playwright/test'
import { open, loginAndWait } from './helpers.js'

// cik lietotāju kartē — atsevišķi punkti + skaitļi apvienotajos
async function countOnMap(page) {
  const map = page.locator('.users-map')
  const dots = await map.locator('path.leaflet-interactive').count()
  const clusters = await map.locator('.map-cluster').allTextContents()
  return dots + clusters.reduce((sum, n) => sum + Number(n), 0)
}

test('viesis sākumlapā redz kartē visus ar grāmatām', async ({ page }) => {
  await open(page, '/')
  await page.locator('.users-map').scrollIntoViewIfNeeded()

  // Anna, Jānis, Līga — Pēterim nav vietas
  await expect.poll(() => countOnMap(page)).toBe(3)
})

test('browse kartē sevi neredz, un punkts ved uz profilu', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.goto('/browse')
  await page.getByRole('button', { name: 'Map', exact: true }).click()

  await expect.poll(() => countOnMap(page)).toBe(2)

  await page.locator('.users-map path.leaflet-interactive').first().click()
  await page.locator('.leaflet-popup-content a').click()
  await expect(page).toHaveURL(/\/users\/\d+/)
})

test('lietotājs var sevi kartē paslēpt', async ({ page }) => {
  await loginAndWait(page, 'liga@e2e.test')
  await page.goto('/settings')
  await page.locator('.privacy-toggle', { hasText: 'public map' }).click()
  await page.waitForResponse(r => r.url().includes('/api/profile/privacy'))

  await page.evaluate(() => localStorage.clear())
  await open(page, '/')
  await page.locator('.users-map').scrollIntoViewIfNeeded()
  await expect.poll(() => countOnMap(page)).toBe(2)
})
