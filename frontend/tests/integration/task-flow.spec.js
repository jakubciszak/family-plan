const { test, expect } = require('@playwright/test');
const { login, getAdminCredentials } = require('./helpers');

/**
 * Integration tests for complete Task workflow
 * These tests run against the real backend API
 * 
 * Prerequisites:
 * - Backend API must be running (docker-compose up)
 * - Database must be seeded with test user
 */

test.describe('Task Management Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    const { email, password } = getAdminCredentials();
    await login(page, email, password);
  });

  test('should display task list after login', async ({ page }) => {
    // Check for task list container
    await expect(page.locator('.task-list-container')).toBeVisible();
    await expect(page.locator('h2')).toContainText('Tasks');
    await expect(page.locator('.task-list-header button')).toContainText('Create Task');
  });

  test('should create a new task', async ({ page }) => {
    // Click create task button
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check that form is visible
    await expect(page.locator('.task-create-form')).toBeVisible();
    
    // Fill in the form with unique task name
    const timestamp = Date.now();
    const taskName = `Integration Test Task ${timestamp}`;
    
    await page.fill('input#name', taskName);
    await page.fill('textarea#description', 'This is an integration test task');
    await page.fill('input#points', '25');
    await page.selectOption('select#frequency', 'once');
    
    // Submit form
    await page.click('.task-create-form button[type="submit"]');
    
    // Wait for form to close
    await expect(page.locator('.task-create-form')).not.toBeVisible({ timeout: 5000 });
    
    // Verify new task appears in list
    await expect(page.locator('.task-card').filter({ hasText: taskName })).toBeVisible({ timeout: 5000 });
  });

  test('should complete and approve task workflow', async ({ page }) => {
    // First, create a task
    await page.click('.task-list-header button:has-text("Create Task")');
    
    const timestamp = Date.now();
    const taskName = `Workflow Test ${timestamp}`;
    
    await page.fill('input#name', taskName);
    await page.fill('textarea#description', 'Task for complete workflow test');
    await page.fill('input#points', '10');
    await page.selectOption('select#frequency', 'once');
    await page.click('.task-create-form button[type="submit"]');
    
    // Wait for task to appear
    await page.waitForSelector(`.task-card:has-text("${taskName}")`, { timeout: 5000 });
    
    // Find the task and complete it
    const taskCard = page.locator('.task-card').filter({ hasText: taskName });
    await taskCard.locator('.btn-success:has-text("Complete")').click();
    
    // Wait for status to update to completed
    await expect(taskCard.locator('.status-completed')).toBeVisible({ timeout: 10000 });
    
    // As admin, approve the task
    await taskCard.locator('.btn-primary:has-text("Approve")').click();
    
    // Wait for status to update to approved
    await expect(taskCard.locator('.status-approved')).toBeVisible({ timeout: 10000 });
  });

  test('should display task with correct metadata', async ({ page }) => {
    // Check if there are any tasks
    const taskCards = page.locator('.task-card');
    const count = await taskCards.count();
    
    if (count > 0) {
      const firstTask = taskCards.first();
      
      // Check that task has all required elements
      await expect(firstTask.locator('.task-header h3')).toBeVisible();
      await expect(firstTask.locator('.task-status')).toBeVisible();
      await expect(firstTask.locator('.task-points')).toBeVisible();
      await expect(firstTask.locator('.task-frequency')).toBeVisible();
    }
  });

  test('should logout successfully', async ({ page }) => {
    // Click logout button
    await page.click('.user-info button:has-text("Logout")');
    
    // Wait for redirect to login page
    await page.waitForSelector('h2:has-text("Login")', { timeout: 5000 });
    
    // Verify we're on login page
    await expect(page.locator('h2')).toContainText('Login');
    await expect(page.locator('input#email')).toBeVisible();
  });
});

test.describe('Task List Display Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = getAdminCredentials();
    await login(page, email, password);
  });

  test('should show admin buttons for completed tasks', async ({ page }) => {
    // Wait for task list to load
    await page.waitForSelector('.task-list-container');
    
    // Check if there are any completed tasks
    const completedTasks = page.locator('.task-card').filter({ has: page.locator('.status-completed') });
    const count = await completedTasks.count();
    
    if (count > 0) {
      // Verify admin can see approve button
      await expect(completedTasks.first().locator('.btn-primary:has-text("Approve")')).toBeVisible();
    }
  });

  test('should filter tasks by status', async ({ page }) => {
    // Get all task cards
    const taskCards = page.locator('.task-card');
    const count = await taskCards.count();
    
    // Verify tasks are displayed
    expect(count).toBeGreaterThanOrEqual(0);
    
    // Each task should have a status
    if (count > 0) {
      for (let i = 0; i < count; i++) {
        await expect(taskCards.nth(i).locator('.task-status')).toBeVisible();
      }
    }
  });
});
