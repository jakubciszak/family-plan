const { test, expect } = require('./app');

test('login screen greets a signed-out visitor', async ({ app, page }) => {
  await app.open();

  await expect(page.getByText('Family Plan')).toBeVisible();
  await expect(page.getByLabel('Email')).toBeVisible();
});

test('signing in lands on tasks', async ({ app, page }) => {
  await app.signIn();

  await expect(page.getByTestId('approval-queue')).toBeVisible();
});
