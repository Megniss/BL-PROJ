import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

test('vienāda grāmata trim lietotājiem ir viena kartīte', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.goto('/browse?search=HarryPotter')

  const cards = page.locator('.book-card')
  await expect(cards).toHaveCount(1)
  await expect(cards.first()).toContainText('3 users')

  await cards.first().click()
  const rows = page.locator('.copy-row')
  await expect(rows).toHaveCount(3)
  // tuvākais pirmais - Jelgava ir tuvāk Rīgai par Valmieru
  await expect(rows.first()).toContainText('Jānis Bērziņš')
})

test('grāmata, kas pašam jau ir, netiek rādīta', async ({ page }) => {
  await loginAndWait(page, 'peteris@e2e.test')
  await page.goto('/browse?search=HarryPotter')

  await expect(page.locator('.book-card')).toHaveCount(0)
})

// saite no e-pasta (?title=&author=) uzreiz atver grāmatas logu
test('saite no e-pasta atver tieši to grāmatu', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.goto('/browse?title=harrypotter&author=JkRowling')

  const modal = page.locator('.book-detail-card')
  await expect(modal).toContainText('HarryPotter')
  await expect(modal.locator('.copy-row')).toHaveCount(3)

  // aizverot logs pazūd un pārlādējot vairs neatveras
  await modal.locator('.detail-close-btn').click()
  await expect(modal).toHaveCount(0)
  await expect(page).not.toHaveURL(/title=/)
})

test('autora saite no e-pasta rāda visas viņa grāmatas', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await page.goto('/browse?search=Tolkien')

  await expect(page.locator('.book-card')).toHaveCount(1)
  await expect(page.locator('.book-card').first()).toContainText('Hobits')
})
