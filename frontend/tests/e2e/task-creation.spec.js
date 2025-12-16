const { test, expect } = require('@playwright/test');
const { 
  mockApiResponses,
  setupAuthenticatedSession
} = require('./fixtures');

test.describe('Task Creation', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session as regular user
    await setupAuthenticatedSession(page, 'user');
    await page.goto('/');
    await page.waitForSelector('.task-list-container');
  });

  test('should show create task form when button clicked', async ({ page }) => {
    // Click create task button
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check that form is visible
    await expect(page.locator('.task-create-form')).toBeVisible();
    
    // Check form fields
    await expect(page.locator('input#name')).toBeVisible();
    await expect(page.locator('textarea#description')).toBeVisible();
    await expect(page.locator('input#points')).toBeVisible();
    await expect(page.locator('select#frequency')).toBeVisible();
    await expect(page.locator('.task-create-form button[type="submit"]')).toContainText('Create Task');
  });

  test('should hide create task form when cancel clicked', async ({ page }) => {
    // Click create task button to show form
    await page.click('.task-list-header button:has-text("Create Task")');
    await expect(page.locator('.task-create-form')).toBeVisible();
    
    // Click cancel (same button now shows Cancel)
    await page.click('.task-list-header button:has-text("Cancel")');
    
    // Check that form is hidden
    await expect(page.locator('.task-create-form')).not.toBeVisible();
  });

  test('should have proper form field labels', async ({ page }) => {
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check labels
    await expect(page.locator('label[for="name"]')).toContainText('Task Name');
    await expect(page.locator('label[for="description"]')).toContainText('Description');
    await expect(page.locator('label[for="points"]')).toContainText('Points');
    await expect(page.locator('label[for="frequency"]')).toContainText('Frequency');
  });

  test('should have required fields marked', async ({ page }) => {
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check required attributes
    await expect(page.locator('input#name')).toHaveAttribute('required', '');
    await expect(page.locator('input#points')).toHaveAttribute('required', '');
    await expect(page.locator('select#frequency')).toHaveAttribute('required', '');
  });

  test('should have proper input types and constraints', async ({ page }) => {
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check input types
    await expect(page.locator('input#name')).toHaveAttribute('type', 'text');
    await expect(page.locator('input#points')).toHaveAttribute('type', 'number');
    
    // Check number constraints
    await expect(page.locator('input#points')).toHaveAttribute('min', '0');
    await expect(page.locator('input#points')).toHaveAttribute('max', '1000');
  });

  test('should have all frequency options', async ({ page }) => {
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check frequency options
    const options = await page.locator('select#frequency option').allTextContents();
    expect(options).toContain('Once');
    expect(options).toContain('Daily');
    expect(options).toContain('Weekly');
    expect(options).toContain('Monthly');
  });

  test('should create task successfully', async ({ page }) => {
    // Mock task creation endpoint
    await page.route('**/api/tasks', async route => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify(mockApiResponses.newTask)
        });
      } else if (route.request().method() === 'GET') {
        // Return updated task list
        const updatedTasks = {
          tasks: [...mockApiResponses.sampleTasks.tasks, mockApiResponses.newTask]
        };
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(updatedTasks)
        });
      }
    });
    
    // Open create form
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Fill in form
    await page.fill('input#name', 'New Test Task');
    await page.fill('textarea#description', 'This is a test task description');
    await page.fill('input#points', '25');
    await page.selectOption('select#frequency', 'weekly');
    
    // Submit form
    await page.click('.task-create-form button[type="submit"]');
    
    // Wait for form to close
    await expect(page.locator('.task-create-form')).not.toBeVisible();
    
    // Wait for task list to update by checking task count
    const taskCards = page.locator('.task-card');
    await expect(taskCards).toHaveCount(4);
  });

  test('should handle form input changes correctly', async ({ page }) => {
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Fill in form with test data
    const testName = 'Test Task Name';
    const testDescription = 'Test task description';
    const testPoints = '50';
    
    await page.fill('input#name', testName);
    await page.fill('textarea#description', testDescription);
    await page.fill('input#points', testPoints);
    await page.selectOption('select#frequency', 'monthly');
    
    // Verify values are set correctly
    await expect(page.locator('input#name')).toHaveValue(testName);
    await expect(page.locator('textarea#description')).toHaveValue(testDescription);
    await expect(page.locator('input#points')).toHaveValue(testPoints);
    await expect(page.locator('select#frequency')).toHaveValue('monthly');
  });

  test('should clear form after successful submission', async ({ page }) => {
    // Mock task creation
    await page.route('**/api/tasks', async route => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify(mockApiResponses.newTask)
        });
      }
    });
    
    // Open form and fill it
    await page.click('.task-list-header button:has-text("Create Task")');
    await page.fill('input#name', 'Test Task');
    await page.fill('textarea#description', 'Description');
    await page.fill('input#points', '10');
    
    // Submit
    await page.click('.task-create-form button[type="submit"]');
    
    // Wait for form to close
    await expect(page.locator('.task-create-form')).not.toBeVisible();
    
    // Reopen the form
    await page.click('.task-list-header button:has-text("Create Task")');
    await expect(page.locator('.task-create-form')).toBeVisible();
    
    // Verify form is empty
    await expect(page.locator('input#name')).toHaveValue('');
    await expect(page.locator('textarea#description')).toHaveValue('');
    await expect(page.locator('input#points')).toHaveValue('0');
  });

  test('should have proper default values', async ({ page }) => {
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check default values
    await expect(page.locator('input#name')).toHaveValue('');
    await expect(page.locator('textarea#description')).toHaveValue('');
    await expect(page.locator('input#points')).toHaveValue('0');
    await expect(page.locator('select#frequency')).toHaveValue('once');
  });
});
