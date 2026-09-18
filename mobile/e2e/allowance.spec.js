const { test, expect } = require('./app');

const openWallet = async (app) => {
  app.world.teams[0].role = 'member';
  await app.signIn();
  await app.goTo('Kieszonkowe');
};

test('dochód i wydatek zapisują kwoty w groszach', async ({ app, page }) => {
  await openWallet(app);
  await page.getByRole('button', { name: 'Dopisz dochód' }).click();
  await app.field('Kwota').fill('12,50');
  await app.field('Skąd te pieniądze').fill('Prezent');
  await page.getByRole('button', { name: 'Zapisz' }).filter({ visible: true }).last().click();
  await expect.poll(() => app.world.wallet.available).toBe(1250);
  await page.getByRole('button', { name: 'Dopisz wydatek' }).click();
  await app.field('Kwota').fill('2,50');
  await app.field('Na co poszło').fill('Lody');
  await page.getByRole('button', { name: 'Zapisz' }).filter({ visible: true }).last().click();
  await expect.poll(() => app.world.wallet.available).toBe(1000);
  expect(app.lastSent('POST', '/api/allowance/expenses').body).toMatchObject({ amount: 250, description: 'Lody' });
});

test('cel można utworzyć i odłożyć na niego pieniądze', async ({ app, page }) => {
  app.world.wallet.available = 2000;
  await openWallet(app);
  await page.getByRole('button', { name: 'Dodaj cel' }).click();
  await app.field('Na co zbierasz').fill('Książka');
  await app.field('Ile potrzebujesz').fill('30');
  await page.getByRole('button', { name: 'Zapisz' }).filter({ visible: true }).last().click();
  await expect(page.getByText('Książka', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Odłóż', exact: true }).click();
  await app.field('Kwota').fill('10');
  await page.getByRole('button', { name: 'Zapisz' }).filter({ visible: true }).last().click();
  await expect.poll(() => app.world.wallet.putAside).toBe(1000);
});

test('domownik potwierdza wypłatę', async ({ app, page }) => {
  app.world.wallet.awaitingConfirmation = [{ id: 'p1', amount: 1500, note: null, status: 'offered', offeredAt: '2026-01-01' }];
  await openWallet(app);
  await page.getByRole('button', { name: 'Odebrałem' }).click();
  await expect.poll(() => app.sent('POST', '/api/allowance/payouts/p1/confirm').length).toBe(1);
  await expect(page.getByRole('button', { name: 'Odebrałem' })).toHaveCount(0);
});

test('administrator zamyka zakończony tydzień i może cofnąć rozliczenie', async ({ app, page }) => {
  app.world.allowanceWeek.isOver = true;
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await page.getByRole('button', { name: 'Zamknij tydzień' }).click();
  await expect(page.getByRole('button', { name: 'Otwórz ponownie' })).toBeVisible();
  await page.getByRole('button', { name: 'Otwórz ponownie' }).click();
  await expect(page.getByRole('button', { name: 'Zamknij tydzień' })).toBeVisible();
});

test('administrator zleca wypłatę z notatką', async ({ app, page }) => {
  app.world.wallet.pending = 1500;
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await page.getByRole('button', { name: 'Wypłać część' }).click();
  await app.field('Kwota').fill('15');
  await app.field('Notatka (opcjonalnie)').fill('Gotówka');
  await page.getByRole('button', { name: 'Zapisz' }).filter({ visible: true }).last().click();
  await expect.poll(() => app.sent('POST', '/api/allowance/payouts').length).toBe(1);
  expect(app.lastSent('POST', '/api/allowance/payouts').body).toMatchObject({ amount: 1500, note: 'Gotówka' });
});
