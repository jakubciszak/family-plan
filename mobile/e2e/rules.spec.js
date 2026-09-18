const { test, expect } = require('./app');
const { template } = require('./fake-api');

const asSuperAdmin = (app) => {
  app.world.me.role = 'ROLE_ADMIN';
};

test.describe('Typy zadań', () => {
  const open = async (app) => {
    await app.signIn();
    await app.goTo('Typy zadań');
  };

  test('bez administrowanego zespołu nie ma czego definiować', async ({ app, page }) => {
    app.world.teams[0].role = 'member';

    await app.signIn();
    await page.goto('/task-types');

    await expect(page.getByText('Typy zadań definiuje administrator zespołu')).toBeVisible();
  });

  test('tworzenie zapisuje nazwę, punkty i częstotliwość', async ({ app, page }) => {
    await open(app);
    await page.getByRole('button', { name: 'Dodaj typ zadania' }).click();

    await app.field('Nazwa zadania').fill('Odkurzanie');
    await app.field('Punkty').fill('7');
    await page.getByRole('button', { name: 'Zapisz' }).click();

    await expect(app.onScreen('task-type-Odkurzanie')).toBeVisible();
    expect(app.lastSent('POST', '/api/task-templates').body).toMatchObject({
      name: 'Odkurzanie',
      points: 7,
    });
  });

  test('limit z liczbą odsłania pole, bez limitu je chowa', async ({ app, page }) => {
    await open(app);
    await page.getByRole('button', { name: 'Dodaj typ zadania' }).click();

    await expect(app.field('Liczba wykonań')).toHaveCount(0);

    await page.getByRole('button', { name: 'Określona liczba dziennie' }).click();
    await expect(app.field('Liczba wykonań')).toBeVisible();

    await page.getByRole('button', { name: 'Bez ograniczeń', exact: true }).click();
    await expect(app.field('Liczba wykonań')).toHaveCount(0);
  });

  test('edycja jest wypełniona i ma wszystkie pola tworzenia', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie', points: 5, description: 'Po obiedzie' })];

    await open(app);
    await app.onScreen('task-type-Zmywanie').getByRole('button', { name: 'Więcej' }).click();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await expect(page.getByTestId('task-type-actions-surface')).toBeHidden();

    await expect(app.field('Nazwa zadania')).toHaveValue('Zmywanie');
    await expect(app.field('Opis')).toHaveValue('Po obiedzie');
    await expect(app.field('Punkty')).toHaveValue('5');
    await expect(page.getByRole('button', { name: 'Bez ograniczeń', exact: true })).toBeVisible();
  });

  test('wycofanie zdejmuje typ z puli zadań', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie' })];

    await open(app);
    await app.onScreen('task-type-Zmywanie').getByRole('button', { name: 'Więcej' }).click();
    await page.getByRole('button', { name: 'Wycofaj', exact: true }).click();

    await expect(app.onScreen('task-type-Zmywanie').getByText('Wycofany')).toBeVisible();

    await app.goTo('Zadania');

    await expect(app.onScreen('task-Zmywanie')).toHaveCount(0);
  });

  test('usunięcie pyta o potwierdzenie', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie' })];

    await open(app);
    await app.onScreen('task-type-Zmywanie').getByRole('button', { name: 'Więcej' }).click();
    await page.getByRole('button', { name: 'Usuń', exact: true }).click();
    await expect(page.getByTestId('task-type-actions-surface')).toBeHidden();

    await page.getByRole('button', { name: 'Anuluj' }).click();

    await expect(app.onScreen('task-type-Zmywanie')).toBeVisible();
    expect(app.sent('DELETE', '/api/task-templates/a')).toHaveLength(0);
  });
});

test.describe('Zasady bonusowe', () => {
  const open = async (app) => {
    await app.signIn();
    await app.goTo('Zasady bonusowe');
  };

  test('bez administrowanego zespołu brak dostępu', async ({ app, page }) => {
    app.world.teams[0].role = 'member';

    await app.signIn();
    await page.goto('/bonus-rules');

    await expect(page.getByText('Brak dostępu')).toBeVisible();
  });

  test('zmiana typu przestawia widoczne pola konfiguracji', async ({ app, page }) => {
    await open(app);
    await page.getByRole('button', { name: 'Utwórz zasadę bonusową' }).click();

    await expect(app.field('Ile dni z rzędu')).toBeVisible();

    await page.getByRole('button', { name: 'Liczba zadań w miesiącu' }).click();

    await expect(app.field('Ile dni z rzędu')).toHaveCount(0);
    await expect(app.field('Wymagana liczba zadań')).toBeVisible();
  });

  test('tworzenie serii wysyła dni, punkty dzienne i konta', async ({ app, page }) => {
    await open(app);
    await page.getByRole('button', { name: 'Utwórz zasadę bonusową' }).click();

    await app.field('Nazwa zasady').fill('Seria');
    await app.field('Opis').fill('Pięć dni z rzędu');
    await app.field('Ile dni z rzędu').fill('5');
    await app.field('Punktów dziennie').fill('10');
    await page.getByRole('button', { name: 'Zapisz' }).click();

    expect(app.lastSent('POST', '/api/bonus-rules').body).toMatchObject({
      ruleType: 'consecutive_days',
      ruleConfig: { requiredDays: 5, pointsPerDay: 10 },
    });
  });

  test('edycja odsłania typ i konfigurację, wypełnione', async ({ app, page }) => {
    app.world.bonusRules = [
      {
        id: 'r1',
        teamId: app.world.teams[0].id,
        name: 'Seria',
        description: 'Pięć dni',
        bonusPoints: 30,
        type: 'consecutive_days',
        config: { requiredDays: 5, pointsPerDay: 10, accounts: ['tasks'] },
        isActive: true,
      },
    ];

    await open(app);
    await app.onScreen('bonus-rule-Seria').getByRole('button', { name: 'Edytuj' }).click();

    await expect(app.field('Nazwa zasady')).toHaveValue('Seria');
    await expect(app.field('Ile dni z rzędu')).toHaveValue('5');
    await expect(app.field('Punktów dziennie')).toHaveValue('10');
  });

  test('edycja nie pozwala zmienić zespołu', async ({ app, page }) => {
    app.world.teams.push({ ...app.world.teams[0], id: 'team-2', name: 'Druga ekipa' });
    app.world.bonusRules = [
      {
        id: 'r1',
        teamId: app.world.teams[0].id,
        name: 'Seria',
        description: 'Pięć dni',
        bonusPoints: 30,
        type: 'consecutive_days',
        config: { requiredDays: 5, pointsPerDay: 10, accounts: ['tasks'] },
        isActive: true,
      },
    ];

    await open(app);
    await app.onScreen('bonus-rule-Seria').getByRole('button', { name: 'Edytuj' }).click();

    await expect(page.getByText('Zespół', { exact: true })).toHaveCount(0);
  });

  test('zapis edycji wysyła zmieniony typ i konfigurację', async ({ app, page }) => {
    app.world.bonusRules = [
      {
        id: 'r1',
        teamId: app.world.teams[0].id,
        name: 'Seria',
        description: 'Pięć dni',
        bonusPoints: 30,
        type: 'consecutive_days',
        config: { requiredDays: 5, pointsPerDay: 10, accounts: ['tasks'] },
        isActive: true,
      },
    ];

    await open(app);
    await app.onScreen('bonus-rule-Seria').getByRole('button', { name: 'Edytuj' }).click();
    await page.getByRole('button', { name: 'Suma punktów w tygodniu' }).click();
    await app.field('Ile punktów w tygodniu').fill('150');
    await page.getByRole('button', { name: 'Zapisz' }).click();

    expect(app.lastSent('PUT', '/api/bonus-rules/r1').body).toMatchObject({
      ruleType: 'weekly_points_sum',
      ruleConfig: { requiredPoints: 150 },
    });
  });

  test('wycofanie i przywrócenie zasady', async ({ app, page }) => {
    app.world.bonusRules = [
      {
        id: 'r1',
        teamId: app.world.teams[0].id,
        name: 'Seria',
        description: 'Pięć dni',
        bonusPoints: 30,
        type: 'consecutive_days',
        config: { requiredDays: 5, pointsPerDay: 10, accounts: ['tasks'] },
        isActive: true,
      },
    ];

    await open(app);
    const card = app.onScreen('bonus-rule-Seria');

    await card.getByRole('button', { name: 'Dezaktywuj' }).click();
    await expect(card.getByText('Nieaktywna')).toBeVisible();

    await card.getByRole('button', { name: 'Aktywuj' }).click();
    await expect(card.getByText('Nieaktywna')).toHaveCount(0);
  });
});

test.describe('Reguły zmian statusów', () => {
  const open = async (app) => {
    asSuperAdmin(app);
    await app.signIn();
    await app.goTo('Reguły Zmian Statusów');
  };

  test('bez roli administratora aplikacji brak dostępu', async ({ app, page }) => {
    await app.signIn();

    await expect(page.getByRole('button', { name: 'Reguły', exact: true })).toHaveCount(0);
  });

  test('tworzenie przerwy wysyła pilnowane zadanie i dni', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie' })];

    await open(app);
    await page.getByRole('button', { name: 'Utwórz Regułę' }).click();

    await app.field('Nazwa Reguły').fill('Przerwa');
    await app.field('Opis').fill('Dwa dni przerwy');
    await page.getByRole('button', { name: 'Zmywanie' }).click();
    await app.field('Dni Przerwy').fill('2');
    await page.getByRole('button', { name: 'Zapisz' }).click();

    expect(app.lastSent('POST', '/api/status-change-rules').body).toMatchObject({
      taskTemplateId: 'a',
      conditionType: 'last_execution_cooldown',
      conditionConfig: { cooldownDays: 2 },
    });
  });

  test('lista zadań wymaganych pomija zadanie pilnowane', async ({ app, page }) => {
    app.world.templates = [
      template({ id: 'a', name: 'Zmywanie' }),
      template({ id: 'b', name: 'Smieci' }),
    ];

    await open(app);
    await page.getByRole('button', { name: 'Utwórz Regułę' }).click();

    await page.getByRole('button', { name: 'Zmywanie' }).click();
    await page.getByRole('button', { name: 'Inne Zadanie Wykonane Dzisiaj' }).click();

    const dialog = page.getByTestId('modal-surface');

    await expect(dialog.getByRole('button', { name: 'Smieci', exact: true })).toHaveCount(2);
    await expect(dialog.getByRole('button', { name: 'Zmywanie', exact: true })).toHaveCount(1);
  });

  test('edycja odsłania typ warunku i konfigurację', async ({ app, page }) => {
    app.world.templates = [template({ id: 'a', name: 'Zmywanie' })];
    app.world.statusChangeRules = [
      {
        id: 'r1',
        taskTemplateId: 'a',
        name: 'Przerwa',
        description: 'Dwa dni',
        conditionType: 'last_execution_cooldown',
        config: { cooldownDays: 2 },
        isActive: true,
      },
    ];

    await open(app);
    await app.onScreen('status-rule-Przerwa').getByRole('button', { name: 'Edytuj' }).click();

    await expect(app.field('Dni Przerwy')).toHaveValue('2');
    await expect(page.getByRole('button', { name: 'Inne Zadanie Wykonane Dzisiaj' })).toBeVisible();
  });

  test('przełączenie na wymagane zadanie bez wyboru blokuje zapis', async ({ app, page }) => {
    app.world.templates = [
      template({ id: 'a', name: 'Zmywanie' }),
      template({ id: 'b', name: 'Smieci' }),
    ];
    app.world.statusChangeRules = [
      {
        id: 'r1',
        taskTemplateId: 'a',
        name: 'Przerwa',
        description: 'Dwa dni',
        conditionType: 'last_execution_cooldown',
        config: { cooldownDays: 2 },
        isActive: true,
      },
    ];

    await open(app);
    await app.onScreen('status-rule-Przerwa').getByRole('button', { name: 'Edytuj' }).click();
    await page.getByRole('button', { name: 'Inne Zadanie Wykonane Dzisiaj' }).click();

    const save = page.getByRole('button', { name: 'Zapisz' });
    await expect(save).toBeDisabled();

    await page.getByTestId('modal-surface').getByRole('button', { name: 'Smieci' }).click();
    await expect(save).toBeEnabled();
  });
});
