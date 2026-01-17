const { test, expect } = require('@playwright/test');
const { setupUnauthenticatedSession } = require('./fixtures');

// Helper function to navigate to registration page
async function navigateToRegistration(page) {
  await page.goto('/');
  await page.waitForSelector('h2:has-text("Login")', { timeout: 10000 });
  await page.locator('.auth-switch a').click();
  await page.waitForSelector('h2:has-text("Register")', { timeout: 5000 });
}

test.describe('Registration Page', () => {
  test.beforeEach(async ({ page }) => {
    // Setup unauthenticated session
    await setupUnauthenticatedSession(page);
  });

  test('should display registration form when clicking register link', async ({ page }) => {
    await navigateToRegistration(page);

    // Check that registration form is visible
    await expect(page.locator('h2')).toContainText('Register');
    await expect(page.locator('input#name')).toBeVisible();
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('input#phoneNumber')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should have link to go back to login', async ({ page }) => {
    await navigateToRegistration(page);

    // Check for auth-switch link
    await expect(page.locator('.auth-switch')).toBeVisible();

    // Click back to login
    const loginLink = page.locator('.auth-switch a');
    await loginLink.click();

    // Should be back on login page
    await page.waitForSelector('h2:has-text("Login")', { timeout: 5000 });
    await expect(page.locator('h2')).toContainText('Login');
  });

  test('should show validation for empty required fields', async ({ page }) => {
    await navigateToRegistration(page);

    // HTML5 validation should show required attributes
    const nameInput = page.locator('input#name');
    const emailInput = page.locator('input#email');
    const passwordInput = page.locator('input#password');

    await expect(nameInput).toHaveAttribute('required', '');
    await expect(emailInput).toHaveAttribute('required', '');
    await expect(passwordInput).toHaveAttribute('required', '');
  });

  test('should register successfully with valid data', async ({ page }) => {
    // Mock successful registration response - must be set before navigation
    await page.route('**/api/auth/register', async route => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'User registered successfully. Please check your email to activate your account.'
        })
      });
    });

    await navigateToRegistration(page);

    // Fill in registration form
    await page.fill('input#name', 'Jan Kowalski');
    await page.fill('input#email', 'jan@example.com');
    await page.fill('input#password', 'password123');
    await page.fill('input#phoneNumber', '+48123456789');

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for and check success message
    await page.waitForSelector('.success-message', { timeout: 5000 });
    await expect(page.locator('.success-message')).toBeVisible();
    await expect(page.locator('.success-message')).toContainText('Registration successful!');
  });

  test('should register successfully without phone number', async ({ page }) => {
    // Mock successful registration response
    await page.route('**/api/auth/register', async route => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'User registered successfully. Please check your email to activate your account.'
        })
      });
    });

    await navigateToRegistration(page);

    // Fill in registration form without phone number
    await page.fill('input#name', 'Anna Nowak');
    await page.fill('input#email', 'anna@example.com');
    await page.fill('input#password', 'securePass456');

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for and check success message
    await page.waitForSelector('.success-message', { timeout: 5000 });
    await expect(page.locator('.success-message')).toBeVisible();
    await expect(page.locator('.success-message')).toContainText('Registration successful!');
  });

  test('should show error for duplicate email', async ({ page }) => {
    // Mock failed registration response
    await page.route('**/api/auth/register', async route => {
      await route.fulfill({
        status: 400,
        contentType: 'application/json',
        body: JSON.stringify({
          error: 'User with this email already exists'
        })
      });
    });

    await navigateToRegistration(page);

    // Fill in registration form
    await page.fill('input#name', 'Jan Kowalski');
    await page.fill('input#email', 'existing@example.com');
    await page.fill('input#password', 'password123');

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for and check error message
    await page.waitForSelector('.error-message', { timeout: 5000 });
    await expect(page.locator('.error-message')).toBeVisible();
    await expect(page.locator('.error-message')).toContainText('already exists');
  });

  test('should show generic error for registration failure', async ({ page }) => {
    // Mock failed registration response
    await page.route('**/api/auth/register', async route => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          error: 'Internal server error'
        })
      });
    });

    await navigateToRegistration(page);

    // Fill in registration form
    await page.fill('input#name', 'Test User');
    await page.fill('input#email', 'test@example.com');
    await page.fill('input#password', 'password123');

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for and check error message
    await page.waitForSelector('.error-message', { timeout: 5000 });
    await expect(page.locator('.error-message')).toBeVisible();
    await expect(page.locator('.error-message')).toContainText('Registration failed');
  });

  test('should have proper input types', async ({ page }) => {
    await navigateToRegistration(page);

    // Check input types
    await expect(page.locator('input#name')).toHaveAttribute('type', 'text');
    await expect(page.locator('input#email')).toHaveAttribute('type', 'email');
    await expect(page.locator('input#password')).toHaveAttribute('type', 'password');
    await expect(page.locator('input#phoneNumber')).toHaveAttribute('type', 'tel');
  });

  test('should have accessible form labels', async ({ page }) => {
    await navigateToRegistration(page);

    // Check for proper labels
    const nameLabel = page.locator('label[for="name"]');
    const emailLabel = page.locator('label[for="email"]');
    const passwordLabel = page.locator('label[for="password"]');
    const phoneLabel = page.locator('label[for="phoneNumber"]');

    // Just verify labels exist and are visible
    await expect(nameLabel).toBeVisible();
    await expect(emailLabel).toBeVisible();
    await expect(passwordLabel).toBeVisible();
    await expect(phoneLabel).toBeVisible();
  });

  test('should clear form on successful registration', async ({ page }) => {
    // Mock successful registration response
    await page.route('**/api/auth/register', async route => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'User registered successfully. Please check your email to activate your account.'
        })
      });
    });

    await navigateToRegistration(page);

    // Fill in registration form
    await page.fill('input#name', 'Test User');
    await page.fill('input#email', 'test@example.com');
    await page.fill('input#password', 'password123');

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for success message
    await page.waitForSelector('.success-message', { timeout: 5000 });
    await expect(page.locator('.success-message')).toBeVisible();

    // Check that form is cleared
    await expect(page.locator('input#name')).toHaveValue('');
    await expect(page.locator('input#email')).toHaveValue('');
    await expect(page.locator('input#password')).toHaveValue('');
  });
});
