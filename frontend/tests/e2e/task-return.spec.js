const { test, expect } = require('@playwright/test');
const { mockApiResponses, setupAuthenticatedSession } = require('./fixtures');

test('returning from the approval queue requires a reason and preserves it on failure', async ({ page }) => {
  await setupAuthenticatedSession(page, 'admin');
  let rejected = false;
  let fail = true;
  let sent;
  await page.route('**/api/task-executions/awaiting-approval', route => route.fulfill({ json: { executions: rejected ? [] : [{ id: 'return-1', name: 'Wash dishes', status: 'completed', points: 10, assignedUserName: 'Child' }] } }));
  await page.route('**/api/task-executions/return-1/reject', route => {
    sent = route.request().postDataJSON();
    rejected = !fail;
    return route.fulfill({ status: fail ? 500 : 200, json: fail ? { error: 'Try again' } : { status: 'rejected' } });
  });
  await page.goto('/');
  const queue = page.getByTestId('approval-queue');
  await queue.getByRole('button', { name: 'Return', exact: true }).click();
  const dialog = page.getByRole('dialog');
  const confirm = dialog.getByRole('button', { name: 'Return', exact: true });
  await expect(confirm).toBeDisabled();
  await dialog.getByLabel('Reason for returning').fill('  Wash the cups too.  ');
  await confirm.click();
  await expect(dialog.getByRole('alert')).toBeVisible();
  await expect(dialog.getByLabel('Reason for returning')).toHaveValue('  Wash the cups too.  ');
  fail = false;
  await confirm.click();
  await expect(dialog).toHaveCount(0);
  await expect(queue).not.toContainText('Wash dishes');
  expect(sent).toEqual({ reason: 'Wash the cups too.' });
});
