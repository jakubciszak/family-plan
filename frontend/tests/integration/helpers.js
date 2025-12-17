/**
 * Helper utilities for integration tests
 */

/**
 * Timeout constants for integration tests
 */
const TIMEOUTS = {
  SHORT: 10000,   // 10 seconds - for quick UI updates
  MEDIUM: 20000,  // 20 seconds - for API calls and page loads
  LONG: 30000     // 30 seconds - for complex operations
};

/**
 * Login to the application with given credentials
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} email - User email
 * @param {string} password - User password
 */
async function login(page, email, password) {
  // Navigate to login page with increased timeout
  await page.goto('/', { waitUntil: 'networkidle', timeout: TIMEOUTS.LONG });
  
  // Wait for login form to be visible
  await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
  
  // Fill in credentials
  await page.fill('input#email', email);
  await page.fill('input#password', password);
  
  // Submit and wait for navigation
  await page.click('button[type="submit"]');
  
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
  TIMEOUTS
};
