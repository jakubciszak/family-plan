const { test, expect } = require('@playwright/test');

const isRealApiEnabled = Boolean(process.env.REAL_API);
const baseUrl = process.env.REAL_APP_URL || process.env.BASE_URL || 'http://localhost:3000';
const email = process.env.E2E_EMAIL || 'admin@familyplan.local';
const password = process.env.E2E_PASSWORD || 'FamilyPlanE2E123!';

test.describe('Real API - Login', () => {
  test.skip(!isRealApiEnabled, 'REAL_API not enabled');

  test('logs in with real backend credentials', async ({ page }) => {
    await page.goto(baseUrl);

    await expect(page.locator('h2')).toContainText('Login');
    await page.fill('input#email', email);
    await page.fill('input#password', password);
    await page.click('button[type="submit"]');

    await page.waitForSelector('.app-header', { timeout: 15000 });
    await expect(page.locator('.user-info')).toBeVisible();
    await expect(page.locator('.user-info')).toContainText('Welcome');
  });
});
