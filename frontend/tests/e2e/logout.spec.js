const { test, expect } = require('@playwright/test');
const { 
  mockApiResponses,
  setupAuthenticatedSession,
  setupUnauthenticatedSession
} = require('./fixtures');

test.describe('Logout Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session
    await setupAuthenticatedSession(page, 'user');
    await page.goto('/');
    await page.waitForSelector('.app-header');
  });

  test('should display logout button in header', async ({ page }) => {
    // Check for logout button
    const logoutButton = page.locator('.user-info button:has-text("Logout")');
    await expect(logoutButton).toBeVisible();
  });

  test('should logout successfully', async ({ page }) => {
    // Mock logout endpoint
    await page.route('**/api/auth/logout', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Mock unauthenticated response after logout
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Unauthorized' })
      });
    });
    
    // Click logout button
    await page.click('.user-info button:has-text("Logout")');
    
    // Wait for redirect to login page
    await page.waitForSelector('h2:has-text("Login")', { timeout: 5000 });
    
    // Verify we're on login page
    await expect(page.locator('h2')).toContainText('Login');
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
  });

  test('should redirect to login page after logout', async ({ page }) => {
    // Mock logout endpoint
    await page.route('**/api/auth/logout', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Mock unauthenticated response
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Unauthorized' })
      });
    });
    
    // Logout
    await page.click('.user-info button:has-text("Logout")');
    
    // Wait for login page
    await page.waitForSelector('.login-container');
    
    // Verify login form is visible
    await expect(page.locator('.login-form')).toBeVisible();
  });

  test('should clear user state after logout', async ({ page }) => {
    // Mock logout
    await page.route('**/api/auth/logout', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Mock unauthenticated
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Unauthorized' })
      });
    });
    
    // Verify user info before logout
    await expect(page.locator('.user-info')).toContainText('Welcome, Test User');
    
    // Logout
    await page.click('.user-info button:has-text("Logout")');
    
    // Wait for login page
    await page.waitForSelector('.login-container');
    
    // Verify user info is gone
    await expect(page.locator('.user-info')).not.toBeVisible();
    await expect(page.locator('.app-header')).not.toBeVisible();
  });

  test('should handle logout even if API fails', async ({ page }) => {
    // Mock logout endpoint to fail
    await page.route('**/api/auth/logout', async route => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Server error' })
      });
    });
    
    // Mock unauthenticated response
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Unauthorized' })
      });
    });
    
    // Logout
    await page.click('.user-info button:has-text("Logout")');
    
    // Wait for login page - should still redirect even if API fails
    await page.waitForSelector('.login-container', { timeout: 5000 });
    
    // Verify we're on login page
    await expect(page.locator('h2')).toContainText('Login');
  });
});

test.describe('Session Management', () => {
  test('should redirect to login when accessing app without authentication', async ({ page }) => {
    // Setup unauthenticated session
    await setupUnauthenticatedSession(page);
    
    // Try to access the app
    await page.goto('/');
    
    // Should see login page
    await expect(page.locator('h2')).toContainText('Login');
    await expect(page.locator('.login-form')).toBeVisible();
  });

  test('should maintain session across page reloads', async ({ page }) => {
    // Setup authenticated session
    await setupAuthenticatedSession(page, 'user');
    
    // Load page
    await page.goto('/');
    await page.waitForSelector('.app-header');
    
    // Verify authenticated
    await expect(page.locator('.user-info')).toContainText('Welcome, Test User');
    
    // Reload page
    await page.reload();
    await page.waitForSelector('.app-header');
    
    // Should still be authenticated
    await expect(page.locator('.user-info')).toContainText('Welcome, Test User');
  });

  test('should handle session expiration', async ({ page }) => {
    // Start with authenticated session
    await setupAuthenticatedSession(page, 'user');
    await page.goto('/');
    await page.waitForSelector('.app-header');
    
    // Simulate session expiration by changing the mock
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Session expired' })
      });
    });
    
    // Reload page
    await page.reload();
    
    // Should redirect to login
    await page.waitForSelector('.login-container');
    await expect(page.locator('h2')).toContainText('Login');
  });
});
