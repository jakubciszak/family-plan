const { test, expect } = require('@playwright/test');
const { createTeamOwner, addTeamMember, createTaskType, loginThroughUi, openTab } = require('./helpers');

const calendar = (page) => page.getByTestId('week-calendar');

const earnPoints = async (owner, member, points) => {
  const type = await createTaskType(owner, { name: `Zadanie ${points} ${Date.now()}`, points });
  const taken = await member.session.post(`/api/task-templates/${type.id}/take`);
  await member.session.post(`/api/task-executions/${taken.body.id}/complete`);
  await owner.session.post(`/api/task-executions/${taken.body.id}/approve`);
};

test.describe('W. Kalendarz tygodnia', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('W1 ekran glowny pokazuje siedem dni tygodnia', async ({ page }) => {
    const owner = await createTeamOwner();
    await loginThroughUi(page, owner.email);

    await expect(calendar(page)).toBeVisible();
    await expect(calendar(page).locator('.week-day')).toHaveCount(7);
  });

  test('W2 punkty z dzisiaj trafiaja na dzisiejszy dzien', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await earnPoints(owner, member, 30);

    await loginThroughUi(page, member.email);

    const today = calendar(page).locator('.week-day.is-today');
    await expect(today).toHaveCount(1);
    await expect(today.locator('.week-day-points')).toHaveText('30');
    await expect(calendar(page).locator('.week-total')).toContainText('30');
  });

  test('W3 bez reguly serii nie ma paska serii', async ({ page }) => {
    const owner = await createTeamOwner();
    await loginThroughUi(page, owner.email);

    await expect(page.getByTestId('week-streak')).toHaveCount(0);
  });

  test('W4 regula serii pokazuje postep i prog punktowy', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);

    const rule = await owner.session.post('/api/bonus-rules', {
      teamId: owner.teamId,
      name: 'Seria dni',
      description: 'trzy dni po 20 punktow',
      bonusPoints: 50,
      ruleType: 'consecutive_days',
      ruleConfig: { requiredDays: 3, pointsPerDay: 20 },
    });
    expect(rule.status).toBe(201);

    await earnPoints(owner, member, 25);

    await loginThroughUi(page, member.email);

    await expect(page.getByTestId('week-streak')).toContainText('3');
    await expect(page.getByTestId('week-streak')).toContainText('20');
    await expect(calendar(page).locator('.week-day.in-streak')).toHaveCount(1);
  });

  test('W5 dzien ponizej progu nie liczy sie do serii', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);

    await owner.session.post('/api/bonus-rules', {
      teamId: owner.teamId,
      name: 'Seria dni',
      description: 'trzy dni po 50 punktow',
      bonusPoints: 50,
      ruleType: 'consecutive_days',
      ruleConfig: { requiredDays: 3, pointsPerDay: 50 },
    });

    await earnPoints(owner, member, 10);

    await loginThroughUi(page, member.email);

    await expect(calendar(page).locator('.week-day.in-streak')).toHaveCount(0);
    await expect(calendar(page).locator('.week-day.is-today .week-day-points')).toHaveText('10');
  });

  test('W6 admin zespolu zaklada regule serii z formularza', async ({ page }) => {
    const owner = await createTeamOwner();
    await loginThroughUi(page, owner.email);
    await openTab(page, /bonus rules|zasady bonusowe/i);

    await page.getByRole('button', { name: /create bonus rule|utw[oó]rz zasad/i }).first().click();

    const form = page.locator('.bonus-rule-form');
    await form.locator('input[type="text"]').first().fill('Seria z formularza');
    await form.locator('textarea').first().fill('cztery dni po 15 punktow');
    await form.locator('#requiredDays').fill('4');
    await form.locator('#pointsPerDay').fill('15');
    await form.locator('button[type="submit"]').first().click();

    await expect(page.locator('.bonus-rules-container')).toContainText('Seria z formularza');
  });
});
