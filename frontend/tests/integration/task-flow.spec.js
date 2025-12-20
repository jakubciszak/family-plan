const { test, expect } = require('@playwright/test');
const { login, getAdminCredentials, TIMEOUTS } = require('./helpers');

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
    // Check for task list container with explicit wait
    await page.waitForSelector('.task-list-container', { state: 'visible', timeout: TIMEOUTS.LONG });
    await expect(page.locator('.task-list-container')).toBeVisible({ timeout: TIMEOUTS.MEDIUM });
    await expect(page.locator('h2')).toContainText('Tasks', { timeout: TIMEOUTS.MEDIUM });
    await expect(page.locator('.task-list-header button')).toContainText('Create Task', { timeout: TIMEOUTS.MEDIUM });
  });

  test('should create a new task', async ({ page }) => {
    // Wait for task list to be fully loaded
    await page.waitForSelector('.task-list-header button', { state: 'visible', timeout: TIMEOUTS.LONG });
    
    // Click create task button
    await page.click('.task-list-header button:has-text("Create Task")');
    
    // Check that form is visible
    await page.waitForSelector('.task-create-form', { state: 'visible', timeout: TIMEOUTS.MEDIUM });
    await expect(page.locator('.task-create-form')).toBeVisible({ timeout: TIMEOUTS.MEDIUM });
    
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
    await expect(page.locator('.task-create-form')).not.toBeVisible({ timeout: TIMEOUTS.MEDIUM });
    
    // Verify new task appears in list
    await expect(page.locator('.task-card').filter({ hasText: taskName })).toBeVisible({ timeout: TIMEOUTS.LONG });
  });

  test('should complete and approve task workflow', async ({ page }) => {
    // Wait for task list to be fully loaded
    await page.waitForSelector('.task-list-header button', { state: 'visible', timeout: TIMEOUTS.LONG });
    
    // First, create a task
    await page.click('.task-list-header button:has-text("Create Task")');
    
    const timestamp = Date.now();
    const taskName = `Workflow Test ${timestamp}`;
    
    await page.waitForSelector('.task-create-form', { state: 'visible', timeout: TIMEOUTS.MEDIUM });
    await page.fill('input#name', taskName);
    await page.fill('textarea#description', 'Task for complete workflow test');
    await page.fill('input#points', '10');
    await page.selectOption('select#frequency', 'once');
    await page.click('.task-create-form button[type="submit"]');
    
    // Wait for task to appear using filter approach for robust text matching
    const taskCard = page.locator('.task-card').filter({ hasText: taskName });
    await expect(taskCard).toBeVisible({ timeout: TIMEOUTS.LONG });
    
    // Complete the task
    await taskCard.locator('.btn-success:has-text("Complete")').click();
    
    // Wait for status to update to completed
    await expect(taskCard.locator('.status-completed')).toBeVisible({ timeout: TIMEOUTS.LONG });
    
    // As admin, approve the task
    await taskCard.locator('.btn-primary:has-text("Approve")').click();
    
    // Wait for status to update to approved
    await expect(taskCard.locator('.status-approved')).toBeVisible({ timeout: TIMEOUTS.LONG });
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
    // Wait for user info to be visible
    await page.waitForSelector('.user-info button', { state: 'visible', timeout: TIMEOUTS.MEDIUM });
    
    // Click logout button
    await page.click('.user-info button:has-text("Logout")');
    
    // Wait for redirect to login page
    await page.waitForSelector('h2:has-text("Login")', { state: 'visible', timeout: TIMEOUTS.LONG });
    
    // Verify we're on login page
    await expect(page.locator('h2')).toContainText('Login', { timeout: TIMEOUTS.MEDIUM });
    await expect(page.locator('input#email')).toBeVisible({ timeout: TIMEOUTS.MEDIUM });
  });
});

test.describe('Task List Display Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = getAdminCredentials();
    await login(page, email, password);
  });

  test('should show admin buttons for completed tasks', async ({ page }) => {
    // Wait for task list to load
    await page.waitForSelector('.task-list-container', { state: 'visible', timeout: TIMEOUTS.LONG });
    
    // Check if there are any completed tasks
    const completedTasks = page.locator('.task-card').filter({ has: page.locator('.status-completed') });
    const count = await completedTasks.count();
    
    if (count > 0) {
      // Verify admin can see approve button
      await expect(completedTasks.first().locator('.btn-primary:has-text("Approve")')).toBeVisible({ timeout: TIMEOUTS.MEDIUM });
    }
  });

  test('should filter tasks by status', async ({ page }) => {
    // Get all task cards
    const taskCards = page.locator('.task-card');
    const count = await taskCards.count();
    
    // Each task should have a status (if any tasks exist)
    if (count > 0) {
      for (let i = 0; i < count; i++) {
        await expect(taskCards.nth(i).locator('.task-status')).toBeVisible();
      }
    }
  });
});
