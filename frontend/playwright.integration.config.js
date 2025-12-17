const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright configuration for Family Plan Frontend - Integration Tests
 * These tests run against the real backend API (not mocked)
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests/integration',
  
  // Maximum time one test can run - longer for real API calls
  timeout: 120 * 1000,
  
  expect: {
    // Maximum time expect() should wait for the condition to be met
    timeout: 10000
  },
  
  // Run tests in files in parallel
  fullyParallel: false,
  
  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 2 : 0,
  
  // Opt out of parallel tests for integration
  workers: 1,
  
  // Reporter to use
  reporter: 'html',
  
  use: {
    // Base URL to use in actions like `await page.goto('/')`
    baseURL: process.env.BASE_URL || 'http://localhost:3000',
    
    // Collect trace when retrying the failed test
    trace: 'on-first-retry',
    
    // Take screenshots after each test
    screenshot: 'on',
    
    // Record video for all tests
    video: 'on',
    
    // Increase navigation timeout for real API calls
    navigationTimeout: 30000,
    
    // Increase action timeout for real API calls
    actionTimeout: 15000,
  },

  // Configure projects for major browsers
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // No webServer defined - expects services to be running via docker-compose
});
