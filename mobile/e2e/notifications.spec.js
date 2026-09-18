const { test, expect } = require('./app');

const notification = (over = {}) => ({
  id: 'n1',
  subject: 'Zadanie zatwierdzone',
  message: 'Bartek dostał 5 punktów',
  createdAt: '2026-01-02T10:00:00+00:00',
  readAt: null,
  ...over,
});

test.describe('Dymki powiadomień', () => {
  test('nieprzeczytane pokazuje się z tematem i treścią', async ({ app, page }) => {
    app.world.notifications = [notification()];

    await app.signIn();

    const snackbar = page.getByTestId('notification-snackbar');

    await expect(snackbar.getByText('Zadanie zatwierdzone')).toBeVisible();
    await expect(snackbar.getByText('Bartek dostał 5 punktów')).toBeVisible();
  });

  test('zamknięcie oznacza jako przeczytane i pokazuje kolejne', async ({ app, page }) => {
    app.world.notifications = [
      notification({ id: 'n1', subject: 'Pierwsze', message: 'Raz' }),
      notification({ id: 'n2', subject: 'Drugie', message: 'Dwa' }),
    ];

    await app.signIn();
    await expect(page.getByText('Pierwsze')).toBeVisible();

    await page.getByTestId('notification-snackbar').getByRole('button', { name: 'Zamknij' }).click();

    await expect(page.getByText('Drugie')).toBeVisible();
    expect(app.sent('POST', '/api/notifications/n1/read')).toHaveLength(1);
  });

  test('to samo powiadomienie nie wraca', async ({ app, page }) => {
    app.world.notifications = [notification()];

    await app.signIn();
    await page.getByTestId('notification-snackbar').getByRole('button', { name: 'Zamknij' }).click();

    await expect(page.getByTestId('notification-snackbar')).toHaveCount(0);
  });

  test('brak nieprzeczytanych to brak dymka', async ({ app, page }) => {
    app.world.notifications = [notification({ readAt: '2026-01-02T11:00:00+00:00' })];

    await app.signIn();

    await expect(page.getByTestId('notification-snackbar')).toHaveCount(0);
  });
});

test.describe('Powiadomienia o zdarzeniach', () => {
  const open = async (app, page) => {
    app.world.me.role = 'ROLE_ADMIN';
    await app.signIn();
    await app.goTo('Powiadomienia');
  };

  test('zwykły użytkownik nie ma tego w nawigacji', async ({ app, page }) => {
    await app.signIn();

    await expect(page.getByRole('button', { name: 'Powiad.', exact: true })).toHaveCount(0);
  });

  test('niedostępne wprost dla zwykłego użytkownika', async ({ app, page }) => {
    await app.signIn();
    await page.goto('/notification-events');

    await expect(page.getByText('Brak dostępu')).toBeVisible();
  });

  test('macierz listuje zdarzenia i kanały', async ({ app, page }) => {
    await open(app, page);

    await expect(page.getByText('Zadanie zgłoszone do akceptacji')).toBeVisible();
    await expect(page.getByText('E-mail').first()).toBeVisible();
    await expect(page.getByText('SMS').first()).toBeVisible();
    await expect(page.getByText('W aplikacji').first()).toBeVisible();
  });

  test('zdarzenie transakcyjne ma przełączniki zablokowane', async ({ app, page }) => {
    await open(app, page);

    await expect(page.getByText(/zawsze wysyłana mailem/)).toBeVisible();
    await expect(page.getByRole('switch').last()).toBeDisabled();
  });

  test('zapis wysyła tylko zdarzenia konfigurowalne', async ({ app, page }) => {
    await open(app, page);

    await page.getByText('W aplikacji', { exact: true }).first().click();
    await page.getByRole('button', { name: 'Zapisz' }).click();

    await expect(page.getByText('Ustawienia zapisane')).toBeVisible();

    expect(app.lastSent('PUT', '/api/notification-policies/task_completed').body).toEqual({
      channels: ['email', 'in_app'],
    });
    expect(app.sent('PUT', '/api/notification-policies/account_activation')).toHaveLength(0);
  });

  test('błąd zapisu pokazuje baner', async ({ app, page }) => {
    app.world.failing.add('PUT /api/notification-policies/task_completed');

    await open(app, page);
    await page.getByText('W aplikacji', { exact: true }).first().click();
    await page.getByRole('button', { name: 'Zapisz' }).click();

    await expect(page.getByText('Nie udało się zapisać ustawień')).toBeVisible();
  });
});

test.describe('Nawigacja', () => {
  test('pasek trzyma kolejność z personalizacji', async ({ app, page }) => {
    app.world.personalisation.navigation = ['settings', 'tasks', 'teams'];

    await app.signIn();

    const labels = await page.getByRole('button', { name: /Ustawienia|Zadania|Zespoły/ }).allInnerTexts();

    expect(labels.join(' ')).toMatch(/Ustawienia.*Zadania.*Zespoły/s);
  });

  test('powyżej pięciu pozycji reszta chowa się pod Więcej', async ({ app, page }) => {
    await app.signIn();

    await expect(page.getByRole('button', { name: 'Więcej', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Ustawienia', exact: true })).toHaveCount(0);
  });

  test('krótka lista mieści się w całości na pasku', async ({ app, page }) => {
    app.world.personalisation.navigation = ['tasks', 'teams', 'settings'];

    await app.signIn();

    await expect(page.getByRole('button', { name: 'Więcej', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Ustawienia', exact: true })).toBeVisible();
  });

  test('typy zadań i bonusy widzi tylko admin zespołu', async ({ app, page }) => {
    app.world.teams[0].role = 'member';
    app.world.personalisation.navigation = ['tasks', 'task-types', 'bonus-rules'];

    await app.signIn();

    await expect(page.getByRole('button', { name: 'Typy', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Bonusy', exact: true })).toHaveCount(0);
  });

  test('reguły statusów widzi tylko administrator aplikacji', async ({ app, page }) => {
    app.world.me.role = 'ROLE_ADMIN';
    app.world.personalisation.navigation = ['tasks'];

    await app.signIn();

    await expect(page.getByRole('button', { name: 'Reguły', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Powiad.', exact: true })).toBeVisible();
  });

  test('widok domownika nie pojawia się na pasku', async ({ app, page }) => {
    await app.signIn();
    await app.goTo('Zespoły');
    await app.onScreen('team-Kowalscy').getByText('Bartek', { exact: true }).click();

    await expect(page.getByRole('button', { name: 'Zadania: Bartek' })).toHaveCount(0);
  });
});

test.describe('Moje konto', () => {
  test('pokazuje imię, adres i rolę', async ({ app, page }) => {
    await app.signIn();
    await app.goTo('Moje Konto');

    await expect(page.getByText('Ala Kowalska').first()).toBeVisible();
    await expect(page.getByText('ala@example.com').first()).toBeVisible();
  });

  test('pseudonim wypiera imię z konta', async ({ app, page }) => {
    app.world.personalisation.nickname = 'Alcia';

    await app.signIn();
    await app.goTo('Moje Konto');

    await expect(page.getByText('Alcia')).toBeVisible();
  });

  test('wylogowanie wraca na ekran logowania', async ({ app, page }) => {
    await app.signIn();
    await app.goTo('Moje Konto');
    await page.getByRole('button', { name: 'Wyloguj' }).click();

    await expect(app.field('Email')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Zadania' })).toHaveCount(0);
  });
});
