const { defineConfig, devices } = require('@playwright/test');

const api = process.env.MOBILE_API_URL || 'http://127.0.0.1:19080';
const port = process.env.EXPO_WEB_PORT || '19083';

module.exports = defineConfig({
  testDir: './e2e/real-api',
  outputDir: './test-results-real',
  timeout: 60000,
  expect: { timeout: 10000 },
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  use: {
    ...devices['Pixel 5'],
    locale: 'pl-PL',
    baseURL: `http://localhost:${port}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: [
    {
      command: 'bash ../scripts/start-mobile-api.sh',
      url: `${api}/api/doc`,
      reuseExistingServer: process.env.MOBILE_REUSE_API === '1',
      timeout: 120000,
    },
    {
      command: `npx expo start --web --port ${port}`,
      env: { EXPO_PUBLIC_API_URL: api },
      url: `http://localhost:${port}`,
      reuseExistingServer: false,
      timeout: 180000,
    },
  ],
});
