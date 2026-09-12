const { test, expect } = require('@playwright/test');
const {
  createTeamOwner,
  addTeamMember,
  createTask,
  loginThroughUi,
  openTab,
  horizontalOverflow,
} = require('./helpers');

const VIEWPORTS = [
  { label: 'iPhone SE', width: 375, height: 667 },
  { label: 'Pixel 7', width: 412, height: 915 },
  { label: 'iPhone 14 Pro Max', width: 430, height: 932 },
];

const SCREENS = [
  ['Zadania', /^tasks|^zadani/i],
  ['Zespoly', /^teams|^zespo/i],
  ['Moje Konto', /my account|moje konto/i],
  ['Ustawienia', /settings|ustawieni/i],
];

test.describe('M. Mobile first', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  for (const viewport of VIEWPORTS) {
    test.describe(`${viewport.label} (${viewport.width}px)`, () => {
      test.use({
        viewport: { width: viewport.width, height: viewport.height },
        isMobile: true,
        hasTouch: true,
      });

      test('M1 zaden ekran nie przewija sie w poziomie', async ({ page }) => {
        const owner = await createTeamOwner();
        await addTeamMember(owner);
        await createTask(owner, { name: 'Bardzo dlugie zadanie do sprzatania calego domu' });

        await loginThroughUi(page, owner.email);

        const offenders = [];
        for (const [label, pattern] of SCREENS) {
          await openTab(page, pattern);
          if (label === 'Zespoly' && (await page.locator('.team-card').count())) {
            await page.locator('.team-card').first().click();
            await page.waitForTimeout(600);
          }
          const overflow = await horizontalOverflow(page);
          if (overflow > 0) offenders.push(`${label}: +${overflow}px`);
        }

        expect(offenders, `Ekrany przewijaja sie w poziomie: ${offenders.join(', ')}`).toEqual([]);
      });

      test('M2 ekran logowania miesci sie w szerokosci', async ({ page }) => {
        await page.goto(process.env.REAL_APP_URL || process.env.BASE_URL || 'http://localhost:3000');
        await page.waitForSelector('input[type="password"]');

        expect(await horizontalOverflow(page)).toBe(0);
      });

      test('M3 cele dotyku maja co najmniej 44px wysokosci', async ({ page }) => {
        const owner = await createTeamOwner();
        await loginThroughUi(page, owner.email);

        const tooSmall = await page.evaluate(() => {
          const bad = [];
          document.querySelectorAll('button, a[href], input, select').forEach((el) => {
            const rect = el.getBoundingClientRect();
            if (rect.height > 0 && rect.height < 44) {
              const cls = typeof el.className === 'string' ? el.className.split(' ')[0] : '';
              bad.push(`${el.tagName.toLowerCase()}${cls ? '.' + cls : ''} ${Math.round(rect.height)}px`);
            }
          });
          return [...new Set(bad)];
        });

        expect(tooSmall, `Za male cele dotyku: ${tooSmall.join(', ')}`).toEqual([]);
      });
    });
  }
});
