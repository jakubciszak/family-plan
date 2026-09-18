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
    const member = await addTeamMember(owner);
    await loginThroughUi(page, member.email);
    await page.locator('.team-selector').click();
    await page.getByRole('option', { name: owner.teamName, exact: true }).click();

    await expect(calendar(page)).toBeVisible();
    await expect(calendar(page).locator('.week-day')).toHaveCount(7);
  });

  test('W2 punkty z dzisiaj trafiaja na dzisiejszy dzien', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await earnPoints(owner, member, 30);

    await loginThroughUi(page, member.email);
    await page.locator('.team-selector').click();
    await page.getByRole('option', { name: owner.teamName, exact: true }).click();

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
    await page.locator('.team-selector').click();
    await page.getByRole('option', { name: owner.teamName, exact: true }).click();

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
    await page.locator('.team-selector').click();
    await page.getByRole('option', { name: owner.teamName, exact: true }).click();

    await expect(calendar(page).locator('.week-day.in-streak')).toHaveCount(0);
    await expect(calendar(page).locator('.week-day.is-today .week-day-points')).toHaveText('10');
  });

  test('W7 admin cofa bonus, ktorego nie uznaje', async ({ page }) => {
    const owner = await createTeamOwner();
    const teams = await owner.session.get('/api/teams');
    const shown = { ...owner, teamId: teams.body.teams[0].id };
    const member = await addTeamMember(shown, 'Dziecko');

    await owner.session.post('/api/bonus-rules', {
      teamId: shown.teamId,
      name: 'Minimum 15 punktow w tygodniu',
      description: 'zbierz 15 punktow',
      bonusPoints: 5,
      ruleType: 'weekly_points_sum',
      ruleConfig: { requiredPoints: 15, accounts: ['tasks'] },
    });

    await earnPoints(shown, member, 16);

    await loginThroughUi(page, owner.email);

    const week = page.getByTestId('member-weeks').getByTestId('week-calendar').first();
    await week.locator('.week-day.is-today').click();

    const detail = page.getByTestId('week-day-detail');
    const bonus = detail.locator('.week-day-task--bonus').first();
    await expect(bonus).toContainText('Minimum 15 punktow w tygodniu');

    await bonus.getByRole('button').click();

    await expect(detail).toContainText(/cofni[eę]ty|taken back/i);
    await expect(week.locator('.week-day.is-today .week-day-bonus')).toHaveCount(0);
    await expect(detail.locator('.week-day-task--bonus button')).toHaveCount(0);
  });

  test('W8 admin zmienia date wykonania i usuwa je z kalendarza', async ({ page }) => {
    const owner = await createTeamOwner();
    const teams = await owner.session.get('/api/teams');
    const shown = { ...owner, teamId: teams.body.teams[0].id };
    const member = await addTeamMember(shown);
    const type = await createTaskType(shown, { name: 'Wykonanie do poprawy', points: 17 });
    const sunday = new Date();
    sunday.setDate(sunday.getDate() - (sunday.getDay() || 7));
    const saturday = new Date(sunday);
    saturday.setDate(saturday.getDate() - 1);
    const oldDay = sunday.toLocaleDateString('sv');
    const newDay = saturday.toLocaleDateString('sv');
    const booked = await owner.session.post(`/api/task-templates/${type.id}/book`, { userId: member.id, doneOn: oldDay });
    expect(booked.status).toBe(201);

    await loginThroughUi(page, owner.email);
    const week = page.getByTestId('member-weeks').getByTestId('week-calendar').first();
    await week.getByRole('button', { name: /poprzedni tydzień|previous week/i }).click();
    await week.locator(`[data-date="${oldDay}"] button`).click();
    await week.getByRole('button', { name: /zmień datę wykonania|change completion date/i }).click();
    await week.getByLabel(/^(data wykonania|completion date)$/i).fill(newDay);
    await week.getByRole('button', { name: /^(zapisz|save)$/i }).click();
    await expect(week.locator(`[data-date="${oldDay}"] .week-day-points`)).toHaveText('0');
    await expect(week.locator(`[data-date="${newDay}"] .week-day-points`)).toHaveText('17');
    await week.locator(`[data-date="${newDay}"] button`).click();
    await week.getByRole('button', { name: /usuń wykonanie|delete execution/i }).click();
    await expect(week.locator(`[data-date="${newDay}"] .week-day-points`)).toHaveText('0');
    await expect(week.getByTestId('week-day-detail')).not.toContainText('Wykonanie do poprawy');
    const balance = await owner.session.get(`/api/users/${member.id}/points`);
    expect(balance.body.balance).toBe(0);
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
