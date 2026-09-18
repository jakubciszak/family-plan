const { test, expect } = require('./app');

test.describe('Logowanie', () => {
  test('niezalogowany trafia na logowanie', async ({ app, page }) => {
    await app.open();

    await expect(app.field('Email')).toBeVisible();
    await expect(page.getByText('Moje zadania')).toHaveCount(0);
  });

  test('przycisk jest nieaktywny, dopóki pola są puste', async ({ app, page }) => {
    await app.open();
    const submit = page.getByRole('button', { name: 'Zaloguj' });

    await expect(submit).toBeDisabled();

    await app.field('Email').fill('ala@example.com');
    await expect(submit).toBeDisabled();

    await app.field('Hasło').fill('sekret123');
    await expect(submit).toBeEnabled();
  });

  test('poprawne dane wpuszczają do aplikacji', async ({ app, page }) => {
    await app.signIn();

    await expect(page.getByTestId('approval-queue')).toBeVisible();
  });

  test('błędne dane zostawiają na ekranie z komunikatem', async ({ app, page }) => {
    app.world.loginFails = true;

    await app.signIn({ expectFailure: true });

    await expect(page.getByText('Logowanie nie powiodło się. Sprawdź dane logowania.')).toBeVisible();
    await expect(app.field('Email')).toBeVisible();
  });

  test('kłódka odsłania hasło', async ({ app, page }) => {
    await app.open();
    const password = app.field('Hasło');

    await expect(password).toHaveAttribute('type', 'password');

    await page.getByRole('button', { name: 'Pokaż hasło' }).click();

    await expect(password).not.toHaveAttribute('type', 'password');
    await expect(page.getByRole('button', { name: 'Ukryj hasło' })).toBeVisible();
  });

  test('prowadzi na rejestrację', async ({ app, page }) => {
    await app.open();
    await page.getByRole('button', { name: 'Zarejestruj' }).click();

    await expect(app.field('Imię')).toBeVisible();
  });

  test('z tokenem zaproszenia zachęca do zalogowania się po zaproszenie', async ({ app, page }) => {
    await page.goto('/login?invite=tok-1');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Zaloguj się, aby zaakceptować oczekujące zaproszenie.')).toBeVisible();
  });
  test('zalogowanie z zaproszeniem przyjmuje je', async ({ app, page }) => {
    app.world.invitations = [
      {
        id: 'i1',
        token: 'tok-9',
        teamId: app.world.teams[0].id,
        teamName: 'Kowalscy',
        email: 'ala@example.com',
        role: 'member',
        status: 'pending',
        expiresAt: null,
      },
    ];

    await page.goto('/login?invite=tok-9');
    await page.waitForLoadState('networkidle');
    await app.field('Email').fill('ala@example.com');
    await app.field('Hasło').fill('sekret123');
    await page.getByRole('button', { name: 'Zaloguj' }).click();

    await expect(page.getByTestId('approval-queue')).toBeVisible();

    await expect
      .poll(() => app.sent('POST', '/api/teams/invitations/tok-9/accept').length)
      .toBe(1);
  });
});

test.describe('Rejestracja', () => {
  const openRegister = async (app, page, query = '') => {
    await page.goto(`/register${query}`);
    await page.waitForLoadState('networkidle');
  };

  test('zapis czeka na imię, e-mail i hasło; telefon jest opcjonalny', async ({ app, page }) => {
    await openRegister(app, page);
    const submit = page.getByRole('button', { name: 'Zarejestruj' });

    await expect(submit).toBeDisabled();

    await app.field('Imię').fill('Ala');
    await app.field('Email').fill('ala@example.com');
    await expect(submit).toBeDisabled();

    await app.field('Hasło').fill('sekret123');
    await expect(submit).toBeEnabled();
  });

  test('bez wymaganej aktywacji od razu loguje', async ({ app, page }) => {
    app.world.registration.activationRequired = false;

    await openRegister(app, page);
    await app.field('Imię').fill('Ala');
    await app.field('Email').fill('ala@example.com');
    await app.field('Hasło').fill('sekret123');
    await page.getByRole('button', { name: 'Zarejestruj' }).click();

    await expect(page.getByTestId('approval-queue')).toBeVisible();
  });

  test('z wymaganą aktywacją zostawia komunikat i czyści formularz', async ({ app, page }) => {
    app.world.registration.activationRequired = true;

    await openRegister(app, page);
    await app.field('Imię').fill('Ala');
    await app.field('Email').fill('ala@example.com');
    await app.field('Hasło').fill('sekret123');
    await page.getByRole('button', { name: 'Zarejestruj' }).click();

    await expect(page.getByText(/Sprawdź swoją skrzynkę email/)).toBeVisible();
    await expect(app.field('Imię')).toHaveValue('');
  });

  test('zajęty adres mówi wprost, że konto już istnieje', async ({ app, page }) => {
    app.world.registration.taken = ['ala@example.com'];

    await openRegister(app, page);
    await app.field('Imię').fill('Ala');
    await app.field('Email').fill('ala@example.com');
    await app.field('Hasło').fill('sekret123');
    await page.getByRole('button', { name: 'Zarejestruj' }).click();

    await expect(page.getByText('Konto z tym adresem email już istnieje.')).toBeVisible();
  });

  test('zaproszenie dociąga adres i blokuje pole', async ({ app, page }) => {
    app.world.invitations = [
      {
        id: 'i1',
        token: 'tok-1',
        teamId: 'x',
        teamName: 'Kowalscy',
        email: 'bartek@example.com',
        role: 'member',
        status: 'pending',
        expiresAt: null,
      },
    ];

    await openRegister(app, page, '?invite=tok-1');

    await expect(app.field('Email')).toHaveValue('bartek@example.com');
    await expect(app.field('Email')).not.toBeEditable();
    await expect(page.getByText(/Zostałeś zaproszony do zespołu/)).toBeVisible();
  });

  test('nieznane zaproszenie nie wywala formularza', async ({ app, page }) => {
    await openRegister(app, page, '?invite=nie-ma-takiego');

    await expect(app.field('Imię')).toBeVisible();
    await expect(app.field('Email')).toBeEditable();
  });

  test('rejestracja z zaproszeniem od razu wciąga do zespołu', async ({ app, page }) => {
    app.world.invitations = [
      {
        id: 'i1',
        token: 'tok-1',
        teamId: app.world.teams[0].id,
        teamName: 'Kowalscy',
        email: 'bartek@example.com',
        role: 'member',
        status: 'pending',
        expiresAt: null,
      },
    ];

    await openRegister(app, page, '?invite=tok-1');
    await app.field('Imię').fill('Bartek');
    await app.field('Hasło').fill('sekret123');
    await page.getByRole('button', { name: 'Zarejestruj' }).click();

    await expect(page.getByTestId('approval-queue')).toBeVisible();

    await expect
      .poll(() => app.sent('POST', '/api/teams/invitations/tok-1/accept').length)
      .toBe(1);
    expect(app.world.invitations).toHaveLength(0);
  });

  test('rejestracja bez zaproszenia nie próbuje nic akceptować', async ({ app, page }) => {
    await openRegister(app, page);
    await app.field('Imię').fill('Ala');
    await app.field('Email').fill('ala@example.com');
    await app.field('Hasło').fill('sekret123');
    await page.getByRole('button', { name: 'Zarejestruj' }).click();

    await expect(page.getByTestId('approval-queue')).toBeVisible();

    expect(
      app.world.calls.filter((call) => call.path.includes('/accept'))
    ).toHaveLength(0);
  });

  test('wraca na logowanie', async ({ app, page }) => {
    await openRegister(app, page);
    await page.getByRole('button', { name: 'Zaloguj' }).click();

    await expect(app.field('Hasło')).toBeVisible();
    await expect(app.field('Imię')).toHaveCount(0);
  });
});

test.describe('Trwałość sesji', () => {
  test('sesja wraca po przeładowaniu aplikacji', async ({ app, page }) => {
    await app.signIn();
    await page.reload();
    await expect(page.getByTestId('approval-queue')).toBeVisible();
    expect(app.sent('POST', '/api/auth/token')).toHaveLength(1);
  });

  test('chwilowa awaria API nie usuwa zapisanych tokenów', async ({ app, page }) => {
    await app.signIn();
    app.world.failing.add('GET /api/auth/me');
    await page.reload();
    await expect(app.field('Email')).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('familyplan.refreshToken'))).toBe('fake-refresh');
    app.world.failing.clear();
    await page.reload();
    await expect(page.getByTestId('approval-queue')).toBeVisible();
  });
});
