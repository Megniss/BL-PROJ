import { test, expect } from '@playwright/test'
import { open, login, loginAndWait, lastResetLink, newResetLink } from './helpers.js'

test('aizmirsta parole - saite nāk pa e-pastu, ekrānā tās nav', async ({ page }) => {
  const before = lastResetLink()
  await open(page, '/forgot-password')
  await page.fill('#forgot-email', 'reset@e2e.test')
  await page.getByRole('button', { name: 'Send Reset Link' }).click()

  await expect(page.locator('.alert-success')).toContainText('If an account with this email exists')
  await expect(page.locator('.alert-success a')).toHaveCount(0)

  // saite no e-pasta (žurnālā)
  await page.goto(await newResetLink(before))
  await page.fill('#reset-password', 'JaunaParole1!')
  await page.fill('#reset-confirm', 'JaunaParole1!')
  await page.getByRole('button', { name: 'Reset Password' }).click()
  await expect(page.locator('.alert-success')).toContainText('Your password has been reset')

  // vecā parole vairs nestrādā, jaunā strādā
  await login(page, 'reset@e2e.test')
  await expect(page.locator('.alert-danger')).toBeVisible()
  await login(page, 'reset@e2e.test', 'JaunaParole1!')
  await expect(page).toHaveURL(/\/dashboard/)
})

test('nezināmam e-pastam tāda pati atbilde', async ({ page }) => {
  const before = lastResetLink()
  await open(page, '/forgot-password')
  await page.fill('#forgot-email', 'nav-tada@e2e.test')
  await page.getByRole('button', { name: 'Send Reset Link' }).click()

  await expect(page.locator('.alert-success')).toContainText('If an account with this email exists')
  expect(lastResetLink()).toBe(before)
})

test('ar nepareizu tokenu paroli nomainīt nevar', async ({ page }) => {
  await open(page, '/reset-password?token=nepareizs&email=anna%40e2e.test')
  await page.fill('#reset-password', 'JaunaParole1!')
  await page.fill('#reset-confirm', 'JaunaParole1!')
  await page.getByRole('button', { name: 'Reset Password' }).click()
  await expect(page.locator('.alert-danger')).toBeVisible()

  // annas parole nav mainījusies
  await loginAndWait(page, 'anna@e2e.test')
})
