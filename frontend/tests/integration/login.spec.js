const { test, expect } = require('@playwright/test');
const { login, getAdminCredentials, TIMEOUTS, waitForApiResponse } = require('./helpers');

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
    await page.goto('/', { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.LONG });
  });

  test('should display login form', async ({ page }) => {
    // Wait for login form to be visible
    const emailInput = page.getByLabel('Email');
    await emailInput.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Check that login form is visible
    await expect(page.getByRole('heading', { name: 'Login' })).toBeVisible();
    await expect(emailInput).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Login' })).toBeVisible();
  });

  test('should show validation for empty fields', async ({ page }) => {
    // Wait for login form to be visible
    const emailInput = page.getByLabel('Email');
    await emailInput.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Try to submit empty form
    await page.getByRole('button', { name: 'Login' }).click();
    
    // HTML5 validation should prevent submission
    await expect(emailInput).toHaveAttribute('required', '');
  });

  test('should show error for invalid credentials', async ({ page }) => {
    // Wait for login form to be visible
    const emailInput = page.getByLabel('Email');
    await emailInput.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Fill in login form with invalid credentials
    await emailInput.fill('invalid@example.com');
    await page.getByLabel('Password').fill('wrongpassword');
    
    // Submit form
    const loginResponse = waitForApiResponse(page, '/api/auth/login', {
      predicate: (response) => response.request().method() === 'POST' && response.status() >= 400,
    });
    await Promise.all([
      loginResponse,
      page.getByRole('button', { name: 'Login' }).click(),
    ]);
    
    // Check for error message
    await expect(page.locator('.error-message')).toContainText('Invalid email or password', { timeout: TIMEOUTS.MEDIUM });
    
    // Verify we're still on login page
    await expect(page.getByRole('heading', { name: 'Login' })).toBeVisible();
  });

  test('should login successfully with valid credentials', async ({ page }) => {
    // Wait for login form to be visible
    const emailInput = page.getByLabel('Email');
    await emailInput.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Use admin credentials from helper
    const { email, password } = getAdminCredentials();
    
    // Fill in login form
    await emailInput.fill(email);
    await page.getByLabel('Password').fill(password);
    
    // Submit form
    const loginResponse = waitForApiResponse(page, '/api/auth/login', {
      predicate: (response) => response.request().method() === 'POST' && response.ok(),
    });
    await Promise.all([
      loginResponse,
      page.getByRole('button', { name: 'Login' }).click(),
    ]);
    
    // Wait for navigation and check for successful login
    await page.waitForSelector('.app-header', { state: 'visible', timeout: TIMEOUTS.LONG });
    
    // Verify we're on the main app page
    await expect(page.locator('h1')).toContainText('Family Plan', { timeout: TIMEOUTS.MEDIUM });
    await expect(page.locator('.user-info')).toBeVisible({ timeout: TIMEOUTS.MEDIUM });
  });

  test('should have proper input types', async ({ page }) => {
    // Wait for login form to be visible
    await page.getByLabel('Email').waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Check input types
    await expect(page.getByLabel('Email')).toHaveAttribute('type', 'email');
    await expect(page.getByLabel('Password')).toHaveAttribute('type', 'password');
  });

  test('should have accessible form labels', async ({ page }) => {
    // Wait for login form to be visible
    await page.getByLabel('Email').waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Check for proper labels
    const emailLabel = page.locator('label[for="email"]');
    const passwordLabel = page.locator('label[for="password"]');
    
    await expect(emailLabel).toContainText('Email');
    await expect(passwordLabel).toContainText('Password');
  });
});
