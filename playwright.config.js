// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  retries: 0,
  workers: 1, // séquentiel pour éviter conflits DB
  reporter: [['html', { outputFolder: 'playwright-report', open: 'never' }], ['list']],
  use: {
    baseURL: 'http://localhost:8000',
    headless: false,        // visible pour la démo
    slowMo: 300,            // ralenti pour être lisible
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'fr-FR',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
