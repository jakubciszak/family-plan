const API = process.env.API_BASE_URL || 'http://localhost:8080';
const APP = process.env.REAL_APP_URL || process.env.BASE_URL || 'http://localhost:3000';
const ADMIN_EMAIL = process.env.E2E_EMAIL || 'admin@familyplan.local';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD || 'FamilyPlanE2E123!';
const PASSWORD = 'TestoweHaslo123';

let seq = 0;
const unique = (prefix) => `${prefix}+${Date.now()}${seq++}@example.com`;

class ApiSession {
  constructor() {
    this.cookies = [];
  }

  async call(path, options = {}) {
    const response = await fetch(API + path, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
        ...(this.cookies.length ? { cookie: this.cookies.join('; ') } : {}),
      },
    });

    (response.headers.getSetCookie?.() || []).forEach((cookie) => {
      this.cookies.push(cookie.split(';')[0]);
    });

    const body = await response.text();
    return { status: response.status, body: body ? JSON.parse(body) : null };
  }

  post(path, payload) {
    return this.call(path, { method: 'POST', body: JSON.stringify(payload ?? {}) });
  }

  get(path) {
    return this.call(path);
  }
}

async function createAccount({ name = 'Tester', email = unique('user'), password = PASSWORD } = {}) {
  const session = new ApiSession();
  await session.post('/api/auth/register', { name, email, password });
  await session.post('/api/auth/login', { email, password });
  const me = await session.get('/api/auth/me');
  return { session, email, password, name, id: me.body?.id };
}

async function createTeamOwner(teamName = 'Rodzina') {
  const owner = await createAccount({ name: 'Rodzic' });
  const team = await owner.session.post('/api/teams', { name: teamName, description: 'zespol testowy' });
  return { ...owner, teamId: team.body.id, teamName };
}

async function addTeamMember(owner, memberName = 'Dziecko') {
  const member = await createAccount({ name: memberName });
  const invite = await owner.session.post(`/api/teams/${owner.teamId}/invite`, {
    email: member.email,
    role: 'member',
  });
  const token = invite.body?.invitation?.token;
  await member.session.post(`/api/teams/invitations/${token}/accept`);
  return { ...member, token };
}

async function createTaskType(owner, { name = 'Typ testowy', points = 30, frequency = 'daily', executionLimit = { type: 'unlimited' }, description = 'opis' } = {}) {
  const response = await owner.session.post('/api/task-templates', {
    teamId: owner.teamId,
    name,
    description,
    points,
    frequency,
    executionLimit,
  });
  return response.body;
}

async function loginThroughUi(page, email, password = PASSWORD) {
  await page.context().clearCookies();
  await page.goto(APP, { waitUntil: 'domcontentloaded' });
  await page.locator('input[type="email"]').first().fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.locator('button[type="submit"]').first().click();
  await page.waitForSelector('.app-header', { timeout: 15000 });
  await page.locator('.loading').waitFor({ state: 'detached', timeout: 20000 }).catch(() => {});
}

async function openTab(page, pattern) {
  const hamburger = page.locator('.hamburger-menu');
  if (await hamburger.isVisible().catch(() => false)) {
    await hamburger.click();
    await page.waitForTimeout(300);
  }

  const button = page.getByRole('button', { name: pattern }).first();
  await button.click();
  await page.waitForTimeout(300);
  await page.locator('.loading').waitFor({ state: 'detached', timeout: 20000 }).catch(() => {});
}

async function horizontalOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

module.exports = {
  API,
  APP,
  ADMIN_EMAIL,
  ADMIN_PASSWORD,
  PASSWORD,
  ApiSession,
  unique,
  createAccount,
  createTeamOwner,
  addTeamMember,
  createTaskType,
  loginThroughUi,
  openTab,
  horizontalOverflow,
};
