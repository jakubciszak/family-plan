const { test, expect } = require('@playwright/test');
const {
  createTeamOwner,
  addTeamMember,
  createTaskType,
  loginThroughUi,
  openTab,
} = require('./helpers');

const TASKS_TAB = /^zadania$|^tasks$/i;

const today = () => new Date().toLocaleDateString('sv');

async function chooseTeam(page, teamName) {
  const picker = page.locator('.task-list-header .md-select').first();

  if (!(await picker.isVisible().catch(() => false))) {
    return;
  }

  await picker.getByRole('combobox').click();
  await page.getByRole('option', { name: teamName }).click();
  await page.locator('.loading').waitFor({ state: 'detached', timeout: 20000 }).catch(() => {});
}

async function openAvailableTypes(page) {
  const collapsed = page.getByTestId('available-tasks-collapsed');
  await collapsed.locator('summary').click();

  return collapsed;
}

test.describe('H. Przydzielanie zadań przez admina', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('H1 admin przypisuje zadanie czlonkowi do zrobienia', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Przydzielajaca');
    const member = await addTeamMember(owner, 'Dziecko');
    const type = await createTaskType(owner, { name: `Odkurzanie ${Date.now()}`, points: 20 });

    await loginThroughUi(page, owner.email);
    await openTab(page, TASKS_TAB);
    await chooseTeam(page, owner.teamName);

    const available = await openAvailableTypes(page);
    await available.getByRole('button', { name: /przydziel|hand out/i }).first().click();

    await page.getByRole('button', { name: /przypisz do zrobienia|give it to them/i }).click();

    await expect(page.getByRole('dialog')).toHaveCount(0);

    const mine = await member.session.get('/api/task-executions/mine');
    const taken = mine.body.executions.filter((execution) => execution.taskTemplateId === type.id);

    expect(taken).toHaveLength(1);
    expect(taken[0].status).toBe('new');
  });

  test('H2 admin dopisuje wykonane zadanie na wskazany dzien', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Dopisujaca');
    const member = await addTeamMember(owner, 'Dziecko');
    await createTaskType(owner, { name: `Zmywanie ${Date.now()}`, points: 25 });

    await loginThroughUi(page, owner.email);
    await openTab(page, TASKS_TAB);
    await chooseTeam(page, owner.teamName);

    const available = await openAvailableTypes(page);
    await available.getByRole('button', { name: /przydziel|hand out/i }).first().click();

    await page.locator('#hand-out-date').fill(today());
    await page.getByRole('button', { name: /dopisz jako zrobione|write it down as done/i }).click();

    await expect(page.getByRole('dialog')).toHaveCount(0);

    const week = await member.session.get('/api/points/week');
    const day = week.body.days.find((entry) => entry.date === today());

    expect(day.points).toBe(25);
  });
});
