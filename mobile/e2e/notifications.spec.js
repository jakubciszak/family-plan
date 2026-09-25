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
  const bubble = (page) => page.getByTestId('notification-bubble');
  const summary = (page) => page.getByTestId('notification-summary');

  test('pojedyncze zaległe powiadomienie pokazuje się z tematem i treścią', async ({ app, page }) => {
    app.world.notifications = [notification()];

    await app.signIn();

    await expect(bubble(page).getByText('Zadanie zatwierdzone')).toBeVisible();
    await expect(bubble(page).getByText('Bartek dostał 5 punktów')).toBeVisible();
  });

  test('zaległości z czasu nieobecności przychodzą jednym dymkiem zbiorczym', async ({ app, page }) => {
    app.world.notifications = [
      notification({ id: 'n1', subject: 'Pierwsze', message: 'Raz', createdAt: '2026-01-02T09:00:00+00:00' }),
      notification({ id: 'n2', subject: 'Drugie', message: 'Dwa', createdAt: '2026-01-02T10:00:00+00:00' }),
      notification({ id: 'n3', subject: 'Trzecie', message: 'Trzy', createdAt: '2026-01-01T10:00:00+00:00' }),
    ];

    await app.signIn();

    await expect(summary(page)).toBeVisible();
    await expect(summary(page).getByText('3 powiadomienia, gdy Cię nie było')).toBeVisible();
    await expect(summary(page).getByText('Drugie')).toBeVisible();
    await expect(bubble(page)).toHaveCount(0);

    await summary(page).getByRole('button', { name: 'Zamknij i oznacz jako przeczytane' }).click();

    await expect(summary(page)).toHaveCount(0);
    await expect.poll(() => app.lastSent('POST', '/api/notifications/read-all')?.body).toEqual({ ids: ['n2', 'n1', 'n3'] });
  });

  test('zamknięte powiadomienie jest przeczytane i nie wraca', async ({ app, page }) => {
    await page.clock.install({ time: new Date('2026-01-02T12:00:00Z') });
    app.world.notifications = [notification()];

    await app.signIn();
    await bubble(page).getByRole('button', { name: 'Zamknij' }).click();

    await expect(bubble(page)).toHaveCount(0);
    await expect.poll(() => app.sent('POST', '/api/notifications/n1/read').length).toBe(1);
    await page.clock.runFor(11000);
    await expect(bubble(page)).toHaveCount(0);
  });

  test('brak nieprzeczytanych to brak dymka', async ({ app, page }) => {
    app.world.notifications = [notification({ readAt: '2026-01-02T11:00:00+00:00' })];

    await app.signIn();

    await expect(bubble(page)).toHaveCount(0);
    await expect(summary(page)).toHaveCount(0);
  });

  test('nowość w trakcie korzystania pojawia się sama i sama znika', async ({ app, page }) => {
    await page.clock.install({ time: new Date('2026-01-02T12:00:00Z') });
    await app.signIn();
    await expect(bubble(page)).toHaveCount(0);

    app.world.notifications = [notification({ id: 'live', subject: 'Przypisano zadanie', message: 'Odkurzanie czeka', createdAt: '2026-01-02T12:00:05+00:00' })];
    await page.clock.runFor(10500);
    await expect(bubble(page).getByText('Odkurzanie czeka')).toBeVisible();

    await page.clock.runFor(8500);
    await expect(bubble(page)).toHaveCount(0);
    await expect.poll(() => app.sent('POST', '/api/notifications/live/read').length).toBe(1);
  });

  test('zbiorczy dymek maleje, gdy ktoś inny coś załatwi, i znika, gdy nic nie zostało', async ({ app, page }) => {
    await page.clock.install({ time: new Date('2026-01-02T12:00:00Z') });
    app.world.notifications = [
      notification({ id: 'n1', subject: 'Zadanie do akceptacji', message: 'Ola czeka', createdAt: '2026-01-02T09:00:00+00:00' }),
      notification({ id: 'n2', subject: 'Zadanie do akceptacji', message: 'Kuba czeka', createdAt: '2026-01-02T10:00:00+00:00' }),
      notification({ id: 'n3', subject: 'Kieszonkowe czeka', message: '20 zł do odbioru', createdAt: '2026-01-01T10:00:00+00:00' }),
    ];

    await app.signIn();
    await expect(summary(page).getByText('3 powiadomienia, gdy Cię nie było')).toBeVisible();

    // Drugi rodzic zatwierdził zadanie Kuby.
    app.world.notifications.find((item) => item.id === 'n2').resolvedAt = '2026-01-02T12:00:05+00:00';
    await page.clock.runFor(10500);
    await expect(summary(page).getByText('2 powiadomienia, gdy Cię nie było')).toBeVisible();

    app.world.notifications.forEach((item) => {
      item.resolvedAt ??= '2026-01-02T12:00:15+00:00';
    });
    await page.clock.runFor(10500);
    await expect(summary(page)).toHaveCount(0);
    expect(app.sent('POST', '/api/notifications/read-all')).toHaveLength(0);
  });

  test('dymek na ekranie znika od razu, gdy jego sprawę załatwiono gdzie indziej', async ({ app, page }) => {
    await page.clock.install({ time: new Date('2026-01-02T12:00:00Z') });
    await app.signIn();
    await expect(bubble(page)).toHaveCount(0);

    app.world.notifications = [notification({ id: 'live', subject: 'Zadanie do akceptacji', message: 'Ola czeka', createdAt: '2026-01-02T12:00:05+00:00' })];
    await page.clock.runFor(10500);
    await expect(bubble(page).getByText('Ola czeka')).toBeVisible();

    app.world.notifications[0].resolvedAt = '2026-01-02T12:00:12+00:00';
    // Powrót do aplikacji odpytuje serwer od razu, zanim dymek schowa się sam.
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await expect(bubble(page)).toHaveCount(0);
    expect(app.sent('POST', '/api/notifications/live/read')).toHaveLength(0);
  });

  test('przesunięcie w bok zamyka dymek, krótkie szarpnięcie go zostawia', async ({ app, page }) => {
    app.world.notifications = [notification()];

    await app.signIn();
    const shown = bubble(page);
    await expect(shown).toBeVisible();
    const box = await shown.boundingBox();
    const y = box.y + box.height / 2;

    // The first step sideways comes at once, before the body registers a press, and the click the browser
    // sends after the drag must not open the notification. Then slowly: a quick short move is a flick,
    // and a flick dismisses too.
    await page.mouse.move(box.x + box.width / 2, y);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + 12, y);
    for (let step = 1; step <= 3; step++) {
      await page.waitForTimeout(60);
      await page.mouse.move(box.x + box.width / 2 + 12 + step * 6, y);
    }
    await page.mouse.up();
    await page.waitForTimeout(300);
    await expect(shown).toBeVisible();
    expect(app.sent('POST', '/api/notifications/n1/read')).toHaveLength(0);

    await page.mouse.move(box.x + box.width / 2, y);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + box.width * 0.6, y, { steps: 10 });
    await page.mouse.up();

    await expect(bubble(page)).toHaveCount(0);
    await expect.poll(() => app.sent('POST', '/api/notifications/n1/read').length).toBe(1);
  });

  test('tekst dymka jest czytelny w ciemnym motywie', async ({ app, page }) => {
    app.world.personalisation.themeMode = 'dark';
    app.world.notifications = [notification()];

    await app.signIn();
    await expect(bubble(page)).toBeVisible();

    const ratios = await bubble(page).evaluate((element) => {
      const rgb = (value) => value.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);
      const luminance = ([r, g, b]) => [r, g, b].map((channel) => {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
      }).reduce((sum, c, index) => sum + c * [0.2126, 0.7152, 0.0722][index], 0);
      const background = luminance(rgb(getComputedStyle(element).backgroundColor));
      return ['Zadanie zatwierdzone', 'Bartek dostał 5 punktów'].map((text) => {
        const node = [...element.querySelectorAll('*')].find((candidate) => candidate.childElementCount === 0 && candidate.textContent === text);
        const foreground = luminance(rgb(getComputedStyle(node).color));
        return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
      });
    });
    ratios.forEach((ratio) => expect(ratio).toBeGreaterThan(7));
  });
});

test.describe('Lista powiadomień', () => {
  test('dzwonek liczy nieprzeczytane i otwiera listę z oznaczonymi załatwionymi', async ({ app, page }) => {
    app.world.notifications = [
      notification({ id: 'n1', subject: 'Zadanie do akceptacji', message: 'Ola czeka', createdAt: '2026-01-02T10:00:00+00:00' }),
      notification({ id: 'old', subject: 'Zadanie do akceptacji', message: 'Kuba czekał', createdAt: '2026-01-01T10:00:00+00:00', readAt: '2026-01-01T11:00:00+00:00', resolvedAt: '2026-01-01T11:00:00+00:00', active: false }),
    ];

    await app.signIn();
    await expect(page.getByRole('button', { name: 'Powiadomienia: 1 nieprzeczytane' })).toBeVisible();
    await page.getByTestId('notification-bell').click();

    const items = app.onScreen('notification-item');
    await expect(items).toHaveCount(2);
    await expect(items.nth(1).getByText('Załatwione')).toBeVisible();

    await page.getByRole('button', { name: 'Oznacz wszystkie jako przeczytane' }).click();
    await expect.poll(() => app.sent('POST', '/api/notifications/read-all').length).toBe(1);
  });
});

test.describe('Które powiadomienia chcę dostawać', () => {
  test('każdy rodzaj ma przełącznik, nieistotne są ukryte, a zapis wysyła wybór', async ({ app, page }) => {
    await app.signIn();
    await app.goTo('Ustawienia');

    await expect(page.getByText('Przypisano mi zadanie')).toBeVisible();
    await expect(page.getByText('Zadanie czeka na moją akceptację')).toHaveCount(0);
    await expect(page.getByText('Administrator aplikacji wyłączył ten rodzaj dla wszystkich.', { exact: false })).toBeVisible();

    await page.getByTestId('notification-kind-task_assigned').click();
    await page.getByTestId('notification-kind-streak_at_risk').click();
    await page.getByRole('button', { name: 'Zapisz ustawienia' }).click();

    await expect.poll(() => app.lastSent('PUT', '/api/notifications/preferences')?.body).toEqual({
      events: { task_assigned: false, task_completed: true, task_approved: true, calendar_changed: true, payout_offered: true, streak_at_risk: true },
    });
  });

  test('po błędzie wczytania da się ponowić, a zmiana kanału zostaje', async ({ app, page }) => {
    app.world.failing.add('GET /api/notifications/preferences');
    await app.signIn();
    await app.goTo('Ustawienia');

    await expect(page.getByText('Nie udało się pobrać rodzajów powiadomień.', { exact: false })).toBeVisible();
    await page.getByText('SMS', { exact: true }).click();
    await expect(page.getByRole('switch').nth(1)).toBeChecked();

    app.world.failing.delete('GET /api/notifications/preferences');
    await page.getByTestId('notification-kinds-retry').click();

    await expect(page.getByText('Przypisano mi zadanie')).toBeVisible();
    await expect(page.getByRole('switch').nth(1)).toBeChecked();
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
