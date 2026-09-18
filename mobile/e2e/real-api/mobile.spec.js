const { test, expect } = require('@playwright/test');

const api = process.env.MOBILE_API_URL || 'http://127.0.0.1:19080';

test('logowanie, wykonanie zadania i odtworzenie sesji z prawdziwym API', async ({ page, request }) => {
  const email = `mobile-${Date.now()}@example.com`;
  const password = 'MobileTest123!';
  const registered = await request.post(`${api}/api/auth/register`, { data: { name: 'Mobile Test', email, password } });
  expect(registered.ok()).toBeTruthy();
  const login = await request.post(`${api}/api/auth/token`, { data: { email, password } });
  expect(login.ok()).toBeTruthy();
  const issued = await login.json();
  const headers = { Authorization: `Bearer ${issued.token}` };
  const teams = await (await request.get(`${api}/api/teams`, { headers })).json();
  const template = await request.post(`${api}/api/task-templates`, { headers, data: {
    teamId: teams.teams[0].id, name: 'Mobilne zmywanie', points: 5, frequency: 'daily', executionLimit: { type: 'unlimited' },
  } });
  expect(template.ok()).toBeTruthy();

  await page.goto('/');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Zaloguj', exact: true }).click();
  await page.getByRole('button', { name: 'Zadania do wzięcia', exact: true }).click();
  await page.getByTestId('task-Mobilne zmywanie').getByRole('button', { name: 'Weź to zadanie' }).click();
  await page.getByTestId('task-Mobilne zmywanie').filter({ has: page.getByRole('button', { name: 'Ukończ', exact: true }) }).getByRole('button', { name: 'Ukończ', exact: true }).click();
  await expect.poll(async () => {
    const response = await request.get(`${api}/api/task-executions/mine`, { headers });
    return (await response.json()).executions[0]?.status;
  }).toMatch(/completed|approved/);
  await page.reload();
  await expect(page.getByText('Do zatwierdzenia', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('familyplan.refreshToken'))).toBeTruthy();
});

test('cel oszczędnościowy i zmiana hasła działają z prawdziwym API', async ({ page, request }) => {
  const email = `mobile-wallet-${Date.now()}@example.com`;
  const password = 'MobileTest123!';
  expect((await request.post(`${api}/api/auth/register`, { data: { name: 'Wallet Test', email, password } })).ok()).toBeTruthy();
  const issued = await (await request.post(`${api}/api/auth/token`, { data: { email, password } })).json();
  const headers = { Authorization: `Bearer ${issued.token}` };
  await page.goto('/');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Zaloguj', exact: true }).click();
  await page.getByRole('button', { name: 'Kasa', exact: true }).click();
  await page.getByRole('button', { name: 'Dopisz dochód' }).click();
  await page.getByLabel('Kwota', { exact: true }).fill('50');
  await page.getByLabel('Skąd te pieniądze', { exact: true }).fill('Prezent');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).last().click();
  await expect.poll(async () => (await (await request.get(`${api}/api/allowance/wallet`, { headers })).json()).available).toBe(5000);
  await page.getByRole('button', { name: 'Dodaj cel' }).click();
  await page.getByLabel('Na co zbierasz', { exact: true }).fill('Gra');
  await page.getByLabel('Ile potrzebujesz', { exact: true }).fill('30');
  await page.getByLabel('Na kiedy', { exact: true }).fill('2026-12-31');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).last().click();
  await page.getByRole('button', { name: 'Odłóż', exact: true }).click();
  await page.getByLabel('Kwota', { exact: true }).fill('30');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).last().click();
  await expect.poll(async () => (await (await request.get(`${api}/api/allowance/goals`, { headers })).json()).goals[0]?.saved).toBe(3000);
  await expect(page.getByTestId(/^goal-progress-/)).toContainText('30,00');
  await page.getByRole('button', { name: 'Kupuję', exact: true }).click();
  await expect.poll(async () => (await (await request.get(`${api}/api/allowance/wallet`, { headers })).json()).putAside).toBe(0);
  await page.goto('/account');
  await page.getByLabel('Obecne hasło', { exact: true }).fill(password);
  await page.getByLabel('Nowe hasło', { exact: true }).fill('ChangedTest123!');
  await page.getByLabel('Powtórz nowe hasło', { exact: true }).fill('ChangedTest123!');
  await page.getByRole('button', { name: 'Zmień hasło', exact: true }).click();
  await expect(page.getByText('Hasło zostało zmienione.')).toBeVisible();
  expect((await request.post(`${api}/api/auth/token`, { data: { email, password: 'ChangedTest123!' } })).ok()).toBeTruthy();
});
