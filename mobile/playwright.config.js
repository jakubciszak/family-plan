const { defineConfig, devices } = require('@playwright/test');

const port = process.env.EXPO_WEB_PORT || '19082';

module.exports = defineConfig({
  testDir: './e2e',
  testIgnore: '**/real-api/**',
  timeout: 45 * 1000,
  expect: { timeout: 10 * 1000 },
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],

  use: {
    baseURL: `http://localhost:${port}`,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: process.env.CI ? 'retain-on-failure' : 'off',
  },

  projects: [
    {
      name: 'pixel',
      use: { ...devices['Pixel 5'], locale: 'pl-PL' },
    },
  ],

  webServer: {
    command: `npx expo start --web --port ${port}`,
    url: `http://localhost:${port}`,
    reuseExistingServer: false,
    timeout: 180 * 1000,
  },
});
