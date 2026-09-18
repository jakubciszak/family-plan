const { test, expect } = require('./app');
const { CHILD, execution } = require('./fake-api');

const openMember = async (app, page) => {
  await app.signIn();
  await app.goTo('Zespoły');
  await app.onScreen('team-Kowalscy').getByText('Bartek', { exact: true }).click();
};

const card = (app, name) => app.onScreen(`member-task-${name}`);

test.describe('Widok domownika', () => {
  test('admin zespołu wchodzi w domownika z listy członków', async ({ app, page }) => {
    app.world.executions = [
      execution({ id: 'e1', name: 'Zmywanie', status: 'new', assignedUserId: CHILD.id }),
    ];

    await openMember(app, page);

    await expect(page.getByText('Zadania: Bartek')).toBeVisible();
    await expect(card(app, 'Zmywanie')).toBeVisible();
  });

  test('zwykły członek nie ma w co kliknąć', async ({ app, page }) => {
    app.world.teams[0].role = 'member';
    app.world.members[app.world.teams[0].id][0].role = 'member';

    await app.signIn();
    await app.goTo('Zespoły');
    await app.onScreen('team-Kowalscy').getByText('Bartek', { exact: true }).click();

    await expect(page.getByText('Zadania: Bartek')).toHaveCount(0);
  });

  test('do zatwierdzenia trafiają tylko zadania ukończone', async ({ app, page }) => {
    app.world.executions = [
      execution({ id: 'e1', name: 'Zmywanie', status: 'completed', assignedUserId: CHILD.id }),
      execution({ id: 'e2', name: 'Smieci', status: 'new', assignedUserId: CHILD.id }),
    ];

    await openMember(app, page);

    await expect(card(app, 'Zmywanie').getByRole('button', { name: 'Zatwierdź' })).toBeVisible();
    await expect(card(app, 'Smieci').getByRole('button', { name: 'Zatwierdź' })).toHaveCount(0);
  });

  test('zatwierdzenie zdejmuje zadanie z sekcji', async ({ app, page }) => {
    app.world.executions = [
      execution({ id: 'e1', name: 'Zmywanie', status: 'completed', assignedUserId: CHILD.id }),
    ];

    await openMember(app, page);
    await card(app, 'Zmywanie').getByRole('button', { name: 'Zatwierdź' }).click();

    await expect(app.onScreen('member-screen').getByText('Nic nie czeka na zatwierdzenie')).toBeVisible();
  });

  test('odrzucenie wymaga powodu', async ({ app, page }) => {
    app.world.executions = [
      execution({ id: 'e1', name: 'Zmywanie', status: 'completed', assignedUserId: CHILD.id }),
    ];

    await openMember(app, page);
    await card(app, 'Zmywanie').getByRole('button', { name: 'Cofnij' }).click();

    const confirm = page.getByRole('button', { name: 'Cofnij' }).last();
    await expect(confirm).toBeDisabled();

    await app.field('Powód cofnięcia').fill('Talerze dalej brudne');
    await expect(confirm).toBeEnabled();

    await confirm.click();

    expect(app.lastSent('POST', '/api/task-executions/e1/reject').body).toEqual({
      reason: 'Talerze dalej brudne',
    });
  });

  test('pusta sekcja mówi, że nic nie czeka', async ({ app, page }) => {
    await openMember(app, page);

    await expect(app.onScreen('member-screen').getByText('Nic nie czeka na zatwierdzenie')).toBeVisible();
    await expect(page.getByText('Ten członek nie ma żadnych zadań.')).toBeVisible();
  });

  test('strzałka wraca do zespołów', async ({ app, page }) => {
    await openMember(app, page);
    await page.getByRole('button', { name: 'Wróć' }).click();

    await expect(app.onScreen('team-Kowalscy')).toBeVisible();
    await expect(app.onScreen('member-task-Zmywanie')).toHaveCount(0);
  });
});
