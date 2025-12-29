const { test, expect } = require('@playwright/test');
const { 
  mockApiResponses,
  setupAuthenticatedSession
} = require('./fixtures');

test.describe('User Points Display', () => {
  test.beforeEach(async ({ page }) => {
    // Setup authenticated session as regular user
    await setupAuthenticatedSession(page, 'user');
    
    // Mock points API response
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          userId: '550e8400-e29b-41d4-a716-446655440000',
          balance: 150
        })
      });
    });
  });

  test('should display user points in header', async ({ page }) => {
    await page.goto('/');
    
    // Wait for app to load
    await page.waitForSelector('.app-header');
    
    // Check for points display
    const pointsDisplay = page.locator('.user-points');
    await expect(pointsDisplay).toBeVisible();
    await expect(pointsDisplay).toContainText('150 points');
  });

  test('should display zero points for new user', async ({ page }) => {
    // Override mock to return zero balance
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          userId: '550e8400-e29b-41d4-a716-446655440000',
          balance: 0
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.app-header');
    
    const pointsDisplay = page.locator('.user-points');
    await expect(pointsDisplay).toContainText('0 points');
  });
});

test.describe('Task Assignment Display', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    
    // Mock points API
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
  });

  test('should show assignment info for assigned task', async ({ page }) => {
    // Mock tasks with assignment
    await page.route('**/api/tasks', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          tasks: [
            {
              id: '550e8400-e29b-41d4-a716-446655440001',
              name: 'Assigned Task',
              description: 'This task is assigned',
              points: 50,
              frequency: 'daily',
              status: 'pending',
              assignedUserId: '550e8400-e29b-41d4-a716-446655440000',
              assignedUserName: 'Test User',
              createdAt: '2024-01-15T10:30:00+00:00'
            }
          ]
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Check for assignment display
    const assignmentInfo = page.locator('.task-assignment');
    await expect(assignmentInfo).toBeVisible();
    await expect(assignmentInfo).toContainText('Assigned to:');
    await expect(assignmentInfo).toContainText('Test User');
  });

  test('should not show assignment info for unassigned task', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Find an unassigned task
    const unassignedTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    
    // Assignment info should not be visible
    const assignmentInfo = unassignedTask.locator('.task-assignment');
    await expect(assignmentInfo).not.toBeVisible();
  });

  test('should show assign button for unassigned tasks', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Find an unassigned task
    const unassignedTask = page.locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    
    // Check for assign button
    const assignButton = unassignedTask.locator('button').filter({ hasText: 'Assign to Me' });
    await expect(assignButton).toBeVisible();
  });

  test('should not show assign button for already assigned tasks', async ({ page }) => {
    // Mock tasks with assignment
    await page.route('**/api/tasks', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          tasks: [
            {
              id: '550e8400-e29b-41d4-a716-446655440001',
              name: 'Assigned Task',
              description: 'This task is assigned',
              points: 50,
              frequency: 'daily',
              status: 'pending',
              assignedUserId: '550e8400-e29b-41d4-a716-446655440999',
              assignedUserName: 'Another User',
              createdAt: '2024-01-15T10:30:00+00:00'
            }
          ]
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Assign button should not be visible
    const assignButton = page.locator('button').filter({ hasText: 'Assign to Me' });
    await expect(assignButton).not.toBeVisible();
  });

  test('should allow assigning task to current user', async ({ page }) => {
    let assignCalled = false;

    // Mock assign endpoint
    await page.route('**/api/tasks/*/assign', async route => {
      assignCalled = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          id: '550e8400-e29b-41d4-a716-446655440001',
          name: 'Clean the kitchen',
          description: 'Wash dishes',
          points: 10,
          frequency: 'daily',
          status: 'pending',
          assignedUserId: '550e8400-e29b-41d4-a716-446655440000',
          assignedUserName: 'Test User',
          createdAt: '2024-01-15T10:30:00+00:00'
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Click assign button
    const assignButton = page.locator('button').filter({ hasText: 'Assign to Me' }).first();
    await assignButton.click();
    
    // Wait a bit for the API call
    await page.waitForTimeout(500);
    
    // Verify the API was called
    expect(assignCalled).toBeTruthy();
  });
});

test.describe('Task Actions - Permission Based', () => {
  test('should show complete button only for assigned tasks', async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    
    // Mock points API
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ userId: 1, balance: 100 })
      });
    });

    // Mock tasks - one assigned to current user, one to another user
    await page.route('**/api/tasks', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          tasks: [
            {
              id: 1,
              name: 'My Task',
              description: 'Assigned to me',
              points: 50,
              frequency: 'daily',
              status: 'pending',
              assignedUserId: 1,
              assignedUserName: 'Test User',
              createdAt: '2024-01-15T10:30:00+00:00'
            },
            {
              id: 2,
              name: 'Other Task',
              description: 'Assigned to someone else',
              points: 30,
              frequency: 'daily',
              status: 'pending',
              assignedUserId: 999,
              assignedUserName: 'Other User',
              createdAt: '2024-01-15T10:30:00+00:00'
            }
          ]
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // My task should have complete button
    const myTask = page.locator('.task-card').filter({ hasText: 'My Task' });
    await expect(myTask.locator('button').filter({ hasText: 'Complete' })).toBeVisible();
    
    // Other task should not have complete button
    const otherTask = page.locator('.task-card').filter({ hasText: 'Other Task' });
    await expect(otherTask.locator('button').filter({ hasText: 'Complete' })).not.toBeVisible();
  });

  test('should show approve button only for admin on completed tasks', async ({ page }) => {
    await setupAuthenticatedSession(page, 'admin');
    
    // Mock points API
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ userId: '550e8400-e29b-41d4-a716-446655440100', balance: 200 })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Find a completed task
    const completedTask = page.locator('.task-card').filter({ hasText: 'Vacuum living room' });
    
    // Check for approve button
    await expect(completedTask.locator('button').filter({ hasText: 'Approve' })).toBeVisible();
  });

  test('should not show approve button for regular user', async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    
    // Mock points API
    await page.route('**/api/users/*/points', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ userId: '550e8400-e29b-41d4-a716-446655440000', balance: 100 })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-card');
    
    // Approve button should not be visible for regular user
    const approveButton = page.locator('button').filter({ hasText: 'Approve' });
    await expect(approveButton).not.toBeVisible();
  });
});
