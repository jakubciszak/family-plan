const base = require('@playwright/test');

const { freshWorld, installApi } = require('./fake-api');

/**
 * The bar shows short labels, the "Więcej" sheet the full ones, and they are
 * not substrings of each other ("Bonusy" vs "Zasady bonusowe").
 */
const PLACES = {
  'Plany działania': 'Plany',
  'Zadania': 'Zadania',
  'Zespoły': 'Zespoły',
  'Kieszonkowe': 'Kasa',
  'Mój wygląd': 'Wygląd',
  'Typy zadań': 'Typy',
  'Zasady bonusowe': 'Bonusy',
  'Moje Konto': 'Konto',
  'Ustawienia': 'Ustawienia',
  'Reguły Zmian Statusów': 'Reguły',
  'Powiadomienia': 'Powiad.',
};

const test = base.test.extend({
  world: async ({}, use) => {
    await use(freshWorld());
  },

  app: async ({ page, world }, use) => {
    await installApi(page, world);

    await use({
      page,
      world,

      /** Labels overlap by substring ("Hasło" is inside "Pokaż hasło"), so never fuzzy. */
      field(label) {
        return page.getByLabel(label, { exact: true });
      },

      /**
       * Visited tab screens stay in the DOM stacked under the active one, so a
       * bare getByTestId would also find the screen behind. React Navigation
       * marks the ones out of view aria-hidden; this keeps only what is on top.
       */
      onScreen(testId) {
        return page.locator(
          `[data-testid="${testId}"]:not([aria-hidden="true"]):not([aria-hidden="true"] *)`
        );
      },

      async open() {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
      },

      async signIn({ expectFailure = false } = {}) {
        await this.open();
        await this.field('Email').fill(world.me.email);
        await this.field('Hasło').fill('sekret123');
        await page.getByRole('button', { name: 'Zaloguj' }).click();

        if (!expectFailure) {
          await page.getByRole('button', { name: 'Zadania', exact: true }).waitFor();
        }
      },

      /** Calls the app made, so a test can assert on the payload it sent. */
      sent(method, path) {
        return world.calls.filter((call) => call.method === method && call.path === path);
      },

      lastSent(method, path) {
        return this.sent(method, path).at(-1);
      },

      async goTo(place) {
        const short = PLACES[place];

        if (!short) {
          throw new Error(`goTo: unknown place "${place}"`);
        }

        const onBar = page.getByRole('button', { name: short, exact: true });

        if (await onBar.count()) {
          await onBar.first().click();
          return;
        }

        await page.getByRole('button', { name: 'Więcej' }).click();
        await page
          .getByTestId('modal-surface')
          .getByText(place, { exact: true })
          .click();
      },
    });
  },
});

module.exports = { test, expect: base.expect };
