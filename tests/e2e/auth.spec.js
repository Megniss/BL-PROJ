import { test, expect } from '@playwright/test'
import { login, loginAndWait, logout, lastCode, newCode } from './helpers.js'

test('nepareiza parole rāda kļūdu', async ({ page }) => {
  await login(page, 'anna@e2e.test', 'NepareizaParole1!')
  await expect(page.locator('.alert-danger')).toBeVisible()
  await expect(page).toHaveURL(/\/login/)
})

test('lietotājs bez 2FA ielogojas uzreiz', async ({ page }) => {
  await loginAndWait(page, 'anna@e2e.test')
  await expect(page.getByRole('heading', { name: 'My Library' })).toBeVisible()
})

test('ar 2FA vajag kodu no e-pasta', async ({ page }) => {
  const before = lastCode()
  await login(page, 'twofa@e2e.test')
  await expect(page.locator('#login-code')).toBeVisible()

  await page.fill('#login-code', '000000')
  await page.getByRole('button', { name: 'Confirm' }).click()
  await expect(page.locator('.alert-danger')).toContainText('incorrect')

  await page.fill('#login-code', await newCode(before))
  await page.getByRole('button', { name: 'Confirm' }).click()
  await expect(page).toHaveURL(/\/dashboard/)
})

test('atcerēta ierīce kodu neprasa', async ({ page }) => {
  const before = lastCode()
  await login(page, 'twofa@e2e.test')
  await page.fill('#login-code', await newCode(before))
  await page.check('#login-remember')
  await page.getByRole('button', { name: 'Confirm' }).click()
  await expect(page).toHaveURL(/\/dashboard/)

  await logout(page)
  await login(page, 'twofa@e2e.test')
  await expect(page).toHaveURL(/\/dashboard/)
  await expect(page.locator('#login-code')).toHaveCount(0)
})

test('jaunam lietotājam piedāvā ieslēgt 2FA', async ({ page }) => {
  await loginAndWait(page, 'new@e2e.test')
  const prompt = page.locator('.modal-card', { hasText: 'Two-step verification' })
  await expect(prompt).toBeVisible()

  const before = lastCode()
  await prompt.getByRole('button', { name: 'Turn on' }).click()
  await page.fill('#twofa-setup-code', await newCode(before))
  await prompt.getByRole('button', { name: 'Turn on' }).click()
  await expect(prompt).toHaveCount(0)

  await page.goto('/settings')
  await expect(page.locator('.settings-card', { hasText: 'Two-step verification' })).toContainText('On')
})

test('2FA var izslēgt iestatījumos ar paroli', async ({ page }) => {
  const before = lastCode()
  await login(page, 'twofaoff@e2e.test')
  await page.fill('#login-code', await newCode(before))
  await page.getByRole('button', { name: 'Confirm' }).click()
  await expect(page).toHaveURL(/\/dashboard/)

  await page.goto('/settings')
  const card = page.locator('.settings-card', { hasText: 'Two-step verification' })
  await card.getByRole('button', { name: 'Turn off' }).click()

  await card.locator('input[type=password]').fill('NepareizaParole1!')
  await card.getByRole('button', { name: 'Turn off' }).click()
  await expect(card.locator('.invalid-feedback')).toBeVisible()

  await card.locator('input[type=password]').fill('Password1!')
  await card.getByRole('button', { name: 'Turn off' }).click()
  await expect(card.getByRole('button', { name: 'Turn on' })).toBeVisible()

  // nākamreiz kodu vairs neprasa
  await logout(page)
  await login(page, 'twofaoff@e2e.test')
  await expect(page).toHaveURL(/\/dashboard/)
})
