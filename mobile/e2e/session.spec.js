const { test, expect } = require('./app');

test('równoległe żądania odnawiają token tylko raz', async ({ app, page }) => {
  await app.signIn();
  let refreshes = 0;
  await page.route('**/api/auth/token/refresh', async (route) => {
    refreshes += 1;
    await new Promise((resolve) => setTimeout(resolve, 150));
    await route.fulfill({ json: { token: 'renewed', refresh_token: 'next-refresh' } });
  });
  await page.route('**/api/teams**', async (route) => {
    if (route.request().headers().authorization === 'Bearer fake.access.token') {
      await route.fulfill({ status: 401, json: { message: 'Expired' } });
    } else {
      await route.fallback();
    }
  });
  await app.goTo('Zespoły');
  await expect(app.onScreen('team-Kowalscy')).toBeVisible();
  expect(refreshes).toBe(1);
  expect(await page.evaluate(() => localStorage.getItem('familyplan.refreshToken'))).toBe('next-refresh');
});

test('odrzucone odświeżenie kończy sesję', async ({ app, page }) => {
  await app.signIn();
  await page.route('**/api/auth/me', (route) => route.fulfill({ status: 401, json: {} }));
  await page.route('**/api/auth/token/refresh', (route) => route.fulfill({ status: 401, json: {} }));
  await page.reload();
  await expect(app.field('Email')).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('familyplan.refreshToken'))).toBeNull();
});

test('awaria serwera odświeżania zachowuje sesję do ponowienia', async ({ app, page }) => {
  await app.signIn();
  await page.route('**/api/auth/me', (route) => route.fulfill({ status: 401, json: {} }));
  await page.route('**/api/auth/token/refresh', (route) => route.fulfill({ status: 503, json: {} }));
  await page.reload();
  await expect(app.field('Email')).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('familyplan.refreshToken'))).toBe('fake-refresh');
});
