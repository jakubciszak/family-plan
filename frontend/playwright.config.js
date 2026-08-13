const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright configuration for Family Plan Frontend
 * @see https://playwright.dev/docs/test-configuration
 */
const apiBaseUrl = process.env.API_BASE_URL || 'http://localhost:8080';
const realApiEnabled = Boolean(process.env.REAL_API);
const testDir = realApiEnabled ? './tests/e2e/real-api' : './tests/e2e';

const webServers = [
  {
    command: `REACT_APP_API_URL=${apiBaseUrl} npm start`,
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
];

if (realApiEnabled) {
  webServers.unshift({
    command: `API_BASE_URL=${apiBaseUrl} bash ../scripts/start-backend-e2e.sh`,
    url: `${apiBaseUrl}/api/doc`,
    reuseExistingServer: false,
    timeout: 120 * 1000,
  });
}

module.exports = defineConfig({
  testDir,
  testIgnore: realApiEnabled ? [] : ['**/*-real.spec.js', '**/real-api/**'],
  
  // Maximum time one test can run
  timeout: 30 * 1000,
  
  expect: {
    // Maximum time expect() should wait for the condition to be met
    timeout: 5000
  },
  
  // Run tests in files in parallel
  fullyParallel: true,
  
  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 2 : 0,
  
  // Use multiple workers for faster execution
  workers: process.env.CI ? 2 : undefined,
  
  // Reporter to use
  reporter: 'html',
  
  use: {
    // Base URL to use in actions like `await page.goto('/')`
    baseURL: process.env.BASE_URL || 'http://localhost:3000',
    
    // Collect trace when retrying the failed test
    trace: 'on-first-retry',
    
    // Take screenshots only on failure
    screenshot: 'only-on-failure',
    
    // Record video only on failure
    video: 'retain-on-failure',
  },

  // Configure projects for major browsers
  // Only run on chromium by default for speed - other browsers can be run separately if needed
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    // Uncomment to test on other browsers
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },

    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },

    // // Test against mobile viewports
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
    // {
    //   name: 'Mobile Safari',
    //   use: { ...devices['iPhone 12'] },
    // },
  ],

  // Run your local dev server before starting the tests
  webServer: webServers,
});
