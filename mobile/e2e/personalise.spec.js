const { test, expect } = require('./app');

const open = async (app) => {
  await app.signIn();
  await app.goTo('Mój wygląd');
};

test.describe('Mój wygląd', () => {
  test('pseudonim zapisuje się po opuszczeniu pola, nie przy każdym znaku', async ({ app, page }) => {
    await open(app);

    await app.field('Jak mam Cię nazywać').fill('Alcia');
    expect(app.sent('PUT', '/api/personalisation')).toHaveLength(0);

    await app.field('Jak mam Cię nazywać').blur();

    await expect
      .poll(() => app.sent('PUT', '/api/personalisation').length)
      .toBeGreaterThan(0);
    expect(app.lastSent('PUT', '/api/personalisation').body).toMatchObject({ nickname: 'Alcia' });
  });

  test('pusty pseudonim wraca do imienia z konta', async ({ app, page }) => {
    app.world.personalisation.nickname = 'Alcia';

    await open(app);
    await app.field('Jak mam Cię nazywać').fill('');
    await app.field('Jak mam Cię nazywać').blur();

    await app.goTo('Moje Konto');

    await expect(page.getByText('Ala Kowalska').first()).toBeVisible();
  });

  test('wybór koloru wiodącego zapisuje się', async ({ app, page }) => {
    await open(app);
    await page.getByRole('button', { name: '#1565c0' }).click();

    await expect
      .poll(() => app.world.personalisation.theme)
      .toBe('#1565c0');
  });

  test('zaskocz mnie losuje nowy avatar', async ({ app, page }) => {
    await open(app);
    const before = app.world.personalisation.avatar.seed;

    await page.getByRole('button', { name: 'Zaskocz mnie' }).click();

    await expect.poll(() => app.world.personalisation.avatar.seed).not.toBe(before);
  });

  test('schowanie pozycji ze strony głównej zdejmuje ją z układu', async ({ app, page }) => {
    await open(app);

    await app
      .onScreen('arranger-home')
      .getByRole('switch', { name: 'Pokazuj' })
      .first()
      .click();

    await expect.poll(() => app.world.personalisation.home).not.toContain('week');
  });

  test('wyłączenie wszystkiego ostrzega, że nic nie zostało', async ({ app, page }) => {
    app.world.personalisation.home = ['week'];

    await open(app);
    await app
      .onScreen('arranger-home')
      .getByRole('switch', { name: 'Pokazuj' })
      .first()
      .click();

    await expect(page.getByText('Nic tu nie pokazujesz — włącz coś poniżej.')).toBeVisible();
  });

  test('przestawienie kolejności w pasku zmienia pasek', async ({ app, page }) => {
    app.world.personalisation.navigation = ['tasks', 'teams', 'settings'];

    await app.signIn();
    await page.goto('/personalise');

    await app
      .onScreen('arranger-navigation')
      .getByRole('button', { name: 'Niżej' })
      .first()
      .click();

    await expect.poll(() => app.world.personalisation.navigation[0]).toBe('teams');
  });

  test('konfetti i dźwięk zapisują się', async ({ app, page }) => {
    await open(app);

    await page.getByText('Konfetti po zaliczeniu zadania').click();
    await expect.poll(() => app.world.personalisation.celebrates).toBe(false);

    await page.getByText('Dźwięk', { exact: true }).click();
    await expect.poll(() => app.world.personalisation.makesSound).toBe(true);
  });
});

test.describe('Kieszonkowe', () => {
  test('domownik widzi swój portfel', async ({ app, page }) => {
    app.world.teams[0].role = 'member';
    app.world.wallet = { ...app.world.wallet, pending: 1250, available: 800, putAside: 300 };

    await app.signIn();
    await app.goTo('Kieszonkowe');

    await expect(page.getByText('Oczekujące', { exact: true })).toBeVisible();
    await expect(page.getByText('Dostępne', { exact: true })).toBeVisible();
    await expect(page.getByText('Odłożone', { exact: true })).toBeVisible();
  });

  test('domownik bez celów dostaje zachętę', async ({ app, page }) => {
    app.world.teams[0].role = 'member';

    await app.signIn();
    await app.goTo('Kieszonkowe');

    await expect(page.getByText('Nie masz jeszcze żadnego celu. Wymyśl coś fajnego!')).toBeVisible();
  });

  test('admin widzi zakładki rozliczeń i zasad', async ({ app, page }) => {
    await app.signIn();
    await app.goTo('Kieszonkowe');

    await expect(page.getByRole('button', { name: 'Rozliczenia' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Zasady' })).toBeVisible();
  });

  test('admin bez ustawionej zasady widzi, że nic nie ustawiono', async ({ app, page }) => {
    await app.signIn();
    await app.goTo('Kieszonkowe');
    await page.getByRole('button', { name: 'Zasady' }).click();

    await expect(page.getByText('Nie ustawiono jeszcze żadnej zasady.').first()).toBeVisible();
  });

  test('błąd wczytywania pokazuje baner', async ({ app, page }) => {
    app.world.failing.add('GET /api/allowance/rules');

    await app.signIn();
    await app.goTo('Kieszonkowe');

    await expect(page.getByText('Coś poszło nie tak. Spróbuj ponownie.')).toBeVisible();
  });
});

test('własne zdjęcie i tło są przesyłane i zapisywane', async ({ app, page }) => {
  await open(app);
  const bytes = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
  for (const [label, purpose] of [['Wgraj swoje zdjęcie', 'avatar'], ['Wgraj własne tło', 'backdrop']]) {
    const selected = page.waitForEvent('filechooser');
    await page.getByRole('button', { name: label, exact: true }).click();
    await (await selected).setFiles({ name: 'pixel.png', mimeType: 'image/png', buffer: bytes });
    await expect.poll(() => app.world.personalisation[purpose].pictureId).toBeTruthy();
  }
  expect(app.world.pictures.map((picture) => picture.purpose)).toEqual(['avatar', 'backdrop']);
});
