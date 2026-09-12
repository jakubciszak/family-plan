const { test, expect } = require('@playwright/test');
const {
  APP,
  PASSWORD,
  unique,
  createAccount,
  createTeamOwner,
  loginThroughUi,
  openTab,
} = require('./helpers');

test.describe('A. Uwierzytelnianie', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('A1 rejestracja zaklada konto', async ({ page }) => {
    await page.goto(APP);
    await page.locator('.auth-switch a').click();
    await page.locator('input[type="text"]').first().fill('Nowy');
    await page.locator('input[type="email"]').first().fill(unique('a1'));
    await page.locator('input[type="password"]').first().fill(PASSWORD);
    await page.locator('button[type="submit"]').first().click();

    await expect(page.locator('.success-message')).toBeVisible();
  });

  test('A2 rejestracja odrzuca zajety adres', async ({ page }) => {
    const existing = await createAccount();

    await page.goto(APP);
    await page.locator('.auth-switch a').click();
    await page.locator('input[type="text"]').first().fill('Duplikat');
    await page.locator('input[type="email"]').first().fill(existing.email);
    await page.locator('input[type="password"]').first().fill(PASSWORD);
    await page.locator('button[type="submit"]').first().click();

    await expect(page.locator('.error-message')).toBeVisible();
  });

  test('A3 rejestracja odrzuca za krotkie haslo', async ({ page }) => {
    await page.goto(APP);
    await page.locator('.auth-switch a').click();
    await page.locator('input[type="text"]').first().fill('Krotkie');
    await page.locator('input[type="email"]').first().fill(unique('a3'));
    await page.locator('input[type="password"]').first().fill('abc');
    await page.locator('button[type="submit"]').first().click();

    await expect(page.locator('.success-message')).toHaveCount(0);
  });

  test('A4 logowanie wpuszcza do aplikacji', async ({ page }) => {
    const account = await createAccount();
    await loginThroughUi(page, account.email);

    await expect(page.locator('.user-info')).toBeVisible();
  });

  test('A5 logowanie odrzuca zle haslo', async ({ page }) => {
    const account = await createAccount();

    await page.goto(APP);
    await page.locator('input[type="email"]').first().fill(account.email);
    await page.locator('input[type="password"]').first().fill('ZupelnieZle999');
    await page.locator('button[type="submit"]').first().click();

    await expect(page.locator('input[type="password"]')).toBeVisible();
  });

  test('A6 wylogowanie przezywa odswiezenie strony', async ({ page }) => {
    const account = await createAccount();
    await loginThroughUi(page, account.email);

    await page.getByRole('button', { name: /logout|wyloguj/i }).first().click();
    await expect(page.locator('input[type="password"]')).toBeVisible();

    await page.reload();
    await expect(page.locator('input[type="password"]')).toBeVisible({ timeout: 15000 });
  });

  test('A7 zwykle wejscie nie pokazuje banera zaproszenia', async ({ page }) => {
    const owner = await createTeamOwner();
    const invite = await owner.session.post(`/api/teams/${owner.teamId}/invite`, {
      email: unique('a7'),
      role: 'member',
    });

    await page.goto(`${APP}/?invite=${invite.body.invitation.token}`);
    await expect(page.locator('.invite-banner')).toBeVisible();

    await page.goto(APP);
    await expect(page.locator('.invite-banner')).toHaveCount(0);
  });
});

test.describe('B. Moje Konto', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('B1 konto pokazuje dane zalogowanego', async ({ page }) => {
    const account = await createAccount({ name: 'Wlasciciel Konta' });
    await loginThroughUi(page, account.email);
    await openTab(page, /my account|moje konto/i);

    await expect(page.locator('.account-details')).toContainText(account.email);
  });

  test('B2 zmiana hasla wykrywa niezgodne powtorzenie', async ({ page }) => {
    const account = await createAccount();
    await loginThroughUi(page, account.email);
    await openTab(page, /my account|moje konto/i);

    await page.locator('#currentPassword').fill(PASSWORD);
    await page.locator('#newPassword').fill('NoweHaslo456');
    await page.locator('#confirmPassword').fill('Inne9999999');
    await page.locator('.account-form button[type="submit"]').click();

    await expect(page.locator('.error-message')).toBeVisible();
  });

  test('B3 zmiana hasla odrzuca zle obecne', async ({ page }) => {
    const account = await createAccount();
    await loginThroughUi(page, account.email);
    await openTab(page, /my account|moje konto/i);

    await page.locator('#currentPassword').fill('ZleObecne999');
    await page.locator('#newPassword').fill('NoweHaslo456');
    await page.locator('#confirmPassword').fill('NoweHaslo456');
    await page.locator('.account-form button[type="submit"]').click();

    await expect(page.locator('.error-message')).toBeVisible();
  });

  test('B4 nowe haslo dziala po zmianie', async ({ page }) => {
    const account = await createAccount();
    await loginThroughUi(page, account.email);
    await openTab(page, /my account|moje konto/i);

    await page.locator('#currentPassword').fill(PASSWORD);
    await page.locator('#newPassword').fill('NoweHaslo456');
    await page.locator('#confirmPassword').fill('NoweHaslo456');
    await page.locator('.account-form button[type="submit"]').click();
    await expect(page.locator('.success-message')).toBeVisible();

    await page.getByRole('button', { name: /logout|wyloguj/i }).first().click();
    await loginThroughUi(page, account.email, 'NoweHaslo456');
    await expect(page.locator('.user-info')).toBeVisible();
  });
});
