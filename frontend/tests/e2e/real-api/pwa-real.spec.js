const { test, expect } = require('@playwright/test');
const { APP } = require('./helpers');

test.describe('P. Instalowalna aplikacja', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('P1 strona wskazuje manifest i ikony', async ({ page }) => {
    await page.goto(APP);

    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.json');
    await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute('href', '/apple-touch-icon.png');
    await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#f5fbf5');
  });

  test('P2 manifest spelnia warunki instalowalnosci', async ({ page }) => {
    const response = await page.request.get(`${APP}/manifest.json`);
    expect(response.ok()).toBeTruthy();

    const manifest = await response.json();
    expect(manifest.name).toBeTruthy();
    expect(manifest.short_name).toBeTruthy();
    expect(manifest.start_url).toBe('/');
    expect(manifest.display).toBe('standalone');

    const sizes = manifest.icons.map((icon) => icon.sizes);
    expect(sizes).toContain('192x192');
    expect(sizes).toContain('512x512');
    expect(manifest.icons.some((icon) => icon.purpose === 'maskable')).toBeTruthy();
  });

  test('P3 wszystkie ikony z manifestu sa dostepne', async ({ page }) => {
    const manifest = await (await page.request.get(`${APP}/manifest.json`)).json();

    for (const icon of manifest.icons) {
      const response = await page.request.get(`${APP}${icon.src}`);
      expect(response.status(), `${icon.src} nie jest serwowana`).toBe(200);
    }
  });

  test('P4 service worker rejestruje sie i obsluguje fetch', async ({ page }) => {
    await page.goto(APP);

    const registered = await page.evaluate(async () => {
      if (!('serviceWorker' in navigator)) return false;
      const registration = await navigator.serviceWorker.ready;
      return Boolean(registration.active || registration.installing || registration.waiting);
    });

    expect(registered).toBeTruthy();

    const sw = await page.request.get(`${APP}/sw.js`);
    expect(sw.ok()).toBeTruthy();
    expect(await sw.text()).toContain("addEventListener('fetch'");
  });

  test('P5 service worker nie przechwytuje wywolan API', async ({ page }) => {
    const sw = await (await page.request.get(`${APP}/sw.js`)).text();

    expect(sw).toContain("url.pathname.startsWith('/api/')");
  });
});
