import { test, expect } from '@playwright/test'
import { login, ADMIN_PASSWORD } from './helpers.js'

test('admins pēc login izvēlas paneli un ievada admin paroli', async ({ page }) => {
  await login(page, 'admin@e2e.test')

  const choice = page.locator('.modal-card', { hasText: 'Where to?' })
  await choice.getByRole('button', { name: 'Admin panel' }).click()

  await page.fill('#admin-unlock-pwd', 'nepareiza-parole')
  await page.getByRole('button', { name: 'Open' }).click()
  await expect(page.getByText('Admin password is incorrect.')).toBeVisible()

  await page.fill('#admin-unlock-pwd', ADMIN_PASSWORD)
  await page.getByRole('button', { name: 'Open' }).click()
  await expect(page).toHaveURL(/\/admin/)
  await expect(page.getByRole('heading', { name: 'Admin Panel' })).toBeVisible()
})

test('parastam lietotājam admin panelis nav pieejams', async ({ page }) => {
  await login(page, 'anna@e2e.test')
  await expect(page).toHaveURL(/\/dashboard/)

  await page.goto('/admin')
  await expect(page).not.toHaveURL(/\/admin/)
})

test('admin kartē redz lietotājus ar atrašanās vietu', async ({ page }) => {
  await login(page, 'admin@e2e.test')
  await page.locator('.modal-card', { hasText: 'Where to?' }).getByRole('button', { name: 'Admin panel' }).click()
  await page.fill('#admin-unlock-pwd', ADMIN_PASSWORD)
  await page.getByRole('button', { name: 'Open' }).click()

  await page.getByRole('button', { name: 'Locations' }).click()
  await expect(page.getByText('3 of 7 users have set their location')).toBeVisible()

  // trīs punkti - Rīga, Jelgava, Valmiera
  const dots = page.locator('.admin-users-map path.leaflet-interactive')
  await expect(dots).toHaveCount(3)

  await dots.first().click()
  await expect(page.locator('.leaflet-popup-content')).toContainText('@e2e.test')
})

test('admin grāmatas var kārtot un filtrēt ar dropdown', async ({ page }) => {
  await login(page, 'admin@e2e.test')
  await page.locator('.modal-card', { hasText: 'Where to?' }).getByRole('button', { name: 'Admin panel' }).click()
  await page.fill('#admin-unlock-pwd', ADMIN_PASSWORD)
  await page.getByRole('button', { name: 'Open' }).click()

  await page.getByRole('button', { name: 'All Books' }).click()
  const table = page.getByRole('table', { name: 'All Books' })
  const firstTitle = table.locator('tbody tr').first().locator('td').first()
  const sort = page.getByRole('combobox', { name: 'Sort' })

  await sort.selectOption({ label: 'Title A–Z' })
  await expect(firstTitle).toHaveText('Bille')
  await sort.selectOption({ label: 'Title Z–A' })
  await expect(firstTitle).toHaveText('MazaisPrincis')

  await page.locator('select').filter({ hasText: 'All Genres' }).selectOption('Romāns')
  await expect(table.locator('tbody tr')).toHaveCount(1)

  await page.getByRole('button', { name: 'Clear filters' }).click()
  await expect(table.locator('tbody tr')).toHaveCount(7)
})

test('telefonā admin rāda tikai galveno, pārējo pēc "Skatīt vairāk"', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page, 'admin@e2e.test')
  await page.locator('.modal-card', { hasText: 'Where to?' }).getByRole('button', { name: 'Admin panel' }).click()
  await page.fill('#admin-unlock-pwd', ADMIN_PASSWORD)
  await page.getByRole('button', { name: 'Open' }).click()
  await page.getByRole('button', { name: 'All Books' }).click()

  const card = page.getByRole('table', { name: 'All Books' }).locator('tbody tr').first()
  await expect(card.getByText('Delete')).toBeHidden()
  await card.getByRole('button', { name: 'View more ▾' }).click()
  await expect(card.getByText('Delete')).toBeVisible()

  const genre = page.locator('select').filter({ hasText: 'All Genres' })
  await expect(genre).toBeHidden()
  await page.getByRole('button', { name: 'Filters' }).click()
  await expect(genre).toBeVisible()
})
