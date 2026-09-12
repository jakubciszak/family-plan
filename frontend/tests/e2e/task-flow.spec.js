const { test, expect } = require('@playwright/test');
const { mockApiResponses, setupAuthenticatedSession } = require('./fixtures');

const available = (page) => page.getByTestId('available-tasks');
const mine = (page) => page.getByTestId('my-tasks');
const approvals = (page) => page.getByTestId('approval-queue');

test.describe('Available tasks', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
  });

  test('shows the task types of the team with points and run limit', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    const card = available(page).locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    await expect(card.locator('.task-points')).toContainText('10');
    await expect(card.locator('.task-frequency')).toContainText('2');
    await expect(card.locator('.task-remaining')).toContainText('2');
  });

  test('an unlimited type reports no remaining count', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    const card = available(page).locator('.task-card').filter({ hasText: 'Take out trash' });
    await expect(card.locator('.task-remaining')).toHaveCount(0);
  });

  test('shows an empty hint when nothing is available', async ({ page }) => {
    await page.route('**/api/task-templates', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.emptyTaskTypes)
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    await expect(available(page)).toContainText('No tasks available');
  });

  test('taking a type calls the take endpoint', async ({ page }) => {
    let takenId = null;
    await page.route('**/api/task-templates/*/take', async route => {
      takenId = route.request().url().split('/task-templates/')[1].split('/take')[0];
      await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ id: 'exec-9' }) });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    await available(page).locator('.task-card').filter({ hasText: 'Clean the kitchen' })
      .getByRole('button', { name: /take this task/i }).click();

    await expect.poll(() => takenId).toBe('type-1');
  });

  test('a retired type is not offered', async ({ page }) => {
    await page.route('**/api/task-templates', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          templates: [{ ...mockApiResponses.sampleTaskTypes.templates[0], isActive: false }]
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    await expect(available(page)).toContainText('No tasks available');
  });

  test('an exhausted type is not offered', async ({ page }) => {
    await page.route('**/api/task-templates', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          templates: [{ ...mockApiResponses.sampleTaskTypes.templates[0], remaining: 0 }]
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    await expect(available(page)).toContainText('No tasks available');
  });
});

test.describe('My tasks', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    await page.route('**/api/task-executions/mine', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.myExecutions)
      });
    });
  });

  test('lists what I have taken with both actions', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    const card = mine(page).locator('.task-card').filter({ hasText: 'Take out trash' });
    await expect(card.getByRole('button', { name: /complete/i })).toBeVisible();
    await expect(card.getByRole('button', { name: /give it back/i })).toBeVisible();
  });

  test('completing calls the complete endpoint', async ({ page }) => {
    let completed = null;
    await page.route('**/api/task-executions/*/complete', async route => {
      completed = route.request().url().split('/task-executions/')[1].split('/complete')[0];
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ id: 'exec-1' }) });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');
    await mine(page).locator('.task-card').filter({ hasText: 'Take out trash' })
      .getByRole('button', { name: /complete/i }).click();

    await expect.poll(() => completed).toBe('exec-1');
  });

  test('giving a task back calls the abandon endpoint', async ({ page }) => {
    let abandoned = null;
    await page.route('**/api/task-executions/*/abandon', async route => {
      abandoned = route.request().url().split('/task-executions/')[1].split('/abandon')[0];
      await route.fulfill({ status: 204, body: '' });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');
    await mine(page).locator('.task-card').filter({ hasText: 'Take out trash' })
      .getByRole('button', { name: /give it back/i }).click();

    await expect.poll(() => abandoned).toBe('exec-1');
  });

  test('a finished task waits for approval without further actions', async ({ page }) => {
    await page.route('**/api/task-executions/mine', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          executions: [{ ...mockApiResponses.myExecutions.executions[0], status: 'completed' }]
        })
      });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    const card = mine(page).locator('.task-card').filter({ hasText: 'Take out trash' });
    await expect(card).toContainText('Waiting for approval');
    await expect(card.getByRole('button')).toHaveCount(0);
  });
});

test.describe('Approval queue', () => {
  test('a plain member has no approval section', async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    await expect(approvals(page)).toHaveCount(0);
  });

  test('a team admin approves a finished task of a team mate', async ({ page }) => {
    await setupAuthenticatedSession(page, 'admin');

    let approved = null;
    await page.route('**/api/task-executions/*/approve', async route => {
      approved = route.request().url().split('/task-executions/')[1].split('/approve')[0];
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ id: 'exec-2' }) });
    });

    await page.goto('/');
    await page.waitForSelector('.task-list-container');

    const card = approvals(page).locator('.task-card').filter({ hasText: 'Clean the kitchen' });
    await expect(card).toContainText('Test User');
    await card.getByRole('button', { name: /approve/i }).click();

    await expect.poll(() => approved).toBe('exec-2');
  });
});
