const { test, expect } = require('@playwright/test');
const {
  APP,
  PASSWORD,
  unique,
  createAccount,
  createTeamOwner,
  addTeamMember,
  createTask,
  loginThroughUi,
  openTab,
} = require('./helpers');

const openTeamDetails = async (page) => {
  await openTab(page, /^teams|^zespo/i);
  await page.locator('.team-card').first().click();
  await page.waitForTimeout(600);
};

test.describe('C. Zespoly i zaproszenia', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('C1 kazdy moze utworzyc zespol i jest w nim adminem', async ({ page }) => {
    const account = await createAccount();
    await loginThroughUi(page, account.email);
    await openTab(page, /^teams|^zespo/i);

    await page.getByRole('button', { name: /create team|utw[oó]rz zesp/i }).first().click();
    await page.locator('form input[type="text"]').first().fill('Rodzina Nowa');
    await page.locator('form button[type="submit"]').first().click();

    await expect(page.locator('.team-card')).toContainText('Rodzina Nowa');

    const teams = await account.session.get('/api/teams');
    expect(teams.body.teams[0].role).toBe('admin');
  });

  test('C2 zaproszenie pojawia sie na liscie z linkiem do skopiowania', async ({ page }) => {
    const owner = await createTeamOwner();
    await loginThroughUi(page, owner.email);
    await openTeamDetails(page);

    await page.getByRole('button', { name: /invite|zapro/i }).first().click();
    await page.locator('.invite-form input[type="email"]').fill(unique('c2'));
    await page.locator('.invite-form button[type="submit"]').click();

    await expect(page.locator('.invitation-card')).toHaveCount(1);
    await expect(page.locator('.invitation-link-input')).toHaveValue(/\?invite=/);
  });

  test('C3 osoba bez konta rejestruje sie z linku i trafia do zespolu', async ({ page }) => {
    const owner = await createTeamOwner();
    const invitedEmail = unique('c3');
    const invite = await owner.session.post(`/api/teams/${owner.teamId}/invite`, {
      email: invitedEmail,
      role: 'member',
    });

    await page.goto(`${APP}/?invite=${invite.body.invitation.token}`);
    await page.locator('input[type="text"]').first().fill('Zaproszony');
    await page.locator('input[type="email"]').first().fill(invitedEmail);
    await page.locator('input[type="password"]').first().fill(PASSWORD);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForTimeout(1500);

    if (await page.locator('input[type="password"]').count()) {
      await loginThroughUi(page, invitedEmail);
    }

    await expect.poll(
      async () => {
        const members = await owner.session.get(`/api/teams/${owner.teamId}/members`);
        return members.body.members.map((m) => m.userEmail);
      },
      { timeout: 15000 }
    ).toContain(invitedEmail);
  });

  test('C4 osoba z kontem loguje sie z linku i dolacza do zespolu', async ({ page }) => {
    const owner = await createTeamOwner();
    const invited = await createAccount({ name: 'Ma Konto' });
    const invite = await owner.session.post(`/api/teams/${owner.teamId}/invite`, {
      email: invited.email,
      role: 'member',
    });

    await page.goto(`${APP}/?invite=${invite.body.invitation.token}`);
    const loginLink = page.getByText(/log ?in|zaloguj/i).last();
    if (await loginLink.count()) await loginLink.click();
    await page.locator('input[type="email"]').first().fill(invited.email);
    await page.locator('input[type="password"]').first().fill(PASSWORD);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForSelector('.app-header', { timeout: 15000 });
    await page.waitForTimeout(1500);

    await expect.poll(
      async () => {
        const members = await owner.session.get(`/api/teams/${owner.teamId}/members`);
        return members.body.members.map((m) => m.userEmail);
      },
      { timeout: 15000 }
    ).toContain(invited.email);
  });

  test('C5 czlonek widoczny na liscie i mozna go usunac', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);

    await loginThroughUi(page, owner.email);
    await openTeamDetails(page);
    await expect(page.locator('.members-list')).toContainText(member.email);

    page.once('dialog', (d) => d.accept());
    await page.getByRole('button', { name: /remove|usu[nń]/i }).first().click();
    await expect(page.locator('.members-list')).not.toContainText(member.email);
  });

  test('C6 zwykly czlonek nie widzi tokenow zaproszen', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    await owner.session.post(`/api/teams/${owner.teamId}/invite`, { email: unique('c6'), role: 'member' });

    await loginThroughUi(page, member.email);
    await openTeamDetails(page);

    await expect(page.locator('.invitation-card')).toHaveCount(0);
  });
});

test.describe('D. Zadania i punkty', () => {
  test.skip(!process.env.REAL_API, 'REAL_API not enabled');

  test('D1 admin zespolu widzi przycisk tworzenia zadania', async ({ page }) => {
    const owner = await createTeamOwner();
    await loginThroughUi(page, owner.email);
    await openTab(page, /^tasks|^zadani/i);

    await expect(page.getByRole('button', { name: /create task|utw[oó]rz zadanie/i })).toBeVisible();
  });

  test('D2 zwykly czlonek nie widzi przycisku tworzenia zadania', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);

    await loginThroughUi(page, member.email);
    await openTab(page, /^tasks|^zadani/i);

    await expect(page.getByRole('button', { name: /create task|utw[oó]rz zadanie/i })).toHaveCount(0);
  });

  test('D3 zadanie utworzone z formularza pojawia sie na liscie', async ({ page }) => {
    const owner = await createTeamOwner();
    await loginThroughUi(page, owner.email);
    await openTab(page, /^tasks|^zadani/i);

    await page.getByRole('button', { name: /create task|utw[oó]rz zadanie/i }).first().click();
    const form = page.locator('.task-create-form');
    await form.locator('input[type="text"]').first().fill('Odkurzyc salon');
    const points = form.locator('input[type="number"]').first();
    if (await points.count()) await points.fill('30');
    await form.locator('button[type="submit"]').first().click();

    await expect(page.locator('.tasks')).toContainText('Odkurzyc salon');
  });

  test('D4 zadania sa widoczne na ekranie glownym po zalogowaniu', async ({ page }) => {
    const owner = await createTeamOwner();
    await createTask(owner, { name: 'Wyniesc smieci' });

    await loginThroughUi(page, owner.email);

    await expect(page.locator('.tasks')).toContainText('Wyniesc smieci');
  });

  test('D5 zatwierdzenie zadania nalicza punkty wykonawcy', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    const task = await createTask(owner, { name: 'Umyc naczynia', points: 30 });

    await owner.session.post(`/api/tasks/${task.id}/assign`, { userId: member.id });
    await member.session.post(`/api/tasks/${task.id}/complete`);
    await owner.session.post(`/api/tasks/${task.id}/approve`);

    await loginThroughUi(page, member.email);
    await expect(page.locator('.user-points')).toContainText('30');
  });

  test('D6 punkty widoczne na ekranie konta', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    const task = await createTask(owner, { points: 25 });

    await owner.session.post(`/api/tasks/${task.id}/assign`, { userId: member.id });
    await member.session.post(`/api/tasks/${task.id}/complete`);
    await owner.session.post(`/api/tasks/${task.id}/approve`);

    await loginThroughUi(page, member.email);
    await openTab(page, /my account|moje konto/i);

    await expect(page.locator('.account-details')).toContainText('25');
  });

  test('D7 zwykly czlonek nie moze zatwierdzic wlasnego zadania', async ({ page }) => {
    const owner = await createTeamOwner();
    const member = await addTeamMember(owner);
    const task = await createTask(owner);

    await owner.session.post(`/api/tasks/${task.id}/assign`, { userId: member.id });
    await member.session.post(`/api/tasks/${task.id}/complete`);
    const refused = await member.session.post(`/api/tasks/${task.id}/approve`);

    expect(refused.status).toBe(403);
  });
});
