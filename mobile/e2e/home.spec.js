const { test, expect } = require('./app');

test('układ strony głównej ukrywa wyłączone sekcje', async ({ app, page }) => {
  app.world.personalisation.home = ['standings'];
  app.world.teams[0].role = 'member';
  app.world.standings = [{ userId: 'child', name: 'Bartek', total: 25 }];
  await app.signIn();
  await expect(app.onScreen('home-standings')).toContainText('Bartek');
  await expect(app.onScreen('home-standings')).toContainText('25');
  await expect(page.getByText('Moje zadania')).toHaveCount(0);
  await expect(app.onScreen('points-calendar')).toHaveCount(0);
});

test('kalendarz pobiera wybrany dzień i wcześniejszy tydzień', async ({ app, page }) => {
  app.world.personalisation.home = ['week'];
  app.world.teams[0].role = 'member';
  await app.signIn();
  const calendar = app.onScreen('points-calendar');
  await calendar.getByRole('button', { name: '2026-01-05', exact: true }).click();
  await expect(page.getByText('Tego dnia nic nie zostało zaliczone.')).toBeVisible();
  await page.getByRole('button', { name: 'Zamknij', exact: true }).last().click();
  await calendar.getByRole('button', { name: 'Poprzedni tydzień' }).click();
  await expect.poll(() => app.sent('GET', '/api/points/week').length).toBeGreaterThan(1);
});

test('własny portfel pozostaje dostępny administratorowi prywatnej rodziny', async ({ app, page }) => {
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await page.getByRole('button', { name: 'Mój portfel' }).click();
  await expect.poll(() => app.sent('GET', '/api/allowance/wallet').length).toBeGreaterThan(0);
  await expect(page.getByText('Dostępne', { exact: true })).toBeVisible();
});

test('rozliczenie pobiera poprzedni tydzień', async ({ app, page }) => {
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await page.getByRole('button', { name: 'Poprzedni tydzień' }).click();
  await expect.poll(() => app.sent('GET', '/api/allowance/weeks').length).toBeGreaterThan(1);
});

test('korekta zatwierdzonego zadania zapisuje datę i potwierdzone usunięcie', async ({ app, page }) => {
  const today = new Date().toLocaleDateString('sv');
  app.world.week.days = [{ date: today, points: 5, bonus: 0, isToday: true, inStreak: false }];
  let removed = false;
  let moved = null;
  await page.route('**/api/points/day?**', (route) => route.fulfill({ json: {
    date: today, closed: false, userId: app.world.users[1].id,
    tasks: removed ? [] : [{ id: 'approved-one', name: 'Zmywanie', points: 5, earnedOn: today }], bonuses: [],
  } }));
  await page.route('**/api/task-executions/approved-one', async (route) => {
    if (route.request().method() === 'DELETE') removed = true;
    else moved = route.request().postDataJSON().doneOn;
    await route.fulfill({ json: {} });
  });
  await app.signIn();
  await page.goto(`/member?id=${app.world.users[1].id}&name=Bartek`);
  await app.onScreen('points-calendar').getByRole('button', { name: today, exact: true }).click();
  await page.getByRole('button', { name: 'Zmień datę', exact: true }).click();
  await app.field('Zmień datę').fill(today);
  await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
  await expect.poll(() => moved).toBe(today);
  await page.getByRole('button', { name: 'Usuń', exact: true }).click();
  await expect(page.getByText('Usunąć wykonanie i cofnąć przyznane punkty?')).toBeVisible();
  expect(removed).toBe(false);
  await page.getByRole('button', { name: 'Usuń', exact: true }).last().click();
  await expect.poll(() => removed).toBe(true);
  await expect(page.getByText('Tego dnia nic nie zostało zaliczone.')).toBeVisible();
});

test('zamknięty tydzień nie udostępnia korekty wykonania', async ({ app, page }) => {
  const today = new Date().toLocaleDateString('sv');
  app.world.week.days = [{ date: today, points: 5, bonus: 0, isToday: true, inStreak: false }];
  await page.route('**/api/points/day?**', (route) => route.fulfill({ json: {
    date: today, closed: true, userId: app.world.users[1].id,
    tasks: [{ id: 'approved-one', name: 'Zmywanie', points: 5, earnedOn: today }], bonuses: [],
  } }));
  await app.signIn();
  await page.goto(`/member?id=${app.world.users[1].id}&name=Bartek`);
  await app.onScreen('points-calendar').getByRole('button', { name: today, exact: true }).click();
  await expect(page.getByText('Zmywanie', { exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Zmień datę', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Usuń', exact: true })).toHaveCount(0);
});
