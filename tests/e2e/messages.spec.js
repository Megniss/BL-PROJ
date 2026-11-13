import { test, expect } from '@playwright/test'
import { loginAndWait, logout } from './helpers.js'

test('ziņu var nosūtīt, labot kamēr nav izlasīta, un otrs to saņem', async ({ page }) => {
  await loginAndWait(page, 'edgars@e2e.test')
  await page.goto('/browse?search=Fausts')
  await page.locator('.book-card:not(.skeleton-card)').first().click()
  await page.locator('.copy-row', { hasText: 'Zane Ozoliņa' }).locator('.btn-msg-owner').click()
  await expect(page).toHaveURL(/\/messages/)

  await page.fill('.compose-input', 'Sveiki, vai Fausts vēl ir?')
  // ar enter, jo cookie josla aizsedz pogu
  await page.locator('.compose-input').press('Enter')
  const mine = page.locator('.msg-mine .msg-bubble').last()
  await expect(mine).toContainText('Sveiki, vai Fausts vēl ir?')

  // labošana caur labās pogas izvēlni
  await mine.click({ button: 'right' })
  await page.locator('.msg-ctx-menu').getByRole('button', { name: 'Edit' }).click()
  await page.fill('.msg-edit-input', 'Sveiki, vai Fausts vēl pieejams?')
  await page.locator('.msg-edit-save').click()
  await expect(mine).toContainText('vēl pieejams?')
  await expect(mine.locator('.msg-edited')).toBeVisible()

  await logout(page)
  await loginAndWait(page, 'zane@e2e.test')
  await page.goto('/messages')
  const convo = page.locator('.convo-item', { hasText: 'Edgars Kalns' })
  await expect(convo.locator('.convo-badge')).toHaveText('1')

  await convo.click()
  await expect(page.locator('.msg-row').last()).toContainText('Sveiki, vai Fausts vēl pieejams?')
  await page.fill('.compose-input', 'Jā, ir!')
  // ar enter, jo cookie josla aizsedz pogu
  await page.locator('.compose-input').press('Enter')
  await expect(page.locator('.msg-mine').last()).toContainText('Jā, ir!')

  // izlasītu ziņu vairs nevar labot
  await logout(page)
  await loginAndWait(page, 'edgars@e2e.test')
  await page.goto('/messages')
  await page.locator('.convo-item', { hasText: 'Zane Ozoliņa' }).click()
  await expect(page.locator('.msg-row').last()).toContainText('Jā, ir!')
  await page.locator('.msg-mine .msg-bubble').last().click({ button: 'right' })
  await expect(page.locator('.msg-ctx-menu')).toHaveCount(0)
})
