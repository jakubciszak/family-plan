const { test, expect } = require('@playwright/test');
const { login, getAdminCredentials, TIMEOUTS } = require('./helpers');

/**
 * Integration tests for Login functionality
 * These tests run against the real backend API
 * 
 * Prerequisites:
 * - Backend API must be running (docker-compose up)
 * - Database must be seeded with test user
 */

test.describe('Login Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    // No mocking - tests run against real API
    // Just navigate to the page, let individual tests wait for what they need
    await page.goto('/', { waitUntil: 'networkidle', timeout: TIMEOUTS.LONG });
  });

  test('should display login form', async ({ page }) => {
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
    
    // Check that login form is visible
    await expect(page.locator('h2')).toContainText('Login');
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should show validation for empty fields', async ({ page }) => {
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
    
    // Try to submit empty form
    await page.click('button[type="submit"]');
    
    // HTML5 validation should prevent submission
    const emailInput = page.locator('input#email');
    await expect(emailInput).toHaveAttribute('required', '');
  });

  test('should show error for invalid credentials', async ({ page }) => {
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
    
    // Fill in login form with invalid credentials
    await page.fill('input#email', 'invalid@example.com');
    await page.fill('input#password', 'wrongpassword');
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Wait a bit for API response
    await page.waitForTimeout(2000);
    
    // Check for error message
    await expect(page.locator('.error-message')).toContainText('Invalid email or password', { timeout: TIMEOUTS.MEDIUM });
    
    // Verify we're still on login page
    await expect(page.locator('h2')).toContainText('Login');
  });

  test('should login successfully with valid credentials', async ({ page }) => {
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
    
    // Use admin credentials from helper
    const { email, password } = getAdminCredentials();
    
    // Fill in login form
    await page.fill('input#email', email);
    await page.fill('input#password', password);
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Wait for navigation and check for successful login
    await page.waitForSelector('.app-header', { state: 'visible', timeout: TIMEOUTS.LONG });
    
    // Verify we're on the main app page
    await expect(page.locator('h1')).toContainText('Family Plan', { timeout: TIMEOUTS.MEDIUM });
    await expect(page.locator('.user-info')).toBeVisible({ timeout: TIMEOUTS.MEDIUM });
  });

  test('should have proper input types', async ({ page }) => {
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
    
    // Check input types
    await expect(page.locator('input#email')).toHaveAttribute('type', 'email');
    await expect(page.locator('input#password')).toHaveAttribute('type', 'password');
  });

  test('should have accessible form labels', async ({ page }) => {
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: TIMEOUTS.MEDIUM });
    
    // Check for proper labels
    const emailLabel = page.locator('label[for="email"]');
    const passwordLabel = page.locator('label[for="password"]');
    
    await expect(emailLabel).toContainText('Email');
    await expect(passwordLabel).toContainText('Password');
  });
});
