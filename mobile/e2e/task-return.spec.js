const { test, expect } = require('./app');
const { CHILD, execution } = require('./fake-api');

test('cofnięcie z głównej listy zachowuje powód po błędzie i usuwa zadanie po zapisie', async ({ app, page }) => {
  app.world.executions = [execution({ id: 'returned-task', name: 'Zmywanie', status: 'completed', assignedUserId: CHILD.id })];
  app.world.failing.add('POST /api/task-executions/returned-task/reject');
  await app.signIn();
  const card = app.onScreen('approval-Zmywanie');
  await expect(card.getByRole('button', { name: 'Zatwierdź' })).toBeVisible();
  await card.getByRole('button', { name: 'Cofnij' }).click();
  const confirm = page.getByRole('button', { name: 'Cofnij', exact: true }).last();
  await expect(confirm).toBeDisabled();
  await app.field('Powód cofnięcia').fill('   ');
  await expect(confirm).toBeDisabled();
  await app.field('Powód cofnięcia').fill('  Umyj też kubki.  ');
  await confirm.click();
  await expect(page.getByText('Nie udało się cofnąć zadania. Spróbuj ponownie.')).toBeVisible();
  await expect(app.field('Powód cofnięcia')).toHaveValue('  Umyj też kubki.  ');
  app.world.failing.delete('POST /api/task-executions/returned-task/reject');
  await confirm.click();
  await expect(card).toHaveCount(0);
  expect(app.lastSent('POST', '/api/task-executions/returned-task/reject').body).toEqual({ reason: 'Umyj też kubki.' });
});

test('anulowanie cofnięcia niczego nie zmienia', async ({ app, page }) => {
  app.world.executions = [execution({ id: 'returned-task', name: 'Zmywanie', status: 'completed', assignedUserId: CHILD.id })];
  await app.signIn();
  const card = app.onScreen('approval-Zmywanie');
  await card.getByRole('button', { name: 'Cofnij' }).click();
  await app.field('Powód cofnięcia').fill('Nie wysyłaj');
  await page.getByRole('button', { name: 'Anuluj', exact: true }).click();
  await expect(card).toBeVisible();
  expect(app.sent('POST', '/api/task-executions/returned-task/reject')).toHaveLength(0);
});
