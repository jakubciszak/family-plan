const { test, expect } = require('@playwright/test');
const { createTeamOwner, addTeamMember, createTaskType, loginThroughUi, openTab } = require('./helpers');

const openTasks = async (page) => openTab(page, /^tasks$|^zadania$/i);

const availableSection = (page) => page.getByTestId('available-tasks');
const mySection = (page) => page.getByTestId('my-tasks');
const approvalSection = (page) => page.getByTestId('approval-queue');

test.describe('T. Lista zadan i moje zadania', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('T1 dostepny typ pokazuje nazwe, punkty, limit i pozostale wykonania', async ({ page }) => {
    const owner = await createTeamOwner();
    await createTaskType(owner, {
      name: 'Odkurzyc salon',
      points: 30,
      executionLimit: { type: 'per_day', count: 3 },
    });

    await loginThroughUi(page, owner.email);
    await openTasks(page);

    const row = availableSection(page).locator('.task-card').filter({ hasText: 'Odkurzyc salon' }).locator('.task-row');
    await expect(row.locator('.task-name')).toHaveText('Odkurzyc salon');
    await expect(row.locator('.task-points')).toContainText('30');
    await expect(row.locator('.task-frequency')).toContainText('3');
    await expect(row.locator('.task-remaining')).toContainText('3');
  });

  test('T2 wziecie typu przenosi zadanie do moich i zdejmuje jedno z puli', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await createTaskType(owner, { name: 'Wyniesc smieci', executionLimit: { type: 'per_day', count: 2 } });

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await expect(mySection(page)).not.toContainText('Wyniesc smieci');
    await availableSection(page).locator('.task-card').filter({ hasText: 'Wyniesc smieci' })
      .getByRole('button', { name: /take this task|wez to zadanie|weź to zadanie/i }).click();

    await expect(mySection(page).locator('.task-card').filter({ hasText: 'Wyniesc smieci' })).toBeVisible();
    await expect(
      availableSection(page).locator('.task-card').filter({ hasText: 'Wyniesc smieci' }).locator('.task-remaining')
    ).toContainText('1');
  });

  test('T3 jednorazowy typ znika z listy gdy ktos go wezmie', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await createTaskType(owner, { name: 'Posprzatac piwnice', executionLimit: { type: 'once' } });

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await availableSection(page).locator('.task-card').filter({ hasText: 'Posprzatac piwnice' })
      .getByRole('button', { name: /take this task|wez to zadanie|weź to zadanie/i }).click();

    await expect(mySection(page).locator('.task-card').filter({ hasText: 'Posprzatac piwnice' })).toBeVisible();
    await expect(availableSection(page)).not.toContainText('Posprzatac piwnice');
  });

  test('T4 oddanie zadania zwraca je do puli', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await createTaskType(owner, { name: 'Umyc okna', executionLimit: { type: 'once' } });

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await availableSection(page).locator('.task-card').filter({ hasText: 'Umyc okna' })
      .getByRole('button', { name: /take this task|wez to zadanie|weź to zadanie/i }).click();
    await expect(mySection(page).locator('.task-card').filter({ hasText: 'Umyc okna' })).toBeVisible();

    await mySection(page).locator('.task-card').filter({ hasText: 'Umyc okna' })
      .getByRole('button', { name: /give it back|oddaj do puli/i }).click();

    await expect(mySection(page)).not.toContainText('Umyc okna');
    await expect(availableSection(page).locator('.task-card').filter({ hasText: 'Umyc okna' })).toBeVisible();
  });

  test('T5 pelny cykl: wziecie, wykonanie, akceptacja i punkty', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await createTaskType(owner, { name: 'Zmywanie po obiedzie', points: 40 });

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await availableSection(page).locator('.task-card').filter({ hasText: 'Zmywanie po obiedzie' })
      .getByRole('button', { name: /take this task|wez to zadanie|weź to zadanie/i }).click();
    await mySection(page).locator('.task-card').filter({ hasText: 'Zmywanie po obiedzie' })
      .getByRole('button', { name: /^complete$|^ukończ$|^ukoncz$/i }).click();

    await expect(mySection(page).locator('.task-card').filter({ hasText: 'Zmywanie po obiedzie' }))
      .toContainText(/waiting for approval|czeka na zatwierdzenie/i);

    await loginThroughUi(page, owner.email);
    await openTasks(page);

    await expect(approvalSection(page)).toBeVisible({ timeout: 30000 });
    const queued = approvalSection(page).locator('.task-card').filter({ hasText: 'Zmywanie po obiedzie' });
    await expect(queued).toContainText('Dziecko', { timeout: 15000 });
    await queued.getByRole('button', { name: /^approve$|^zatwierdź$|^zatwierdz$/i }).click();

    await expect(approvalSection(page)).not.toContainText('Zmywanie po obiedzie');

    const points = await owner.session.get(`/api/users/${member.id}/points`);
    expect(points.body.balance).toBe(40);
  });

  test('T6 zwykly czlonek nie widzi sekcji zatwierdzania', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await createTaskType(owner, { name: 'Cokolwiek' });

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await expect(approvalSection(page)).toHaveCount(0);
  });

  test('T7 szukajka zawęża pulę zadań do wpisanej części nazwy', async ({ page }) => {
    const owner = await createTeamOwner();
    const teams = await owner.session.get('/api/teams');
    const shown = { ...owner, teamId: teams.body.teams[0].id };
    await createTaskType(shown, { name: 'Odkurzyc salon' });
    await createTaskType(shown, { name: 'Wyniesc smieci' });

    await loginThroughUi(page, owner.email);
    await openTasks(page);
    await page.getByTestId('available-tasks-collapsed').locator('summary').click();

    const search = availableSection(page).locator('#available-search');
    await expect(availableSection(page).locator('.task-card')).toHaveCount(2);

    await search.fill('kurz');

    await expect(availableSection(page).locator('.task-card')).toHaveCount(1);
    await expect(availableSection(page).locator('.task-card')).toContainText('Odkurzyc salon');

    await search.fill('nic takiego');

    await expect(availableSection(page).locator('.task-card')).toHaveCount(0);
    await expect(availableSection(page).locator('.empty-hint')).toBeVisible();

    await search.fill('');

    await expect(availableSection(page).locator('.task-card')).toHaveCount(2);
  });
});
