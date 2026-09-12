const { test, expect } = require('@playwright/test');
const { createTeamOwner, addTeamMember, loginThroughUi, openTab } = require('./helpers');

test.describe('E. Reguly bonusowe', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('E1 admin zespolu tworzy regule z poziomu aplikacji', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Bonusowa');
    await loginThroughUi(page, owner.email);
    await openTab(page, /bonus rules|zasady bonusowe/i);

    await expect(page.locator('.error-message')).toHaveCount(0);

    await page.getByRole('button', { name: /create bonus rule|utw[oó]rz zasad/i }).first().click();

    const form = page.locator('.bonus-rule-form');
    await expect(form.locator('#teamId')).toHaveValue(owner.teamId);

    await form.locator('#name').fill('Piec zadan w miesiacu');
    await form.locator('#description').fill('Nagroda za regularnosc');
    await form.locator('#bonusPoints').fill('50');
    await form.locator('#ruleType').selectOption('monthly_task_count');
    await form.locator('#requiredCount').fill('5');
    await form.locator('button[type="submit"]').click();

    await expect(page.locator('.bonus-rules-list')).toContainText('Piec zadan w miesiacu');
    await expect(page.locator('.error-message')).toHaveCount(0);
  });

  test('E2 czlonek bez uprawnien admina zespolu nie tworzy reguly', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);

    const refused = await member.session.post('/api/bonus-rules', {
      teamId: owner.teamId,
      name: 'Podszywka',
      description: 'opis',
      bonusPoints: 10,
      ruleType: 'monthly_task_count',
      ruleConfig: { requiredCount: 3 },
    });

    expect(refused.status).toBe(403);

    await loginThroughUi(page, member.email);
    await expect(page.getByRole('button', { name: /bonus rules|zasady bonusowe/i })).toHaveCount(0);
  });

  test('E3 reguly innego zespolu nie wyciekaja na liste', async ({ page }) => {
    const owner = await createTeamOwner('Obca rodzina');
    await owner.session.post('/api/bonus-rules', {
      teamId: owner.teamId,
      name: 'Regula obcej rodziny',
      description: 'opis',
      bonusPoints: 10,
      ruleType: 'monthly_task_count',
      ruleConfig: { requiredCount: 3 },
    });

    const outsider = await createTeamOwner('Moja rodzina');
    await loginThroughUi(page, outsider.email);
    await openTab(page, /bonus rules|zasady bonusowe/i);

    await expect(page.locator('body')).not.toContainText('Regula obcej rodziny');
  });
});
