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
    await page.goto('/');
  });

  test('should display login form', async ({ page }) => {
    // Check that login form is visible
    await expect(page.locator('h2')).toContainText('Login');
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should show validation for empty fields', async ({ page }) => {
    // Try to submit empty form
    await page.click('button[type="submit"]');
    
    // HTML5 validation should prevent submission
    const emailInput = page.locator('input#email');
    await expect(emailInput).toHaveAttribute('required', '');
  });

  test('should show error for invalid credentials', async ({ page }) => {
    // Fill in login form with invalid credentials
    await page.fill('input#email', 'invalid@example.com');
    await page.fill('input#password', 'wrongpassword');
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Check for error message
    await expect(page.locator('.error-message')).toContainText('Invalid email or password');
    
    // Verify we're still on login page
    await expect(page.locator('h2')).toContainText('Login');
  });

  test('should login successfully with valid credentials', async ({ page }) => {
    // Use admin credentials from helper
    const { email, password } = getAdminCredentials();
    
    // Fill in login form
    await page.fill('input#email', email);
    await page.fill('input#password', password);
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Wait for navigation and check for successful login
    await page.waitForSelector('.app-header', { timeout: TIMEOUTS.MEDIUM });
    
    // Verify we're on the main app page
    await expect(page.locator('h1')).toContainText('Family Plan');
    await expect(page.locator('.user-info')).toBeVisible();
  });

  test('should have proper input types', async ({ page }) => {
    // Check input types
    await expect(page.locator('input#email')).toHaveAttribute('type', 'email');
    await expect(page.locator('input#password')).toHaveAttribute('type', 'password');
  });

  test('should have accessible form labels', async ({ page }) => {
    // Check for proper labels
    const emailLabel = page.locator('label[for="email"]');
    const passwordLabel = page.locator('label[for="password"]');
    
    await expect(emailLabel).toContainText('Email');
    await expect(passwordLabel).toContainText('Password');
  });
});
