const { test, expect } = require('@playwright/test');
const { 
  mockApiResponses,
  setupAuthenticatedSession
} = require('./fixtures');

test.describe('Task Actions - Complete', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session manually without using the helper
    // because we need custom task list mocking
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.currentUser)
      });
    });
    
    await page.goto('/');
    await page.waitForSelector('.task-card');
  });

  test('should complete a pending task', async ({ page }) => {
    // Mock task completion endpoint
    await page.route('**/api/tasks/*/complete', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Mock updated task list with completed task
    let requestCount = 0;
    await page.route('**/api/tasks', async (route) => {
      if (route.request().method() === 'GET') {
        requestCount++;
        if (requestCount === 1) {
          // First request - return original tasks
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(mockApiResponses.sampleTasks)
          });
        } else {
          // Second request - return updated tasks
          const updatedTasks = {
            tasks: mockApiResponses.sampleTasks.tasks.map(task => 
              task.id === 1 ? { ...task, status: 'completed' } : task
            )
          };
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(updatedTasks)
          });
        }
      }
    });
    
    // Find and click complete button on first pending task
    const pendingTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    await pendingTask.locator('.btn-success:has-text("Complete")').click();
    
    // Wait for the request to complete by checking for network idle
    await page.waitForLoadState('networkidle');
  });

  test('should send correct data when completing task', async ({ page }) => {
    let requestData = null;
    
    // Mock and capture request data
    await page.route('**/api/tasks/1/complete', async route => {
      requestData = await route.request().postDataJSON();
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Complete task
    const pendingTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    await pendingTask.locator('.btn-success:has-text("Complete")').click();
    
    // Wait for request to complete
    await page.waitForLoadState('networkidle');
    
    // Verify request data includes userId
    expect(requestData).toBeTruthy();
    expect(requestData).toHaveProperty('userId');
  });

  test('should not show complete button after task is completed', async ({ page }) => {
    // Mock task completion
    await page.route('**/api/tasks/*/complete', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Mock updated task list - second request should return completed task
    let requestCount = 0;
    await page.route('**/api/tasks', async (route) => {
      if (route.request().method() === 'GET') {
        requestCount++;
        if (requestCount === 1) {
          // First request - return original tasks
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(mockApiResponses.sampleTasks)
          });
        } else {
          // Subsequent requests - return updated tasks with completed status
          const updatedTasks = {
            tasks: mockApiResponses.sampleTasks.tasks.map(task => 
              task.id === 1 ? { ...task, status: 'completed' } : task
            )
          };
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(updatedTasks)
          });
        }
      }
    });
    
    // Complete the task
    const pendingTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    await pendingTask.locator('.btn-success:has-text("Complete")').click();
    
    // Wait for the status to change to 'completed' as a signal that the UI updated
    const taskCard = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    await expect(taskCard.locator('.status-completed')).toBeVisible({ timeout: 3000 });
    
    // Now verify the complete button is not present
    const completeButton = taskCard.locator('.btn-success:has-text("Complete")');
    await expect(completeButton).not.toBeVisible();
  });
});

test.describe('Task Actions - Approve (Admin)', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session manually without using the helper
    await page.route('**/api/auth/me', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.currentAdmin)
      });
    });
    
    await page.goto('/');
    await page.waitForSelector('.task-card');
  });

  test('should show approve button for completed tasks when admin', async ({ page }) => {
    // Find a completed task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    
    // Check for approve button
    const approveButton = completedTask.locator('.btn-primary:has-text("Approve")');
    await expect(approveButton).toBeVisible();
  });

  test('should approve a completed task', async ({ page }) => {
    // Mock task approval endpoint
    await page.route('**/api/tasks/*/approve', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Mock updated task list with approved task
    let requestCount = 0;
    await page.route('**/api/tasks', async (route) => {
      if (route.request().method() === 'GET') {
        requestCount++;
        if (requestCount === 1) {
          // First request - return original tasks
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(mockApiResponses.sampleTasks)
          });
        } else {
          // Subsequent requests - return updated tasks with approved status
          const updatedTasks = {
            tasks: mockApiResponses.sampleTasks.tasks.map(task => 
              task.id === 3 ? { ...task, status: 'approved' } : task
            )
          };
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(updatedTasks)
          });
        }
      }
    });
    
    // Find and click approve button on completed task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    await completedTask.locator('.btn-primary:has-text("Approve")').click();
    
    // Wait for request to complete
    await page.waitForLoadState('networkidle');
  });

  test('should send correct data when approving task', async ({ page }) => {
    let requestData = null;
    
    // Mock and capture request data
    await page.route('**/api/tasks/3/approve', async route => {
      requestData = await route.request().postDataJSON();
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true })
      });
    });
    
    // Approve task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    await completedTask.locator('.btn-primary:has-text("Approve")').click();
    
    // Wait for request to complete
    await page.waitForLoadState('networkidle');
    
    // Verify request data includes adminId
    expect(requestData).toBeTruthy();
    expect(requestData).toHaveProperty('adminId');
  });

  test('should not show approve button for pending tasks', async ({ page }) => {
    // Find a pending task
    const pendingTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    
    // Check that approve button is not visible
    const approveButton = pendingTask.locator('.btn-primary:has-text("Approve")');
    await expect(approveButton).not.toBeVisible();
  });
});

test.describe('Task Actions - Regular User Limitations', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session as regular user
    await setupAuthenticatedSession(page, 'user');
    await page.goto('/');
    await page.waitForSelector('.task-card');
  });

  test('should not show approve button for regular users', async ({ page }) => {
    // Find a completed task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    
    // Check that approve button is not visible for regular user
    const approveButton = completedTask.locator('.btn-primary:has-text("Approve")');
    await expect(approveButton).not.toBeVisible();
  });

  test('should show complete button for pending tasks', async ({ page }) => {
    // Find a pending task
    const pendingTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    
    // Check that complete button is visible
    const completeButton = pendingTask.locator('.btn-success:has-text("Complete")');
    await expect(completeButton).toBeVisible();
  });
});
