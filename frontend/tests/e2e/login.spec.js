const { test, expect } = require('@playwright/test');
const { 
  testCredentials, 
  mockApiResponses,
  setupUnauthenticatedSession 
} = require('./fixtures');

test.describe('Login Page', () => {
  test.beforeEach(async ({ page }) => {
    // Setup unauthenticated session
    await setupUnauthenticatedSession(page);
  });

  test('should display login form', async ({ page }) => {
    await page.goto('/');
    
    // Check that login form is visible
    await expect(page.locator('h2')).toContainText('Login');
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should show validation for empty fields', async ({ page }) => {
    await page.goto('/');
    
    // Try to submit empty form
    await page.click('button[type="submit"]');
    
    // HTML5 validation should prevent submission
    const emailInput = page.locator('input#email');
    await expect(emailInput).toHaveAttribute('required', '');
  });

  test('should login successfully with valid credentials', async ({ page }) => {
    // Initially mock as unauthenticated
    let authenticated = false;
    
    await page.route('**/api/auth/me', async route => {
      if (!authenticated) {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Unauthorized' })
        });
      } else {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockApiResponses.currentUser)
        });
      }
    });
    
    // Mock successful login response
    await page.route('**/api/auth/login', async route => {
      authenticated = true; // Mark as authenticated after login
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.loginSuccess)
      });
    });
    
    // Mock tasks endpoint
    await page.route('**/api/tasks', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.emptyTasks)
      });
    });
    
    await page.goto('/');
    
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: 10000 });
    
    // Fill in login form
    await page.fill('input#email', testCredentials.user.email);
    await page.fill('input#password', testCredentials.user.password);
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Wait for navigation and check for successful login
    await page.waitForSelector('.app-header', { timeout: 5000 });
    
    // Verify we're on the main app page
    await expect(page.locator('h1')).toContainText('Family Plan');
    await expect(page.locator('.user-info')).toContainText('Welcome, Test User');
  });

  test('should show error message for invalid credentials', async ({ page }) => {
    // Mock failed login response
    await page.route('**/api/auth/login', async route => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.loginError)
      });
    });
    
    await page.goto('/');
    
    // Fill in login form with invalid credentials
    await page.fill('input#email', testCredentials.invalid.email);
    await page.fill('input#password', testCredentials.invalid.password);
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Check for error message
    await expect(page.locator('.error-message')).toContainText('Invalid email or password');
    
    // Verify we're still on login page
    await expect(page.locator('h2')).toContainText('Login');
  });

  test('should have proper input types', async ({ page }) => {
    await page.goto('/');
    
    // Check input types
    await expect(page.locator('input#email')).toHaveAttribute('type', 'email');
    await expect(page.locator('input#password')).toHaveAttribute('type', 'password');
  });

  test('should have accessible form labels', async ({ page }) => {
    await page.goto('/');
    
    // Check for proper labels
    const emailLabel = page.locator('label[for="email"]');
    const passwordLabel = page.locator('label[for="password"]');
    
    await expect(emailLabel).toContainText('Email');
    await expect(passwordLabel).toContainText('Password');
  });
});
