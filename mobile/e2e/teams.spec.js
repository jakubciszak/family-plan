const { test, expect } = require('./app');

const openTeams = async (app) => {
  await app.signIn();
  await app.goTo('Zespoły');
};

test.describe('Zespoły', () => {
  test('lista pokazuje zespół z rolą i członkami', async ({ app, page }) => {
    await openTeams(app);

    await expect(app.onScreen('team-Kowalscy')).toBeVisible();
    await expect(page.getByText('Ala', { exact: true })).toBeVisible();
    await expect(app.onScreen('team-Kowalscy').getByText('Bartek', { exact: true })).toBeVisible();
  });

  test('tworzenie czeka na nazwę', async ({ app, page }) => {
    await openTeams(app);
    await page.getByRole('button', { name: 'Utwórz Zespół' }).click();

    const confirm = page.getByRole('button', { name: 'Utwórz', exact: true });
    await expect(confirm).toBeDisabled();

    await app.field('Nazwa Zespołu').fill('Nowa ekipa');
    await expect(confirm).toBeEnabled();

    await confirm.click();

    await expect(app.onScreen('team-Nowa ekipa')).toBeVisible();
  });

  test('edycja jest wypełniona i zapisuje zmiany', async ({ app, page }) => {
    await openTeams(app);
    await page.getByRole('button', { name: 'Edytuj' }).click();

    await expect(app.field('Nazwa Zespołu')).toHaveValue('Kowalscy');
    await expect(app.field('Opis')).toHaveValue('Nasza rodzina');

    await app.field('Nazwa Zespołu').fill('Kowalscy i spółka');
    await page.getByRole('button', { name: 'Zapisz' }).click();

    await expect(app.onScreen('team-Kowalscy i spółka')).toBeVisible();
    expect(app.lastSent('PUT', `/api/teams/${app.world.teams[0].id}`).body).toMatchObject({
      name: 'Kowalscy i spółka',
    });
  });

  test('zwykły członek nie widzi edycji, zapraszania ani usuwania', async ({ app, page }) => {
    app.world.teams[0].role = 'member';
    app.world.members[app.world.teams[0].id][0].role = 'member';

    await openTeams(app);

    await expect(page.getByRole('button', { name: 'Edytuj' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Zaproś Członka' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Usuń' })).toHaveCount(0);
  });

  test('zaproszenie wysyła adres i wybraną rolę', async ({ app, page }) => {
    await openTeams(app);
    await page.getByRole('button', { name: 'Zaproś Członka' }).click();

    const dialog = page.getByTestId('modal-surface');
    await app.field('Email').fill('ciocia@example.com');
    await dialog.getByRole('button', { name: 'Administrator' }).click();
    await dialog.getByRole('button', { name: 'Wyślij Zaproszenie' }).click();

    await expect(page.getByText('Zaproszenie wysłane pomyślnie')).toBeVisible();
    expect(app.lastSent('POST', `/api/teams/${app.world.teams[0].id}/invite`).body).toEqual({
      email: 'ciocia@example.com',
      role: 'admin',
    });
  });

  test('usunięcie członka da się anulować', async ({ app, page }) => {
    await openTeams(app);
    await page.getByRole('button', { name: 'Usuń' }).click();

    await expect(page.getByText('Czy na pewno chcesz usunąć tego członka?')).toBeVisible();

    await page.getByRole('button', { name: 'Anuluj' }).click();

    await expect(app.onScreen('team-Kowalscy').getByText('Bartek', { exact: true })).toBeVisible();
    expect(app.sent('DELETE', `/api/teams/${app.world.teams[0].id}/members/${app.world.users[1].id}`)).toHaveLength(0);
  });

  test('potwierdzone usunięcie zdejmuje członka', async ({ app, page }) => {
    await openTeams(app);
    await page.getByRole('button', { name: 'Usuń' }).click();
    await page.getByRole('button', { name: 'Usuń' }).last().click();

    await expect(app.onScreen('team-Kowalscy').getByText('Bartek', { exact: true })).toHaveCount(0);
  });

  test('admina nie da się usunąć', async ({ app, page }) => {
    await openTeams(app);

    await expect(page.getByRole('button', { name: 'Usuń' })).toHaveCount(1);
  });

  test('oczekujące zaproszenie da się przyjąć', async ({ app, page }) => {
    app.world.invitations = [
      {
        id: 'i1',
        token: 'tok-1',
        teamId: 'other',
        teamName: 'Sąsiedzi',
        email: 'ala@example.com',
        role: 'member',
        status: 'pending',
        expiresAt: null,
      },
    ];

    await openTeams(app);

    await expect(page.getByText('Sąsiedzi')).toBeVisible();

    await page.getByRole('button', { name: 'Akceptuj' }).click();

    await expect(page.getByText('Zaproszenie zaakceptowane pomyślnie')).toBeVisible();
    await expect(page.getByText('Sąsiedzi')).toHaveCount(0);
  });

  test('błąd wczytywania pokazuje baner', async ({ app, page }) => {
    app.world.failing.add('GET /api/teams');

    await app.signIn();
    await app.goTo('Zespoły');

    await expect(page.getByText('Błąd podczas ładowania zespołów')).toBeVisible();
  });
});
