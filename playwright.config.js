import { defineConfig } from '@playwright/test'
import path from 'node:path'

const PORT = 8001

export default defineConfig({
  testDir: 'tests/e2e',
  // visi testi izmanto vienu datubāzi, tāpēc pa vienam
  workers: 1,
  fullyParallel: false,
  reporter: [['list'], ['html', { open: 'never' }]],

  use: {
    baseURL: `http://localhost:${PORT}`,
    serviceWorkers: 'block',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],

  // atsevišķa datubāze ar testa datiem, nobūvēts frontend, e-pasti žurnālā
  webServer: {
    command: [
      'npm run build',
      `php -r "touch('database/e2e.sqlite');"`,
      'php artisan migrate:fresh --seed --seeder=E2eSeeder --force',
      `php artisan serve --port=${PORT}`,
    ].join(' && '),
    url: `http://localhost:${PORT}`,
    reuseExistingServer: false,
    timeout: 120_000,
    env: {
      E2E_TESTS: '1',
      DB_DATABASE: path.resolve('database/e2e.sqlite'),
      APP_URL: `http://localhost:${PORT}`,
      ASSET_URL: '',
      MAIL_MAILER: 'log',
      // login limiti testos traucē - array kešs neko nesaglabā starp pieprasījumiem
      CACHE_STORE: 'array',
    },
  },
})
