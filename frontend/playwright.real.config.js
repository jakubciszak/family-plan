const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright configuration for Real E2E Tests (Frontend + Backend)
 *
 * Prerequisites:
 * 1. Start the backend: cd /home/user/family-plan && make up
 * 2. Run tests: npx playwright test --config=playwright.real.config.js
 *
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests/e2e',

  // Only run real E2E tests (not mocked tests)
  testMatch: '**/user-workflow-real.spec.js',

  // Maximum time one test can run - longer for real tests
  timeout: 60 * 1000,

  expect: {
    // Maximum time expect() should wait - longer for real backend
    timeout: 15000
  },

  // Run tests serially for real E2E (they depend on each other)
  fullyParallel: false,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Single worker for serial tests
  workers: 1,

  // Reporter to use
  reporter: [
    ['list'],
    ['html', { open: 'never' }]
  ],

  use: {
    // Base URL - frontend dev server
    baseURL: process.env.BASE_URL || 'http://localhost:3000',

    // Collect trace on failure
    trace: 'on-first-retry',

    // Take screenshots on failure
    screenshot: 'only-on-failure',

    // Record video on failure
    video: 'retain-on-failure',

    // Slower actions for real backend
    actionTimeout: 15000,
    navigationTimeout: 30000,
  },

  // Configure projects
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Run frontend dev server before starting the tests
  // NOTE: Backend must be started separately with `make up`
  webServer: {
    command: 'npm start',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
});
