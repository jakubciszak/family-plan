const { test, expect } = require('./app');
const { template } = require('./fake-api');

const goal = (over = {}) => ({ id: 'g1', name: 'Rower', target: 3000, saved: 1500, missing: 1500, percent: 50, wantedBy: null, weeksLeft: null, weeksAtThisPace: 3, perWeekNeeded: null, reached: false, ...over });

test('konto pokazuje punkty i pozwala zmienić hasło z walidacją', async ({ app, page }) => {
  app.world.week.total = 37;
  let body;
  await page.route('**/api/auth/change-password', route => { body = route.request().postDataJSON(); return route.fulfill({ json: {} }); });
  await app.signIn();
  await app.goTo('Moje Konto');
  await expect(page.getByText('Punkty w tym tygodniu', { exact: true })).toBeVisible();
  await expect(page.getByText('37', { exact: true }).last()).toBeVisible();
  await app.field('Obecne hasło').fill('sekret123');
  await app.field('Nowe hasło').fill('nowehaslo123');
  await app.field('Powtórz nowe hasło').fill('innehaslo123');
  await page.getByRole('button', { name: 'Zmień hasło', exact: true }).click();
  await expect(page.getByText('Hasła nie są takie same.')).toBeVisible();
  expect(body).toBeUndefined();
  await app.field('Powtórz nowe hasło').fill('nowehaslo123');
  await page.getByRole('button', { name: 'Zmień hasło', exact: true }).click();
  await expect(page.getByText('Hasło zostało zmienione.')).toBeVisible();
  expect(body).toEqual({ currentPassword: 'sekret123', newPassword: 'nowehaslo123' });
  await expect(app.field('Obecne hasło')).toHaveValue('');
});

test('własny kolor i podgląd konfetti działają w wyglądzie', async ({ app, page }) => {
  await app.signIn();
  await app.goTo('Mój wygląd');
  await app.field('Własny kolor').fill('#123abc');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
  await expect.poll(() => app.world.personalisation.theme).toBe('#123abc');
  await page.getByRole('button', { name: 'Pokaż jak to wygląda', exact: true }).click();
  await expect(page.getByTestId('success-celebration')).toBeAttached();
});

test('zaproszenia zespołu pokazują link i kopiują go do schowka', async ({ app, page, context }) => {
  const invitationUrl = 'https://family.example/?invite=abc';
  app.world.teamInvitations[app.world.teams[0].id] = [{ id: 'inv1', email: 'nowy@example.com', role: 'member', accountExists: false, invitationUrl }];
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await app.signIn();
  await app.goTo('Zespoły');
  const invitation = page.getByTestId('pending-invitation-inv1');
  await expect(invitation.getByLabel('Link zaproszenia')).toHaveValue(invitationUrl);
  await invitation.getByRole('button', { name: 'Kopiuj link', exact: true }).click();
  await expect(page.getByText('Skopiowano', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(invitationUrl);
});

test('cel używa salda saved z API i wysyła pełną kwotę zakupu', async ({ app, page }) => {
  app.world.teams[0].role = 'member';
  app.world.goals.goals = [goal({ saved: 3000, missing: 0, percent: 100, reached: true })];
  app.world.wallet.putAside = 3000;
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await expect(page.getByTestId('goal-progress-g1')).toContainText('30,00');
  await page.getByRole('button', { name: 'Kupuję', exact: true }).click();
  await expect.poll(() => app.sent('POST', '/api/allowance/goals/g1/spend').length).toBe(1);
  expect(app.lastSent('POST', '/api/allowance/goals/g1/spend').body).toEqual({ amount: 3000, description: 'Rower' });
});

test('dochód zachowuje wybraną datę a cel termin', async ({ app, page }) => {
  app.world.teams[0].role = 'member';
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await page.getByRole('button', { name: 'Dopisz dochód' }).click();
  await app.field('Kwota').fill('12,50');
  await app.field('Skąd te pieniądze').fill('Prezent');
  await app.field('Data').fill('2026-01-02');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).last().click();
  await expect.poll(() => app.sent('POST', '/api/allowance/income').length).toBe(1);
  expect(app.lastSent('POST', '/api/allowance/income').body.on).toBe('2026-01-02');
  await page.getByRole('button', { name: 'Dodaj cel' }).click();
  await app.field('Na co zbierasz').fill('Rower');
  await app.field('Ile potrzebujesz').fill('50');
  await app.field('Na kiedy').fill('2026-12-31');
  await page.getByRole('button', { name: 'Zapisz', exact: true }).last().click();
  await expect.poll(() => app.sent('POST', '/api/allowance/goals').length).toBe(1);
  expect(app.lastSent('POST', '/api/allowance/goals').body.wantedBy).toBe('2026-12-31');
});

test('rodzic widzi tylko wypłaty domownika i wypłaca wolną kwotę', async ({ app, page }) => {
  app.world.wallet.pending = 5000;
  app.world.wallet.awaitingConfirmation = [{ id: 'p1', amount: 1200, note: 'Poprzednia wypłata' }];
  app.world.goals.goals = [goal()];
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await expect(page.getByText('Wypłacone', { exact: true })).toBeVisible();
  await expect(page.getByTestId('goal-progress-g1')).toHaveCount(0);
  await expect(page.getByText('Do wydania', { exact: true })).toHaveCount(0);
  expect(app.sent('GET', '/api/allowance/goals')).toHaveLength(0);
  expect(app.sent('GET', '/api/allowance/ledger')).toHaveLength(0);
  await expect.poll(() => app.sent('GET', '/api/allowance/wallet').some(call => call.query.userId === app.world.users[1].id)).toBeTruthy();
  await page.getByRole('button', { name: 'Wypłać wszystko', exact: true }).click();
  await expect.poll(() => app.sent('POST', '/api/allowance/payouts').length).toBe(1);
  expect(app.lastSent('POST', '/api/allowance/payouts').body).toMatchObject({ userId: app.world.users[1].id, amount: 3800 });
});

test('zamknięty tydzień wyświetla historyczne rozliczenie', async ({ app, page }) => {
  app.world.teams[0].role = 'member';
  app.world.allowanceWeek.expected.lines = [{ pointsAccount: 'tasks', points: 100, minimumPoints: 0, amount: 9900 }];
  app.world.allowanceWeek.closure = { closedAt: '2026-01-12', total: 1200, lines: [{ pointsAccount: 'tasks', points: 12, minimumPoints: 5, amount: 1200 }] };
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await expect(page.getByTestId('allowance-week-summary')).toContainText('12,00');
  await expect(page.getByTestId('allowance-week-summary')).not.toContainText('99,00');
});

test('prywatna rodzina bez dzieci otwiera własny portfel', async ({ app, page }) => {
  app.world.members[app.world.teams[0].id] = [app.world.members[app.world.teams[0].id][0]];
  await app.signIn();
  await app.goTo('Kieszonkowe');
  await expect(page.getByRole('button', { name: 'Dopisz dochód' })).toBeVisible();
  await expect(page.getByText('Kogo rozliczyć')).toHaveCount(0);
});

test('język jest dostępny przed logowaniem i zapamiętywany', async ({ app, page }) => {
  await app.open();
  await page.getByRole('button', { name: 'EN', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Login', exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByRole('button', { name: 'Login', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Register', exact: true }).click();
  await expect(page.getByRole('button', { name: 'PL', exact: true })).toBeVisible();
});

test('sesja bez zapamiętania nie zapisuje tokenów na dysku', async ({ app, page }) => {
  await app.open();
  await app.field('Email').fill(app.world.me.email);
  await app.field('Hasło').fill('sekret123');
  await page.getByRole('switch', { name: 'Nie wylogowuj mnie' }).click();
  await page.getByRole('button', { name: 'Zaloguj', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Zadania', exact: true })).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('familyplan.refreshToken'))).toBeNull();
  await page.reload();
  await expect(app.field('Email')).toBeVisible();
});

test('wybór widocznych kalendarzy zachowuje się po restarcie', async ({ app, page }) => {
  app.world.personalisation.home = ['week'];
  await app.signIn();
  const weeks = page.getByTestId('member-weeks');
  await weeks.getByRole('button', { name: app.world.users[1].name, exact: true }).click();
  await expect(weeks.getByTestId('points-calendar')).toHaveCount(0);
  await page.reload();
  await expect(weeks.getByRole('button', { name: app.world.users[1].name, exact: true })).toBeEnabled();
  await expect(weeks.getByTestId('points-calendar')).toHaveCount(0);
});

for (const book of [false, true]) {
  test(`rodzic ${book ? 'zalicza' : 'przydziela'} zadanie dziecku`, async ({ app, page }) => {
    app.world.templates = [template({ id: 't1', name: 'Zmywanie' })];
    let body;
    await page.route(`**/api/task-templates/t1/${book ? 'book' : 'assign'}`, route => { body = route.request().postDataJSON(); return route.fulfill({ json: {} }); });
    await app.signIn();
    await page.getByRole('button', { name: 'Zadania do wzięcia', exact: true }).click();
    await page.getByRole('button', { name: 'Przydziel', exact: true }).click();
    await page.getByRole('radio', { name: app.world.users[1].name }).click();
    await page.getByRole('button', { name: book ? 'Dopisz jako zrobione' : 'Przypisz do zrobienia', exact: true }).click();
    await expect.poll(() => body?.userId).toBe(app.world.users[1].id);
    if (book) expect(body.doneOn).toBe(new Date().toLocaleDateString('sv'));
  });
}
