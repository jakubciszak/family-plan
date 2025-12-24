const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

test.describe('User Points on Login', () => {
  test('should fetch and display points after login', async ({ page }) => {
    // Mock the login response
    await page.route('**/api/auth/login', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Login successful',
          user: {
            id: '550e8400-e29b-41d4-a716-446655440000',
            name: 'Test User',
            email: 'test@example.com',
            role: 'ROLE_USER'
          }
        })
      });
    });

    // Mock the me endpoint
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          id: '550e8400-e29b-41d4-a716-446655440000',
          name: 'Test User',
          email: 'test@example.com',
          role: 'ROLE_USER'
        })
      });
    });

    // Mock points endpoint
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          userId: '550e8400-e29b-41d4-a716-446655440000',
          balance: 250
        })
      });
    });

    // Mock tasks endpoint
    await page.route('**/api/tasks', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ tasks: [] })
      });
    });

    await page.goto('/');
    
    // Fill in login form
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Wait for redirect and points to load
    await page.waitForSelector('.user-points');
    
    // Check points display
    const pointsDisplay = page.locator('.user-points');
    await expect(pointsDisplay).toContainText('250 points');
  });

  test('should clear points on logout', async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    
    // Mock points
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          userId: '550e8400-e29b-41d4-a716-446655440000',
          balance: 100
        })
      });
    });

    // Mock logout
    await page.route('**/api/auth/logout', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Logout successful' })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.user-points');
    
    // Verify points are displayed
    await expect(page.locator('.user-points')).toContainText('100 points');
    
    // Logout
    await page.click('button:has-text("Logout")');
    
    // Should redirect to login page
    await page.waitForSelector('.login-form');
    
    // Points should not be visible
    await expect(page.locator('.user-points')).not.toBeVisible();
  });
});
