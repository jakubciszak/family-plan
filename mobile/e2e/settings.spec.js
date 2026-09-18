const { test, expect } = require('./app');

const openSettings = async (app) => {
  await app.signIn();
  await app.goTo('Ustawienia');
};

test.describe('Ustawienia', () => {
  test('kanały powiadomień mają domyślne wartości, gdy backend ich nie ma', async ({ app, page }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') errors.push(message.text());
    });
    await openSettings(app);

    await expect(page.getByText('Kanały powiadomień')).toBeVisible();
    await expect(page.getByRole('switch').nth(0)).toBeChecked();
    await expect(page.getByRole('switch').nth(1)).not.toBeChecked();
    await expect(page.getByRole('switch').nth(2)).toBeChecked();
    expect(errors).toEqual([]);
  });

  test('przełączenie kanału i zapis wysyłają nowy stan', async ({ app, page }) => {
    await openSettings(app);

    await page.getByText('SMS', { exact: true }).click();
    await page.getByRole('button', { name: 'Zapisz ustawienia' }).click();

    await expect(page.getByText('Ustawienia zapisane pomyślnie!')).toBeVisible();
    expect(app.lastSent('PUT', `/api/user-settings/${app.world.me.id}`).body.options).toEqual([
      { name: 'email', enabled: true },
      { name: 'sms', enabled: true },
      { name: 'in_app', enabled: true },
      { name: 'push', enabled: true },
    ]);
  });

  test('błąd zapisu pokazuje baner i zostawia wybór', async ({ app, page }) => {
    app.world.failing.add(`PUT /api/user-settings/${app.world.me.id}`);

    await openSettings(app);
    await page.getByText('SMS', { exact: true }).click();
    await page.getByRole('button', { name: 'Zapisz ustawienia' }).click();

    await expect(page.getByText('Nie udało się zapisać ustawień')).toBeVisible();
    await expect(page.getByRole('switch').nth(1)).toBeChecked();
  });

  test('zmiana motywu zapisuje wybór', async ({ app, page }) => {
    await openSettings(app);
    await page.getByRole('button', { name: 'Ciemny' }).click();

    expect(app.lastSent('PUT', '/api/personalisation').body).toMatchObject({ themeMode: 'dark' });
  });

  test('zmiana języka przestawia interfejs', async ({ app, page }) => {
    await openSettings(app);
    await page.getByRole('button', { name: 'EN', exact: true }).click();

    await expect(page.getByText('Notification channels')).toBeVisible();
  });
});
