/**
 * Helper utilities for integration tests
 */

/**
 * Timeout constants for integration tests
 */
const TIMEOUTS = {
  SHORT: 5000,    // 5 seconds - for quick UI updates
  MEDIUM: 10000,  // 10 seconds - for API calls and page loads
  LONG: 15000     // 15 seconds - for complex operations
};

/**
 * Login to the application with given credentials
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} email - User email
 * @param {string} password - User password
 */
async function login(page, email, password) {
  await page.goto('/');
  await page.fill('input#email', email);
  await page.fill('input#password', password);
  await page.click('button[type="submit"]');
  await page.waitForSelector('.app-header', { timeout: TIMEOUTS.MEDIUM });
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
