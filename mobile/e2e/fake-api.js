const ADMIN = {
  id: '11111111-1111-4111-8111-111111111111',
  name: 'Ala Kowalska',
  email: 'ala@example.com',
  role: 'ROLE_USER',
};

const CHILD = {
  id: '22222222-2222-4222-8222-222222222222',
  name: 'Bartek Kowalski',
  email: 'bartek@example.com',
  role: 'ROLE_USER',
};

const TEAM = {
  id: '33333333-3333-4333-8333-333333333333',
  name: 'Kowalscy',
  description: 'Nasza rodzina',
  createdBy: ADMIN.id,
  createdAt: '2026-01-01T10:00:00+00:00',
  updatedAt: null,
  role: 'admin',
};

const face = (name) => ({
  nickname: null,
  theme: '#2e7d5b',
  avatar: { style: 'bottts', seed: name, pictureId: null },
});

const personalisation = (userId) => ({
  userId,
  nickname: null,
  theme: '#2e7d5b',
  themeMode: 'system',
  language: 'pl',
  avatar: { style: 'bottts', seed: 'ala', pictureId: null },
  backdrop: { pattern: 'plain', pictureId: null, dimming: 0 },
  home: ['week', 'tasks', 'standings', 'members'],
  navigation: ['tasks', 'teams', 'allowance', 'personalise', 'task-types', 'bonus-rules', 'account', 'action-plans', 'settings'],
  celebrates: true,
  makesSound: false,
  places: {
    home: ['week', 'tasks', 'standings', 'members'],
    navigation: ['tasks', 'teams', 'allowance', 'personalise', 'task-types', 'bonus-rules', 'account', 'action-plans', 'settings'],
  },
  choices: {
    themeModes: ['light', 'dark', 'system'],
    languages: ['pl', 'en'],
  },
});

const template = (over = {}) => ({
  id: over.id ?? `t-${Math.random().toString(16).slice(2, 10)}`,
  teamId: TEAM.id,
  name: 'Zmywanie',
  description: '',
  points: 5,
  frequency: 'daily',
  executionLimit: { type: 'unlimited' },
  remaining: null,
  isActive: true,
  ...over,
});

const execution = (over = {}) => ({
  id: over.id ?? `e-${Math.random().toString(16).slice(2, 10)}`,
  taskTemplateId: null,
  name: 'Zmywanie',
  description: null,
  points: 5,
  status: 'new',
  assignedUserId: ADMIN.id,
  assignedUserName: ADMIN.name,
  completedAt: null,
  approvedAt: null,
  createdAt: '2026-01-02T10:00:00+00:00',
  rejectionReason: null,
  ...over,
});

const emptyWeek = () => ({
  weekStart: '2026-01-05',
  total: 0,
  bonusTotal: 0,
  days: Array.from({ length: 7 }, (_, offset) => ({
    date: new Date(Date.UTC(2026, 0, 5 + offset)).toISOString().slice(0, 10),
    points: 0,
    bonus: 0,
    reachedThreshold: false,
    inStreak: false,
    isToday: offset === 0,
  })),
  streak: null,
});

const allowanceWeek = () => ({
  weekStart: '2026-01-05',
  currency: 'PLN',
  days: Array.from({ length: 7 }, (_, offset) => ({
    date: new Date(Date.UTC(2026, 0, 5 + offset)).toISOString().slice(0, 10),
    points: 0,
    bonus: 0,
    isToday: offset === 0,
  })),
  points: 0,
  bonusPoints: 0,
  isOver: false,
  expected: { total: 0, lines: [] },
  closure: null,
});

/**
 * Everything a scenario can bend. Tests reach in and change this before the
 * screen loads, then assert on what the screen did with it.
 */
const freshWorld = () => ({
  me: { ...ADMIN },
  users: [ADMIN, CHILD],
  teams: [{ ...TEAM }],
  members: {
    [TEAM.id]: [
      {
        id: 'm-1',
        userId: ADMIN.id,
        userName: ADMIN.name,
        givenName: 'Ala',
        userEmail: ADMIN.email,
        role: 'admin',
        joinedAt: '2026-01-01T10:00:00+00:00',
        face: face('ala'),
      },
      {
        id: 'm-2',
        userId: CHILD.id,
        userName: CHILD.name,
        givenName: 'Bartek',
        userEmail: CHILD.email,
        role: 'member',
        joinedAt: '2026-01-01T10:00:00+00:00',
        face: face('bartek'),
      },
    ],
  },
  invitations: [],
  teamInvitations: {},
  actionPlans: [],
  templates: [],
  executions: [],
  bonusRules: [],
  statusChangeRules: [],
  notifications: [],
  notificationPolicies: {
    channels: ['email', 'sms', 'in_app'],
    events: [
      { event: 'task_completed', channels: ['email'], defaultChannels: ['email'], configurable: true },
      { event: 'task_approved', channels: ['email'], defaultChannels: ['email'], configurable: true },
      { event: 'user_welcome', channels: ['email'], defaultChannels: ['email'], configurable: true },
      { event: 'account_activation', channels: ['email'], defaultChannels: ['email'], configurable: false },
    ],
  },
  userSettings: { preferences: [] },
  personalisation: personalisation(ADMIN.id),
  allowanceRules: { rules: [], currency: 'PLN' },
  wallet: {
    currency: 'PLN',
    pending: 0,
    available: 0,
    putAside: 0,
    earned: 0,
    otherIncome: 0,
    spent: 0,
    awaitingConfirmation: [],
  },
  goals: { currency: 'PLN', weeklyPace: 0, available: 0, goals: [] },
  ledger: { currency: 'PLN', bookings: [] },
  week: emptyWeek(),
  allowanceWeek: allowanceWeek(),
  registration: { activationRequired: false, taken: [] },
  loginFails: false,
  failing: new Set(),
  calls: [],
  pictures: [],
});

const CORS = {
  'access-control-allow-origin': '*',
  'access-control-allow-methods': 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
  'access-control-allow-headers': 'Authorization,Content-Type,Accept',
};

const json = (route, body, status = 200) =>
  route.fulfill({
    status,
    contentType: 'application/json',
    headers: CORS,
    body: JSON.stringify(body ?? {}),
  });

const byId = (list, id) => list.find((one) => one.id === id);

const drop = (list, id) => {
  const at = list.findIndex((one) => one.id === id);

  if (at >= 0) {
    list.splice(at, 1);
  }
};

const nextId = (prefix) => `${prefix}-${Math.random().toString(16).slice(2, 10)}`;

/**
 * Routes the app's calls to an in-memory world. Anything not handled here comes
 * back as 501 with the path, so a forgotten endpoint fails loudly in the test
 * rather than hanging the screen on a pending request.
 */
const installApi = async (page, world) => {
  await page.route('**/api/**', async (route) => {
    const request = route.request();

    if (request.method() === 'OPTIONS') {
      return route.fulfill({ status: 204, headers: CORS, body: '' });
    }

    const url = new URL(request.url());
    const path = url.pathname;
    const method = request.method();
    const body = ['POST', 'PUT', 'PATCH'].includes(method) ? request.postDataJSON() ?? {} : {};

    world.calls.push({ method, path, body, query: Object.fromEntries(url.searchParams) });

    if (world.failing.has(`${method} ${path}`)) {
      return json(route, { error: 'Nope' }, 500);
    }

    if (method === 'GET' && path.startsWith('/api/personalisation/pictures/')) {
      const picture = world.pictures.find((one) => path.endsWith(`/${one.id}`));
      if (picture) return route.fulfill({ status: 200, headers: CORS, contentType: picture.data.slice(5, picture.data.indexOf(';')), body: Buffer.from(picture.data.split(',')[1], 'base64') });
    }

    const segments = path.replace(/^\/api\//, '').split('/');
    const answer = await route$(world, { method, path, segments, body, query: url.searchParams });

    if (answer === undefined) {
      return json(route, { error: `fake-api has no route for ${method} ${path}` }, 501);
    }

    return json(route, answer.body, answer.status ?? 200);
  });
};

const ok = (body, status) => ({ body, status });

const route$ = async (world, call) => {
  const { method, path, segments, body, query } = call;
  const [head, ...rest] = segments;

  if (path === '/api/auth/token' && method === 'POST') {
    return world.loginFails
      ? ok({ error: 'Invalid credentials' }, 401)
      : ok({ token: 'fake.access.token', refresh_token: 'fake-refresh', user: world.me });
  }

  if (path === '/api/auth/token/refresh' && method === 'POST') {
    return ok({ token: 'fake.access.token', refresh_token: 'fake-refresh' });
  }

  if (path === '/api/auth/me' && method === 'GET') {
    return ok(world.me);
  }

  if (path === '/api/auth/register' && method === 'POST') {
    if (world.registration.taken.includes(body.email)) {
      return ok({ error: 'User with this email already exists' }, 400);
    }

    return ok(
      {
        message: 'Registered',
        activationRequired: world.registration.activationRequired,
        id: nextId('u'),
      },
      201
    );
  }

  if (head === 'personalisation' && rest[0] === 'pictures') {
    if (method === 'POST') {
      const picture = { id: nextId('picture'), ...body };
      world.pictures.push(picture);
      return ok(picture, 201);
    }
    return ok({ pictures: world.pictures });
  }

  if (head === 'personalisation') {
    if (method === 'GET') {
      return ok(world.personalisation);
    }

    if (method === 'PUT') {
      const { avatar, backdrop, ...flat } = body;
      Object.assign(world.personalisation, flat);
      Object.assign(world.personalisation.avatar, avatar ?? {});
      Object.assign(world.personalisation.backdrop, backdrop ?? {});

      return ok(world.personalisation);
    }
  }

  if (head === 'teams') {
    return teams(world, { method, rest, body });
  }

  if (head === 'action-plans') {
    if (!rest.length && method === 'GET') return ok({ plans: world.actionPlans });
    if (!rest.length && method === 'POST') {
      const plan = { ...body, id: nextId('plan'), canManage: true, canChangeScope: true };
      world.actionPlans.push(plan);
      return ok(plan, 201);
    }
    const plan = world.actionPlans.find((item) => item.id === rest[0]);
    if (!plan) return ok({ error: 'actionPlans.notFound' }, 404);
    if (!plan.canManage) return ok({ error: 'Forbidden' }, 403);
    if (world.templates.some((item) => item.actionPlanId === plan.id) && (method === 'DELETE' || body.teamId !== plan.teamId)) return ok({ error: 'actionPlans.linkedPlan' }, 400);
    if (method === 'PUT') { Object.assign(plan, body); return ok(plan); }
    if (method === 'DELETE') { world.actionPlans = world.actionPlans.filter((item) => item.id !== plan.id); return ok(null, 204); }
  }

  if (head === 'task-templates') {
    return templates(world, { method, rest, body });
  }

  if (head === 'task-executions') {
    return executions(world, { method, rest, body });
  }

  if (head === 'points') {
    if (rest[0] === 'accounts') {
      return ok({ accounts: [{ kind: 'tasks' }, { kind: 'bonuses' }] });
    }

    if (rest[0] === 'week') {
      return ok(world.week);
    }

    if (rest[0] === 'day') {
      return ok({ date: query.get('date'), userId: world.me.id, tasks: [], total: 0, bonus: 0 });
    }

    if (rest[0] === 'leaderboard') {
      return ok({ standings: world.standings ?? [] });
    }
  }

  if (head === 'bonus-rules') {
    return rules(world.bonusRules, { method, rest, body }, fromBonusBody);
  }

  if (head === 'status-change-rules') {
    return rules(world.statusChangeRules, { method, rest, body }, fromStatusBody);
  }

  if (head === 'notifications') {
    if (method === 'GET') {
      const unread = world.notifications.filter((one) => !one.readAt);

      return ok({
        notifications: query.get('unread') ? unread : world.notifications,
        unreadCount: unread.length,
      });
    }

    if (method === 'POST' && rest[1] === 'read') {
      const held = byId(world.notifications, rest[0]);

      if (held) {
        held.readAt = '2026-01-02T12:00:00+00:00';
      }

      return ok({ notification: held, unreadCount: 0 });
    }
  }

  if (head === 'notification-policies') {
    if (method === 'GET') {
      return ok(world.notificationPolicies);
    }

    if (method === 'PUT') {
      const held = world.notificationPolicies.events.find((one) => one.event === rest[0]);

      if (held) {
        held.channels = body.channels;
      }

      return ok({ message: 'Notification policy updated' });
    }
  }

  if (head === 'user-settings') {
    if (method === 'GET') {
      return ok(world.userSettings);
    }

    if (method === 'PUT') {
      world.userSettings = {
        preferences: [{ type: body.preference_type, options: body.options }],
      };

      return ok({ status: 'success' });
    }
  }

  if (head === 'allowance') {
    return allowance(world, { method, rest, body });
  }

  if (head === 'users' && rest[1] === 'points') {
    return ok({ balance: 0 });
  }

  return undefined;
};

const teams = (world, { method, rest, body }) => {
  if (rest.length === 0) {
    if (method === 'GET') {
      return ok({ teams: world.teams });
    }

    if (method === 'POST') {
      const made = {
        id: nextId('team'),
        name: body.name,
        description: body.description ?? null,
        createdBy: world.me.id,
        createdAt: '2026-01-02T10:00:00+00:00',
        updatedAt: null,
        role: 'admin',
      };
      world.teams.push(made);
      world.members[made.id] = [];

      return ok(made, 201);
    }
  }

  if (rest[0] === 'invitations') {
    if (rest.length === 1 && method === 'GET') {
      return ok({ invitations: world.invitations });
    }

    if (rest[2] === 'accept' && method === 'POST') {
      drop(world.invitations, byId(world.invitations, rest[1])?.id ?? '');
      world.invitations = world.invitations.filter((one) => one.token !== rest[1]);

      return ok({ message: 'Invitation accepted successfully' });
    }

    if (rest.length === 2 && method === 'GET') {
      const held = world.invitations.find((one) => one.token === rest[1]);

      return held ? ok({ email: held.email }) : ok({ error: 'Invitation not found' }, 404);
    }
  }

  const team = byId(world.teams, rest[0]);

  if (!team) {
    return undefined;
  }

  if (rest.length === 1 && method === 'PUT') {
    team.name = body.name;
    team.description = body.description ?? null;

    return ok({ message: 'Team updated successfully' });
  }

  if (rest[1] === 'members') {
    if (method === 'GET') {
      return ok({ members: world.members[team.id] ?? [], invitations: world.teamInvitations[team.id] ?? [] });
    }

    if (method === 'DELETE') {
      world.members[team.id] = (world.members[team.id] ?? []).filter(
        (one) => one.userId !== rest[2]
      );

      return ok({ message: 'Member removed' });
    }
  }

  if (rest[1] === 'invite' && method === 'POST') {
    world.invitations.push({
      id: nextId('inv'),
      token: nextId('tok'),
      teamId: team.id,
      teamName: team.name,
      email: body.email,
      role: body.role,
      status: 'pending',
      expiresAt: null,
    });

    return ok({ message: 'Invitation sent' }, 201);
  }

  return undefined;
};

const templates = (world, { method, rest, body }) => {
  if (rest.length === 0) {
    if (method === 'GET') {
      return ok({ templates: world.templates.map((item) => ({
        ...item,
        isAvailable: item.isActive && item.remaining !== 0 && !world.executions.some(
          (run) => run.taskTemplateId === item.id && run.assignedUserId && ['new', 'pending', 'rejected'].includes(run.status)
        ),
      })) });
    }

    if (method === 'POST') {
      const made = template({ id: nextId('tpl'), ...body });
      world.templates.push(made);

      return ok(made, 201);
    }
  }

  const held = byId(world.templates, rest[0]);

  if (!held) {
    return undefined;
  }

  if (rest.length === 1) {
    if (method === 'PUT') {
      Object.assign(held, body);

      return ok(held);
    }

    if (method === 'DELETE') {
      drop(world.templates, held.id);

      return ok({ message: 'Deleted' });
    }
  }

  if (method === 'POST' && rest[1] === 'activate') {
    held.isActive = true;

    return ok(held);
  }

  if (method === 'POST' && rest[1] === 'deactivate') {
    held.isActive = false;

    return ok(held);
  }

  if (method === 'POST' && rest[1] === 'take') {
    world.executions.push(
      execution({
        id: nextId('exe'),
        taskTemplateId: held.id,
        name: held.name,
        points: held.points,
        status: 'new',
      })
    );

    return ok({ message: 'Taken' }, 201);
  }

  return undefined;
};

const executions = (world, { method, rest, body }) => {
  if (rest[0] === 'awaiting-approval' && method === 'GET') {
    return ok({ executions: world.executions.filter((one) => one.status === 'completed') });
  }

  if (rest[0] === 'mine' && method === 'GET') {
    return ok({
      executions: world.executions.filter((one) => one.assignedUserId === world.me.id),
    });
  }

  if (rest[0] === 'of' && method === 'GET') {
    return ok({
      executions: world.executions.filter((one) => one.assignedUserId === rest[1]),
    });
  }

  const held = byId(world.executions, rest[0]);

  if (!held || method !== 'POST') {
    return undefined;
  }

  if (rest[1] === 'complete') {
    held.status = 'completed';
    held.completedAt = body.doneOn ?? '2026-01-02T12:00:00+00:00';
    held.rejectionReason = null;

    return ok(held);
  }

  if (rest[1] === 'approve') {
    held.status = 'approved';
    held.approvedAt = '2026-01-02T13:00:00+00:00';

    return ok(held);
  }

  if (rest[1] === 'reject') {
    held.status = 'rejected';
    held.rejectionReason = body.reason;

    return ok(held);
  }

  if (rest[1] === 'abandon') {
    drop(world.executions, held.id);

    return ok({ message: 'Abandoned' });
  }

  return undefined;
};

/**
 * The backend builds config through a value object whose toArray() always emits
 * every key, nulling the ones the type does not use. Mirroring that matters: a
 * fake that echoes only what the client sent hides bugs in reading it back.
 */
const bonusConfig = (type, sent = {}) => ({
  type,
  taskTemplateId: sent.taskTemplateId ?? null,
  requiredDays: sent.requiredDays ?? null,
  requiredCount: sent.requiredCount ?? null,
  pointsPerDay: sent.requiredDays === undefined ? null : sent.pointsPerDay ?? 1,
  requiredPoints: sent.requiredPoints ?? null,
  accounts: sent.accounts ?? [],
});

const statusConfig = (type, sent = {}) => ({
  type,
  requiredTaskTemplateId: sent.requiredTaskTemplateId ?? null,
  cooldownDays: sent.cooldownDays ?? null,
});

const fromBonusBody = (body, held) => ({
  ...(held ?? {}),
  teamId: body.teamId ?? held?.teamId ?? TEAM.id,
  name: body.name,
  description: body.description,
  bonusPoints: body.bonusPoints,
  type: body.ruleType,
  config: bonusConfig(body.ruleType, body.ruleConfig),
  createdAt: held?.createdAt ?? '2026-01-01T10:00:00+00:00',
  updatedAt: held ? '2026-01-02T10:00:00+00:00' : null,
});

const fromStatusBody = (body, held) => ({
  ...(held ?? {}),
  taskTemplateId: body.taskTemplateId ?? held?.taskTemplateId,
  name: body.name,
  description: body.description,
  conditionType: body.conditionType,
  config: statusConfig(body.conditionType, body.conditionConfig),
  createdAt: held?.createdAt ?? '2026-01-01T10:00:00+00:00',
  updatedAt: held ? '2026-01-02T10:00:00+00:00' : null,
});

const rules = (list, { method, rest, body }, shape) => {
  if (rest.length === 0) {
    if (method === 'GET') {
      return ok({ rules: list });
    }

    if (method === 'POST') {
      list.push({ id: nextId('rule'), isActive: true, ...shape(body, null) });

      return ok({ message: 'Rule created successfully' }, 201);
    }
  }

  const held = byId(list, rest[0]);

  if (!held) {
    return undefined;
  }

  if (rest.length === 1 && method === 'PUT') {
    Object.assign(held, shape(body, held));

    return ok({ message: 'Rule updated successfully' });
  }

  if (method === 'POST' && rest[1] === 'activate') {
    held.isActive = true;

    return ok({ message: 'Rule activated' });
  }

  if (method === 'POST' && rest[1] === 'deactivate') {
    held.isActive = false;

    return ok({ message: 'Rule deactivated' });
  }

  return undefined;
};

const allowance = (world, { method, rest, body }) => {
  if (rest[0] === 'weeks' && method === 'POST') {
    world.allowanceWeek.closure = rest[1] === 'close' ? { closedAt: new Date().toISOString(), total: world.allowanceWeek.expected.total, lines: structuredClone(world.allowanceWeek.expected.lines) } : null;
    return ok(world.allowanceWeek);
  }
  if (['income', 'expenses'].includes(rest[0]) && method === 'POST') {
    const expense = rest[0] === 'expenses';
    world.wallet.available += expense ? -body.amount : body.amount;
    world.ledger.bookings.unshift(booking({ type: expense ? 'expense' : 'income', amount: body.amount, description: body.description }));
    return ok({});
  }
  if (rest[0] === 'goals' && method === 'POST' && rest.length === 1) {
    world.goals.goals.push({ id: nextId('goal'), name: body.name, target: body.target, saved: 0, percent: 0, wantedBy: body.wantedBy ?? null, weeksLeft: null, perWeekNeeded: null, weeksAtThisPace: null, reached: false, missing: body.target });
    return ok({}, 201);
  }
  if (rest[0] === 'goals' && method === 'POST' && rest[2] === 'put-aside') {
    const goal = world.goals.goals.find((one) => one.id === rest[1]);
    goal.saved += body.amount;
    goal.missing = goal.target - goal.saved;
    goal.reached = goal.missing <= 0;
    world.wallet.available -= body.amount;
    world.wallet.putAside += body.amount;
    return ok({});
  }
  if (rest[0] === 'goals' && method === 'POST' && rest[2] === 'spend') {
    const goal = world.goals.goals.find(one => one.id === rest[1]);
    if (!Number.isInteger(body.amount) || body.amount <= 0 || body.amount > goal.saved) return ok({ error: 'Invalid goal spending' }, 400);
    goal.saved -= body.amount;
    world.wallet.putAside -= body.amount;
    goal.reached = goal.saved >= goal.target;
    return ok(world.goals);
  }
  if (rest[0] === 'payouts' && method === 'POST') {
    if (rest[2] === 'confirm') {
      world.wallet.awaitingConfirmation = world.wallet.awaitingConfirmation.filter((one) => one.id !== rest[1]);
    }
    return ok({});
  }
  if (rest[0] === 'wallet' && method === 'GET') {
    return ok(world.wallet);
  }

  if (rest[0] === 'goals' && method === 'GET') {
    return ok(world.goals);
  }

  if (rest[0] === 'ledger' && method === 'GET') {
    return ok(world.ledger);
  }

  if (rest[0] === 'rules' && method === 'GET') {
    return ok(world.allowanceRules);
  }

  if (rest[0] === 'rules' && method === 'PUT') {
    const held = world.allowanceRules.rules.find(
      (one) => one.pointsAccount === body.pointsAccount
    );

    world.allowanceRules.rules = [
      ...world.allowanceRules.rules.filter((one) => one !== held),
      { id: held?.id ?? nextId('arule'), isActive: true, ...body },
    ];

    return ok({ message: 'Rule set' });
  }

  if (rest[0] === 'weeks' && method === 'GET') {
    return ok(world.allowanceWeek);
  }

  return undefined;
};

const booking = (over = {}) => ({
  id: over.id ?? nextId('bk'),
  type: 'income',
  amount: 1000,
  description: 'Od babci',
  reference: null,
  context: [],
  bookedAt: '2026-01-02T10:00:00+00:00',
  entries: [{ account: 'available', goalId: null, amount: 1000 }],
  ...over,
});

const expectedLine = (over = {}) => ({
  pointsAccount: 'tasks',
  points: 0,
  minimumPoints: 0,
  amount: 0,
  reachedMinimum: false,
  ...over,
});

module.exports = {
  ADMIN,
  CHILD,
  TEAM,
  booking,
  execution,
  expectedLine,
  template,
  freshWorld,
  installApi,
};
