import { test, expect } from '@playwright/test'
import { loginAndWait } from './helpers.js'

// admins atbildēja uz pieteikumu - lietotājam zvaniņā paziņojums, uzspiežot atveras čats
test('admina atbilde atbalstā parādās zvaniņā un atver pieteikumu', async ({ page }) => {
  await loginAndWait(page, 'support@e2e.test')

  // zvaniņš ir arī citās lapās, ne tikai My Library
  await page.goto('/browse')
  await expect(page.getByTestId('notif-badge')).toHaveText('1')

  await page.getByTestId('notif-bell').click()
  const item = page.getByTestId('notif-item').first()
  await expect(item).toContainText('Support replied to your ticket "Nevaru atrast grāmatu"')

  await item.click()
  await expect(page).toHaveURL(/\/support\?ticket=\d+/)
  // pirmā ziņa redzama tikai čatā (sānjoslā ir pēdējā)
  await expect(page.getByText('Kur palika mana grāmata?')).toBeVisible()

  // izlasīts - skaitlis pazūd
  await expect(page.getByTestId('notif-badge')).toHaveCount(0)
})

test('zvaniņā var atzīmēt visus kā lasītus un notīrīt', async ({ page }) => {
  await loginAndWait(page, 'notif@e2e.test')
  await expect(page.getByTestId('notif-badge')).toHaveText('2')
  await page.getByTestId('notif-bell').click()

  const readAll = page.getByTestId('notif-read-all')
  await expect(readAll).toBeEnabled()
  await readAll.click()
  await expect(page.getByTestId('notif-badge')).toHaveCount(0)
  await expect(readAll).toBeDisabled()

  await page.getByTestId('notif-clear').click()
  await expect(page.getByTestId('notif-item')).toHaveCount(0)
  await expect(page.locator('.notif-empty')).toBeVisible()
})
