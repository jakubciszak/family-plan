const { test, expect } = require('./app');
const { template } = require('./fake-api');

for (const [route, title] of [
  ['/', 'Zadania'],
  ['/teams', 'Zespoły'],
  ['/allowance', 'Kieszonkowe'],
  ['/task-types', 'Typy zadań'],
  ['/bonus-rules', 'Zasady bonusowe'],
  ['/status-change-rules', 'Reguły Zmian Statusów'],
  ['/notification-events', 'Powiadomienia'],
  ['/personalise', 'Mój wygląd'],
  ['/account', 'Moje Konto'],
  ['/settings', 'Ustawienia'],
]) {
  test(`${title}: ekran mieści się na 320 px bez błędów renderowania`, async ({
    app,
    page,
  }, testInfo) => {
    app.world.me.role = 'ROLE_ADMIN';
    app.world.templates = [
      template({
        name: 'Porządek w pokoju',
        description: 'Poskładaj ubrania i schowaj zabawki.',
      }),
    ];
    app.world.personalisation.themeMode =
      route === '/personalise' || route === '/' ? 'dark' : 'light';
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') errors.push(message.text());
    });
    await page.setViewportSize({ width: 320, height: 740 });
    await app.signIn();
    if (route !== '/') await page.goto(route);
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      320,
    );
    expect(errors).toEqual([]);
    await page.screenshot({ path: testInfo.outputPath('screen.png') });
  });
}

test('ustawienia pozwalają ponowić pobranie kanałów po awarii', async ({ app, page }) => {
  const endpoint = `GET /api/user-settings/${app.world.me.id}`;
  app.world.failing.add(endpoint);
  await app.signIn();
  await app.goTo('Ustawienia');
  await expect(page.getByTestId('notification-channel-retry')).toBeVisible();
  app.world.failing.delete(endpoint);
  await page.getByTestId('notification-channel-retry').click();
  await expect(page.getByText('SMS', { exact: true })).toBeVisible();
  await expect(page.getByTestId('notification-channel-retry')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Zapisz ustawienia' })).toBeVisible();
});
