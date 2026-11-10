import { readFileSync } from 'node:fs'
import { expect } from '@playwright/test'

export const PASSWORD = 'Password1!'
export const ADMIN_PASSWORD = 'admin12345678'

// atver lapu angliski un aizver cookie paziņojumu
export async function open(page, path) {
  await page.addInitScript(() => localStorage.setItem('bookloop_locale', 'en'))
  await page.goto(path)
  await page.locator('.cookie-btn').click()
}

export async function login(page, email, password = PASSWORD) {
  await open(page, '/login')
  await page.fill('#login-email', email)
  await page.fill('#login-password', password)
  await page.click('button[type=submit]')
}

export async function loginAndWait(page, email) {
  await login(page, email)
  await expect(page).toHaveURL(/\/dashboard/)
}

// izdzēš login datus, lai var ielogoties kā cits lietotājs
export async function logout(page) {
  await page.evaluate(() => {
    localStorage.removeItem('bookloop_token')
    localStorage.removeItem('bookloop_user')
  })
}

// pēdējais 2FA kods no laravel.log (testos e-pasti netiek sūtīti)
export function lastCode() {
  try {
    const log = readFileSync('storage/logs/laravel.log', 'utf8')
    return [...log.matchAll(/Your code: (\d{6})/g)].at(-1)?.[1]
  } catch {
    return undefined
  }
}

// gaida, līdz žurnālā parādās jauns kods
export async function newCode(before) {
  let code
  await expect.poll(() => (code = lastCode())).not.toBe(before)
  return code
}
