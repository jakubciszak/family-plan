const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

test.describe('User Points on Login', () => {
  test('should fetch and display points after login', async ({ page }) => {
    let isLoggedIn = false;
    
    // Mock the me endpoint - return 401 before login, success after
    await page.route('**/api/auth/me', async route => {
      if (!isLoggedIn) {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Unauthorized' })
        });
      } else {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            role: 'ROLE_USER'
          })
        });
      }
    });
    
    // Mock the login response
    await page.route('**/api/auth/login', async route => {
      isLoggedIn = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Login successful',
          user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            role: 'ROLE_USER'
          }
        })
      });
    });

    // Mock points endpoint
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          userId: 1,
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
    
    // Wait for login form to be visible
    await page.waitForSelector('input#email', { timeout: 10000 });
    
    // Fill in login form
    await page.fill('input#email', 'test@example.com');
    await page.fill('input#password', 'password123');
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
