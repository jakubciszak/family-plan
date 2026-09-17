const { test, expect } = require('@playwright/test');
const {
  createTeamOwner,
  addTeamMember,
  createTaskType,
  loginThroughUi,
  openTab,
} = require('./helpers');

const ALLOWANCE_TAB = /kieszonkowe|pocket money|kasa|money/i;

const aWeekBack = () => {
  const day = new Date();
  day.setDate(day.getDate() - 7);

  return day.toLocaleDateString('sv');
};

async function earnPointsLastWeek(owner, member, points) {
  const type = await createTaskType(owner, { name: `Zmywanie ${Date.now()}`, points });
  const taken = await member.session.post(`/api/task-templates/${type.id}/take`);
  await member.session.post(`/api/task-executions/${taken.body.id}/complete`, { doneOn: aWeekBack() });
  await owner.session.post(`/api/task-executions/${taken.body.id}/approve`);
}

async function setRule(owner, { pointsAccount = 'tasks', minimumPoints = 0, rateAmount = 10, ratePerPoints = 1 } = {}) {
  await owner.session.call('/api/allowance/rules', {
    method: 'PUT',
    body: JSON.stringify({ teamId: owner.teamId, pointsAccount, minimumPoints, rateAmount, ratePerPoints }),
  });
}

async function openTeam(page, teamName) {
  const chip = page.locator('.allowance-page__teams').getByRole('button', { name: teamName });

  if (await chip.isVisible().catch(() => false)) {
    await chip.click();
  }
}

const lastMonday = () => {
  const monday = new Date();
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7) - 7);

  return monday.toLocaleDateString('sv');
};

test.describe('K. Kieszonkowe', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('K1 admin ustawia zasady naliczania', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Kieszonkowa');
    await addTeamMember(owner, 'Dziecko');
    await loginThroughUi(page, owner.email);
    await openTab(page, ALLOWANCE_TAB);

    await page.getByRole('tab', { name: /zasady|rules/i }).click();
    await openTeam(page, owner.teamName);

    const rule = page.getByTestId('rule-tasks');
    await rule.locator('#rule-tasks-minimum').fill('50');
    await rule.locator('#rule-tasks-amount').fill('0,25');
    await rule.locator('#rule-tasks-per').fill('1');
    await rule.getByRole('button', { name: /zapisz|save/i }).click();

    await expect(rule.locator('.form-success')).toBeVisible();

    const saved = await owner.session.get(`/api/allowance/rules?teamId=${owner.teamId}`);
    expect(saved.body.rules[0].rateAmount).toBe(25);
    expect(saved.body.rules[0].minimumPoints).toBe(50);
  });

  test('K1b admin nie widzi zakladki Moje, a czlonek nie widzi zakladek', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Bez Mojego');
    const member = await addTeamMember(owner, 'Dziecko');

    await loginThroughUi(page, owner.email);
    await openTab(page, ALLOWANCE_TAB);

    await expect(page.getByRole('tab', { name: /rozliczenia|settlements/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /^moje$|^mine$/i })).toHaveCount(0);

    await loginThroughUi(page, member.email);
    await openTab(page, ALLOWANCE_TAB);

    await expect(page.getByRole('tab')).toHaveCount(0);
    await expect(page.getByTestId('wallet')).toBeVisible();
  });

  test('K2 admin zamyka tydzien i wyplaca czesc', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Rozliczajaca');
    const member = await addTeamMember(owner, 'Dziecko');
    await setRule(owner, { rateAmount: 10 });
    await earnPointsLastWeek(owner, member, 60);

    await loginThroughUi(page, owner.email);
    await openTab(page, ALLOWANCE_TAB);
    await page.getByRole('tab', { name: /rozliczenia|settlements/i }).click();
    await openTeam(page, owner.teamName);
    await page.locator('.allowance-page__members .member-chip').first().click();

    const week = page.getByTestId('allowance-week');
    await expect(week).toBeVisible();

    await expect(week.getByRole('button', { name: /zamknij tydzie|close the week/i })).toBeDisabled();

    await week.getByRole('button', { name: /poprzedni tydzie|previous week/i }).click();
    await week.getByRole('button', { name: /zamknij tydzie|close the week/i }).click();
    await expect(week).toHaveClass(/is-closed/);

    await page.locator('#payout-amount').fill('4,00');
    await page.getByRole('button', { name: /wyp[lł]a[cć] cz[eę][sś][cć]|pay part/i }).click();

    await expect(page.getByTestId('payouts-awaiting')).toBeVisible();

    const wallet = await member.session.get('/api/allowance/wallet');
    expect(wallet.body.pending).toBe(600);
    expect(wallet.body.awaitingConfirmation[0].amount).toBe(400);
  });

  test('K3 dziecko potwierdza wyplate i dopisuje wydatek', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Wyplacajaca');
    const member = await addTeamMember(owner, 'Dziecko');
    await setRule(owner, { rateAmount: 10 });
    await earnPointsLastWeek(owner, member, 60);
    await owner.session.post('/api/allowance/weeks/close', {
      userId: member.id,
      weekStart: lastMonday(),
    });
    await owner.session.post('/api/allowance/payouts', { userId: member.id, amount: 600 });

    await loginThroughUi(page, member.email);
    await openTab(page, ALLOWANCE_TAB);

    await page.getByTestId('payouts-awaiting').getByRole('button').first().click();
    await expect(page.getByTestId('payouts-awaiting')).toHaveCount(0);

    await page.getByRole('button', { name: /dopisz wydatek|add expense/i }).first().click();

    const expense = page.getByTestId('booking-expense');
    await expense.locator('#booking-expense-amount').fill('2,50');
    await expense.locator('#booking-expense-description').fill('Lody');
    await expense.getByRole('button', { name: /dopisz wydatek|add expense/i }).click();

    await expect(page.getByTestId('ledger')).toContainText('Lody');

    const wallet = await member.session.get('/api/allowance/wallet');
    expect(wallet.body.available).toBe(350);
    expect(wallet.body.pending).toBe(0);
  });

  test('K7 karta tygodnia mowi czego brakuje do wyplaty', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Progowa');
    const member = await addTeamMember(owner, 'Dziecko');
    await setRule(owner, { minimumPoints: 50, rateAmount: 10 });
    await earnPointsLastWeek(owner, member, 40);

    await loginThroughUi(page, owner.email);
    await openTab(page, ALLOWANCE_TAB);
    await page.getByRole('tab', { name: /rozliczenia|settlements/i }).click();
    await openTeam(page, owner.teamName);
    await page.locator('.allowance-page__members .member-chip').first().click();

    const week = page.getByTestId('allowance-week');
    await week.getByRole('button', { name: /poprzedni tydzie|previous week/i }).click();

    await expect(week).toContainText(/za ten tydzie|for this week/i);
    await expect(week.locator('.allowance-week__line-hint')).toContainText('10');
  });

  test('K4 dziecko planuje cel i odklada na niego', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Oszczedzajaca');
    const member = await addTeamMember(owner, 'Dziecko');
    await member.session.post('/api/allowance/income', { amount: 5000, description: 'Babcia' });

    await loginThroughUi(page, member.email);
    await openTab(page, ALLOWANCE_TAB);

    await page.getByRole('button', { name: /dodaj cel|add a goal/i }).first().click();

    const form = page.getByTestId('goal-form');
    await form.locator('#goal-name').fill('Hulajnoga');
    await form.locator('#goal-target').fill('40,00');
    await form.getByRole('button', { name: /dodaj cel|add a goal/i }).click();

    const goal = page.getByTestId('goal').first();
    await expect(goal).toContainText('Hulajnoga');

    await goal.locator('input[id^="goal-amount-"]').fill('40,00');
    await goal.getByRole('button', { name: /^od[lł][oó][zż]|^put aside/i }).click();

    await expect(goal).toHaveClass(/is-reached/);

    const wallet = await member.session.get('/api/allowance/wallet');
    expect(wallet.body.available).toBe(1000);
    expect(wallet.body.putAside).toBe(4000);
  });
  test('K6 sekcje portfela da sie zwinac', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Zwijana');
    const member = await addTeamMember(owner, 'Dziecko');

    await loginThroughUi(page, member.email);
    await openTab(page, ALLOWANCE_TAB);

    const goals = page.getByTestId('panel-goals');
    const header = goals.getByRole('button', { expanded: true });

    await expect(goals.getByTestId('goals')).toBeVisible();

    await header.click();

    await expect(goals.getByTestId('goals')).toBeHidden();
    await expect(goals.getByRole('button', { expanded: false })).toBeVisible();
  });
});

test.describe('K. Kieszonkowe po polsku', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');
  test.use({ locale: 'pl-PL' });

  test('K5 ksiega nazywa operacje systemowe po polsku', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Polska');
    const member = await addTeamMember(owner, 'Dziecko');
    await setRule(owner, { rateAmount: 10 });
    await earnPointsLastWeek(owner, member, 60);
    await owner.session.post('/api/allowance/weeks/close', {
      userId: member.id,
      weekStart: lastMonday(),
    });
    const offered = await owner.session.post('/api/allowance/payouts', { userId: member.id, amount: 600 });
    await member.session.post(`/api/allowance/payouts/${offered.body.awaitingConfirmation[0].id}/confirm`);

    await loginThroughUi(page, member.email);
    await openTab(page, ALLOWANCE_TAB);

    const ledger = page.getByTestId('ledger');

    await expect(ledger).toContainText('Wypłata kieszonkowego');
    await expect(ledger).toContainText('Rozliczenie tygodnia');
    await expect(ledger).not.toContainText('Allowance');
  });
});
