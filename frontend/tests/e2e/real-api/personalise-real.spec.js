const { test, expect } = require('@playwright/test');
const { createTeamOwner, addTeamMember, loginThroughUi } = require('./helpers');

const openLook = async (page) => {
  await page.locator('.user-face').click();
  await expect(page.getByRole('heading', { name: /mój wygląd|my look/i })).toBeVisible();
};

test.describe('P. Personalizacja', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('P1 dziecko wybiera kolor i cala aplikacja go bierze', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Kolorowa');
    const member = await addTeamMember(owner, 'Dziecko');

    await loginThroughUi(page, member.email);
    await openLook(page);

    await page.getByTestId('colour-picker').getByRole('button', { name: '#8e24aa' }).click();

    await expect.poll(async () => {
      const saved = await member.session.get('/api/personalisation');
      return saved.body.theme;
    }).toBe('#8e24aa');

    const painted = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue('--md-sys-color-primary').trim()
    );

    expect(painted).not.toBe('');
    expect(painted.toLowerCase()).not.toBe('#2e7d5b');
  });

  test('P2 pseudonim zastepuje imie w pasku', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Nazywajaca');
    const member = await addTeamMember(owner, 'Dziecko');

    await loginThroughUi(page, member.email);
    await openLook(page);

    await page.locator('#own-nickname').fill('Rakieta');
    await page.locator('#own-nickname').blur();

    await expect(page.locator('.user-welcome')).toContainText('Rakieta');

    const saved = await member.session.get('/api/personalisation');
    expect(saved.body.nickname).toBe('Rakieta');
  });

  test('P3 avatar da sie zmienic i widac go u innych', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Avatarowa');
    const member = await addTeamMember(owner, 'Dziecko');

    await loginThroughUi(page, member.email);
    await openLook(page);

    const picker = page.getByTestId('avatar-picker');
    await picker.locator('.avatar-picker__seeds button').nth(3).click();

    await expect.poll(async () => {
      const saved = await member.session.get('/api/personalisation');
      return saved.body.avatar.seed;
    }).not.toBe(member.id);

    const roster = await owner.session.get(`/api/teams/${owner.teamId}/members`);
    const child = roster.body.members.find((entry) => entry.userId === member.id);

    expect(child.face.avatar.seed).toBeTruthy();
  });

  test('P4 pasek na dole slucha sie ustawien', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Paskowa');
    const member = await addTeamMember(owner, 'Dziecko');
    await member.session.call('/api/personalisation', {
      method: 'PUT',
      body: JSON.stringify({ navigation: ['tasks', 'settings'] }),
    });

    await loginThroughUi(page, member.email);

    const nav = page.locator('.app-nav');

    await expect(nav.getByRole('button', { name: /^zadania$|^tasks$/i })).toBeVisible();
    await expect(nav.getByRole('button', { name: /kieszonkowe|pocket money/i })).toHaveCount(0);
  });

  test('P5 strona glowna uklada sie tak jak kazano', async ({ page }) => {
    const owner = await createTeamOwner('Rodzina Ukladana');
    const member = await addTeamMember(owner, 'Dziecko');
    await member.session.call('/api/personalisation', {
      method: 'PUT',
      body: JSON.stringify({ home: ['standings'] }),
    });

    await loginThroughUi(page, member.email);

    await expect(page.getByTestId('week-calendar')).toHaveCount(0);
    await expect(page.locator('.task-list-container .task-section').first()).toBeVisible();
  });
});
