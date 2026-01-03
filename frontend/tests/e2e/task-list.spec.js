const { test, expect } = require('@playwright/test');
const { 
  mockApiResponses,
  setupAuthenticatedSession
} = require('./fixtures');

test.describe('Task List', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session as regular user
    await setupAuthenticatedSession(page, 'user');
  });

  test('should display task list header', async ({ page }) => {
    await page.goto('/');
    
    // Wait for app to load
    await page.waitForSelector('.task-list-container');
    
    // Check for main elements
    await expect(page.locator('h1')).toContainText('Family Plan');
    await expect(page.locator('h2')).toContainText('Tasks');
    await expect(page.locator('.task-list-header button')).toContainText('Create Task');
  });

  test('should display loading state initially', async ({ page }) => {
    // Mock delayed response
    await page.route('**/api/tasks', async route => {
      await new Promise(resolve => setTimeout(resolve, 100));
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.sampleTasks)
      });
    });
    
    await page.goto('/');
    
    // Check for loading state (translated text)
    const loading = page.locator('.loading');
    await expect(loading).toBeVisible();
    await expect(loading).toContainText('Loading...');
  });

  test('should display empty state when no tasks', async ({ page }) => {
    // Mock empty tasks response
    await page.route('**/api/tasks', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.emptyTasks)
      });
    });
    
    await page.goto('/');
    await page.waitForSelector('.tasks');
    
    // Check for empty message (translated text)
    await expect(page.locator('.tasks')).toContainText('No tasks available');
  });

  test('should display list of tasks', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Check that tasks are displayed
    const taskCards = page.locator('.task-card');
    await expect(taskCards).toHaveCount(3);
    
    // Check first task details
    const firstTask = taskCards.first();
    await expect(firstTask.locator('.task-header h3')).toContainText('Clean the kitchen');
    await expect(firstTask.locator('.task-body p')).toContainText('Wash dishes and clean counters');
    await expect(firstTask.locator('.task-points')).toContainText('10 points');
    await expect(firstTask.locator('.task-frequency')).toContainText('daily');
    await expect(firstTask.locator('.task-status')).toContainText('pending');
  });

  test('should display task status with correct styling', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Check for status classes
    const pendingStatus = page.locator('.status-pending').first();
    await expect(pendingStatus).toBeVisible();
    
    const completedStatus = page.locator('.status-completed').first();
    await expect(completedStatus).toBeVisible();
  });

  test('should show complete button for pending tasks', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Find a pending task assigned to the current user
    const pendingTask = page.locator('.task-card').filter({ hasText: 'Take out trash' });
    
    // Check for complete button
    await expect(pendingTask.locator('.btn-success')).toContainText('Complete');
  });

  test('should not show complete button for completed tasks', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Find a completed task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    
    // Check that complete button is not visible
    const completeButton = completedTask.locator('.btn-success');
    await expect(completeButton).not.toBeVisible();
  });

  test('should display user info in header', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.app-header');
    
    // Check user info
    await expect(page.locator('.user-info')).toContainText('Welcome, Test User');
    // Check specifically for logout button (not language buttons)
    await expect(page.getByRole('button', { name: 'Logout' })).toBeVisible();
  });

  test('should show all task metadata', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    const firstTask = page.locator('.task-card').first();
    const taskMeta = firstTask.locator('.task-meta');
    
    // Check that metadata is displayed
    await expect(taskMeta.locator('.task-points')).toBeVisible();
    await expect(taskMeta.locator('.task-frequency')).toBeVisible();
  });
});

test.describe('Task List - Admin View', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session as admin
    await setupAuthenticatedSession(page, 'admin');
  });

  test('should show approve button for completed tasks when admin', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Find a completed task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    
    // Check for approve button
    await expect(completedTask.locator('.btn-primary')).toContainText('Approve');
  });

  test('should display admin user info', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.app-header');
    
    // Check admin user info
    await expect(page.locator('.user-info')).toContainText('Welcome, Admin User');
  });
});
