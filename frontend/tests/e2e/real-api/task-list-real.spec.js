const { test, expect } = require('@playwright/test');
const { createTeamOwner, addTeamMember, createTask, loginThroughUi, openTab } = require('./helpers');

const openTasks = async (page) => openTab(page, /^tasks|^zadani/i);

test.describe('T. Lista zadan', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('T1 wiersz zadania pokazuje nazwe, punkty i czestotliwosc', async ({ page }) => {
    const owner = await createTeamOwner();
    await createTask(owner, { name: 'Odkurzyc salon', points: 30, frequency: 'weekly' });

    await loginThroughUi(page, owner.email);
    await openTasks(page);

    const row = page.locator('.task-card').first().locator('.task-row');
    await expect(row.locator('.task-name')).toHaveText('Odkurzyc salon');
    await expect(row.locator('.task-points')).toContainText('30');
    await expect(row.locator('.task-frequency')).not.toBeEmpty();
  });

  test('T2 odpiecie pokazuje sie dopiero gdy ktos jest przypisany', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    const task = await createTask(owner, { name: 'Umyc okna' });

    await loginThroughUi(page, owner.email);
    await openTasks(page);

    const card = page.locator('.task-card').filter({ hasText: 'Umyc okna' });
    await expect(card.getByRole('button', { name: /step away|odepnij/i })).toHaveCount(0);

    await owner.session.post(`/api/tasks/${task.id}/assign`, { userId: member.id });
    await page.reload();
    await openTasks(page);

    await expect(
      page.locator('.task-card').filter({ hasText: 'Umyc okna' })
        .getByRole('button', { name: /step away|odepnij/i })
    ).toBeVisible();
  });

  test('T3 zwykly czlonek nie oglada zadan juz zrobionych', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);

    const done = await createTask(owner, { name: 'Zrobione zadanie' });
    const open = await createTask(owner, { name: 'Do zrobienia' });

    const other = await addTeamMember(owner, 'Ktos inny');
    await owner.session.post(`/api/tasks/${done.id}/assign`, { userId: other.id });
    await other.session.post(`/api/tasks/${done.id}/complete`);
    await owner.session.post(`/api/tasks/${done.id}/approve`);

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await expect(page.locator('.tasks')).toContainText('Do zrobienia');
    await expect(page.locator('.tasks')).not.toContainText('Zrobione zadanie');
  });

  test('T4 admin zespolu widzi rowniez zadania zakonczone', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    const done = await createTask(owner, { name: 'Zamkniete zadanie' });

    await owner.session.post(`/api/tasks/${done.id}/assign`, { userId: member.id });
    await member.session.post(`/api/tasks/${done.id}/complete`);
    await owner.session.post(`/api/tasks/${done.id}/approve`);

    await loginThroughUi(page, owner.email);
    await openTasks(page);

    await expect(page.locator('.tasks')).toContainText('Zamkniete zadanie');
  });

  test('T5 wykonawca widzi swoje zadanie czekajace na akceptacje', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    const task = await createTask(owner, { name: 'Czeka na akceptacje' });

    await owner.session.post(`/api/tasks/${task.id}/assign`, { userId: member.id });
    await member.session.post(`/api/tasks/${task.id}/complete`);

    await loginThroughUi(page, member.email);
    await openTasks(page);

    await expect(page.locator('.tasks')).toContainText('Czeka na akceptacje');
  });
});
