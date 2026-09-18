const { test, expect } = require('@playwright/test');
const api = process.env.MOBILE_API_URL || 'http://127.0.0.1:19080';

test('team plan creation, attachment, immutable execution snapshot and task completion use the real API', async ({ page, request }) => {
  const email = `mobile-plans-${Date.now()}@example.com`;
  const password = 'MobileTest123!';
  expect((await request.post(`${api}/api/auth/register`, { data: { name: 'Mobile Plans', email, password } })).ok()).toBeTruthy();
  const issued = await (await request.post(`${api}/api/auth/token`, { data: { email, password } })).json();
  const headers = { Authorization: `Bearer ${issued.token}` };
  const team = (await (await request.get(`${api}/api/teams`, { headers })).json()).teams[0];
  await page.goto('/');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Zaloguj', exact: true }).click();
  await page.getByRole('button', { name: 'Zadania', exact: true }).waitFor();
  await page.goto('/action-plans');
  await page.getByRole('button', { name: 'Nowy plan', exact: true }).click();
  await page.getByLabel('Nazwa planu', { exact: true }).fill('Pakowanie');
  await page.getByRole('button', { name: 'Dla kogo jest ten plan?', exact: true }).click();
  await page.getByRole('button', { name: team.name, exact: true }).click();
  await page.getByLabel('Krok 1', { exact: true }).fill('Spakuj książki');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
  await expect(page.getByTestId('plan-Pakowanie')).toBeVisible();
  const saved = (await (await request.get(`${api}/api/action-plans`, { headers })).json()).plans[0];
  expect(saved.teamId).toBe(team.id);
  const template = await request.post(`${api}/api/task-templates`, { headers, data: {
    teamId: team.id, name: 'Plecak', points: 5, frequency: 'daily', executionLimit: { type: 'unlimited' }, actionPlanId: saved.id,
  } });
  expect(template.ok()).toBeTruthy();
  const templateId = (await template.json()).id;
  expect((await request.post(`${api}/api/task-templates/${templateId}/take`, { headers })).ok()).toBeTruthy();
  expect((await request.put(`${api}/api/action-plans/${saved.id}`, { headers, data: { ...saved, steps: [{ name: 'Zmieniony krok', stages: [] }] } })).ok()).toBeTruthy();
  await page.goto('/');
  await page.getByRole('button', { name: 'Zadania do wzięcia', exact: true }).click();
  await page.getByRole('button', { name: 'Pokaż plan działania', exact: true }).click();
  await page.getByTestId('task-plan-preview').getByRole('button', { name: 'Do dzieła', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Spakuj książki', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Gotowe', exact: true }).click();
  await expect(page.getByTestId('plan-completed')).toContainText('Zadanie jest ukończone');
  const executions = (await (await request.get(`${api}/api/task-executions/mine`, { headers })).json()).executions;
  expect(executions[0].status).toMatch(/completed|approved/);
  await page.getByRole('button', { name: 'Wróć do zadań', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Zadania', exact: true })).toBeVisible();
});
