/**
 * Helper utilities for integration tests
 */

/**
 * Timeout constants for integration tests
 */
const TIMEOUTS = {
  SHORT: 20000,   // 20 seconds - for quick UI updates
  MEDIUM: 40000,  // 40 seconds - for API calls and page loads
  LONG: 60000     // 60 seconds - for complex operations
};

/**
 * Wait for an API response matching a URL fragment and optional predicate.
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} urlFragment - URL fragment to match
 * @param {{ timeout?: number, predicate?: (response: import('@playwright/test').Response) => boolean }} options
 */
function waitForApiResponse(page, urlFragment, options = {}) {
  const { timeout = TIMEOUTS.LONG, predicate } = options;

  return page.waitForResponse(
    (response) => {
      if (!response.url().includes(urlFragment)) {
        return false;
      }
      return predicate ? predicate(response) : true;
    },
    { timeout }
  );
}

/**
 * Login to the application with given credentials
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} email - User email
 * @param {string} password - User password
 */
async function login(page, email, password) {
  // Navigate to login page
  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.LONG });
  
  // Wait for login form to be visible
  const emailInput = page.getByLabel('Email');
  await emailInput.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
  
  // Fill in credentials
  await emailInput.fill(email);
  await page.getByLabel('Password').fill(password);
  
  // Submit and wait for navigation
  const loginResponse = waitForApiResponse(page, '/api/auth/login', {
    predicate: (response) => response.request().method() === 'POST' && response.ok(),
  });
  await Promise.all([
    loginResponse,
    page.getByRole('button', { name: 'Login' }).click(),
  ]);
  
  // Wait for successful login - app header should appear
  await page.waitForSelector('.app-header', { timeout: TIMEOUTS.LONG });
}

/**
 * Get admin credentials from environment or defaults
 * @returns {{email: string, password: string}}
 */
function getAdminCredentials() {
  return {
    email: process.env.SUPER_ADMIN_EMAIL || 'admin@familyplan.local',
    password: process.env.SUPER_ADMIN_PASSWORD || 'admin123'
  };
}

module.exports = {
  login,
  getAdminCredentials,
  TIMEOUTS,
  waitForApiResponse,
};
