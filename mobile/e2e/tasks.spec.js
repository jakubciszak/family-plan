const { test, expect } = require('./app');
const { execution, template } = require('./fake-api');

const card = (app, name) => app.onScreen(`task-${name}`);

test.beforeEach(async ({ app }) => {
  app.world.teams[0].role = 'member';
});

const dayOffset = (days) => {
  const day = new Date();
  day.setDate(day.getDate() + days);

  return day.toLocaleDateString('sv');
};

test.describe('Zadania', () => {
  test('pula pokazuje tylko aktywne typy', async ({ app, page }) => {
    app.world.templates = [
      template({ id: 'a', name: 'Zmywanie', isActive: true }),
      template({ id: 'b', name: 'Wycofane', isActive: false }),
    ];

    await app.signIn();

    await expect(card(app, 'Zmywanie')).toBeVisible();
    await expect(card(app, 'Wycofane')).toHaveCount(0);
  });

  test('pusta lista i pusta pula mówią o tym wprost', async ({ app, page }) => {
    await app.signIn();

    await expect(page.getByText('Nie masz wziętych zadań')).toBeVisible();
    await expect(page.getByText('Brak dostępnych zadań')).toBeVisible();
  });

  test('wzięcie zadania przenosi je z puli do moich', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie' })];

    await app.signIn();
    await card(app, 'Zmywanie').getByRole('button', { name: 'Weź to zadanie' }).click();

    await expect(card(app, 'Zmywanie').getByRole('button', { name: 'Ukończ' })).toBeVisible();
  });

  test('otwarte zadanie ma trzy akcje', async ({ app, page }) => {
    app.world.executions = [execution({ id: 'e1', name: 'Zmywanie', status: 'new' })];

    await app.signIn();
    const mine = card(app, 'Zmywanie');

    await expect(mine.getByRole('button', { name: 'Ukończ' })).toBeVisible();
    await expect(mine.getByRole('button', { name: 'Zaległość' })).toBeVisible();
    await expect(mine.getByRole('button', { name: 'Oddaj do puli' })).toBeVisible();
  });

  test('ukończenie zabiera akcje i pokazuje oczekiwanie', async ({ app, page }) => {
    app.world.executions = [execution({ id: 'e1', name: 'Zmywanie', status: 'new' })];

    await app.signIn();
    await card(app, 'Zmywanie').getByRole('button', { name: 'Ukończ' }).click();

    await expect(card(app, 'Zmywanie').getByText('Czeka na zatwierdzenie')).toBeVisible();
    await expect(card(app, 'Zmywanie').getByRole('button', { name: 'Ukończ' })).toHaveCount(0);
  });

  test('ukończone zadanie nie ma żadnej akcji', async ({ app, page }) => {
    app.world.executions = [execution({ id: 'e1', name: 'Zmywanie', status: 'completed' })];

    await app.signIn();
    const mine = card(app, 'Zmywanie');

    await expect(mine.getByText('Czeka na zatwierdzenie')).toBeVisible();

    for (const action of ['Ukończ', 'Zaległość', 'Oddaj do puli', 'Zgłoś ponownie']) {
      await expect(mine.getByRole('button', { name: action })).toHaveCount(0);
    }
  });

  test('zatwierdzone zadanie znika z listy', async ({ app, page }) => {
    app.world.executions = [
      execution({ id: 'e1', name: 'Zmywanie', status: 'approved' }),
      execution({ id: 'e2', name: 'Smieci', status: 'new' }),
    ];

    await app.signIn();

    await expect(card(app, 'Smieci')).toBeVisible();
    await expect(card(app, 'Zmywanie')).toHaveCount(0);
  });

  test('odrzucone zadanie pokazuje powód i pozwala zgłosić ponownie', async ({ app, page }) => {
    app.world.executions = [
      execution({
        id: 'e1',
        name: 'Zmywanie',
        status: 'rejected',
        rejectionReason: 'Talerze dalej brudne',
      }),
    ];

    await app.signIn();
    const mine = card(app, 'Zmywanie');

    await expect(mine.getByText(/Talerze dalej brudne/)).toBeVisible();
    await expect(mine.getByRole('button', { name: 'Ukończ' })).toHaveCount(0);

    await mine.getByRole('button', { name: 'Zgłoś ponownie' }).click();

    await expect(card(app, 'Zmywanie').getByText('Czeka na zatwierdzenie')).toBeVisible();
  });

  test('oddanie zadania zwraca je do puli', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie' })];
    app.world.executions = [
      execution({ id: 'e1', taskTemplateId: 'a', name: 'Zmywanie', status: 'new' }),
    ];

    await app.signIn();
    await card(app, 'Zmywanie').getByRole('button', { name: 'Oddaj do puli' }).click();

    await expect(page.getByText('Nie masz wziętych zadań')).toBeVisible();
    await expect(card(app, 'Zmywanie').getByRole('button', { name: 'Weź to zadanie' })).toBeVisible();
  });

  test('zaległość wysyła dzień wykonania razem z ukończeniem', async ({ app, page }) => {
    app.world.executions = [execution({ id: 'e1', name: 'Zmywanie', status: 'new' })];

    await app.signIn();
    await card(app, 'Zmywanie').getByRole('button', { name: 'Zaległość' }).click();

    const dialog = page.getByTestId('backlog-dialog');
    await expect(dialog.getByText('Kiedy zrobiono: Zmywanie?')).toBeVisible();

    const yesterday = dayOffset(-1);
    await app.field('Dzień wykonania').fill(yesterday);
    await dialog.getByRole('button', { name: 'Ukończ' }).click();

    expect(app.lastSent('POST', '/api/task-executions/e1/complete').body).toEqual({
      doneOn: yesterday,
    });
  });

  test('zaległość nie przyjmuje dnia spoza okna siedmiu dni', async ({ app, page }) => {
    app.world.executions = [execution({ id: 'e1', name: 'Zmywanie', status: 'new' })];

    await app.signIn();
    await card(app, 'Zmywanie').getByRole('button', { name: 'Zaległość' }).click();

    const dialog = page.getByTestId('backlog-dialog');
    const confirm = dialog.getByRole('button', { name: 'Ukończ' });

    await app.field('Dzień wykonania').fill(dayOffset(-8));
    await expect(confirm).toBeDisabled();

    await app.field('Dzień wykonania').fill(dayOffset(1));
    await expect(confirm).toBeDisabled();

    await app.field('Dzień wykonania').fill(dayOffset(-7));
    await expect(confirm).toBeEnabled();
  });

  test('błąd wczytywania pokazuje baner, który da się zamknąć', async ({ app, page }) => {
    app.world.failing.add('GET /api/task-executions/mine');

    await app.signIn();

    await expect(page.getByText('Nie udało się wczytać zadań')).toBeVisible();

    await page.getByRole('button', { name: 'Zamknij' }).click();

    await expect(page.getByText('Nie udało się wczytać zadań')).toHaveCount(0);
  });
});

test('ukończenie zadania respektuje ustawienie konfetti', async ({ app, page }) => {
  app.world.executions = [execution({ id: 'e-cheer', name: 'Zmywanie' })];
  app.world.personalisation.celebrates = true;
  await app.signIn();
  await app.onScreen('task-Zmywanie').getByRole('button', { name: 'Ukończ', exact: true }).click();
  await expect(page.getByTestId('success-celebration')).toBeVisible();
});
