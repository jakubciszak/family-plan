const { test, expect } = require('./app');
const { TEAM } = require('./fake-api');
const DAY = '2026-09-21';
const button = (page, name) => page.getByRole('button', { name, exact: true });
const event = (world, over = {}) => ({ id: '11111111-aaaa-4111-8111-111111111111', occurrenceKey: DAY + 'T09:00', title: 'Plan lekcji', description: 'Przynieś zeszyt', location: 'Szkoła', ownerId: world.me.id, teamId: TEAM.id, visibility: 'PRIVATE', start: DAY + 'T07:00:00Z', end: DAY + 'T08:00:00Z', allDay: false, timeZone: 'Europe/Warsaw', participantIds: [world.me.id, world.users[1].id], participants: [{ personId: world.me.id, status: 'INCLUDED' }, { personId: world.users[1].id, status: 'INCLUDED' }], tags: [{ id: 'school', name: 'Szkoła', color: '#226a4c' }], blocksTime: true, recurring: true, canEdit: true, canChangeParticipation: true, participation: 'INCLUDED', version: 3, ...over });
const install = async (page, world) => {
  const state = { events: [], tags: [{ id: 'school', name: 'Szkoła', color: '#226a4c', scope: 'TEAM', teamId: TEAM.id, canEdit: true }, { id: 'work', name: 'Praca', color: '#325f99', scope: 'PERSONAL', teamId: null, canEdit: true }], busy: [{ kind: 'busy', personId: world.users[1].id, start: DAY + 'T10:00:00Z', end: DAY + 'T11:00:00Z' }], calls: [], failCreate: false, conflict: false, stale: false, exceptions: {}, needsReset: false, failTagCreate: false, tagCreateGate: null };
  await page.route('**/api/day-planning/**', async (route) => {
    const req = route.request(), url = new URL(req.url()), path = url.pathname, method = req.method(), body = req.postDataJSON();
    state.calls.push({ method, path, body, query: url.searchParams, headers: req.headers() });
    const send = (data, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });
    const coverage = { from: url.searchParams.get('from'), to: url.searchParams.get('to'), complete: true };
    if (path.endsWith('/calendar')) { const tags = url.searchParams.getAll('tagIds[]'); return send({ events: state.events.filter((item) => !tags.length || item.tags.some((tag) => tags.includes(tag.id))), busy: url.searchParams.has('teamId') ? state.busy : [], coverage }); }
    if (path.endsWith('/planning/suggestions')) return send({ slots: [{ start: DAY + 'T13:30:00Z', end: DAY + 'T14:30:00Z' }], personIds: body.personIds, busy: state.busy, coverage });
    if (path.endsWith('/tags') && method === 'GET') return send({ tags: state.tags });
    if (path.endsWith('/tags') && method === 'POST') { if (state.failTagCreate) return send({}, 503); if (state.tagCreateGate) await state.tagCreateGate; const tag = { ...body, id: 'new-tag', canEdit: true }; state.tags.push(tag); return send(tag, 201); }
    if (path.includes('/tags/') && method === 'PATCH') { const tag = state.tags.find((item) => path.endsWith(item.id)); Object.assign(tag, body); return send(tag); }
    if (path.includes('/tags/') && method === 'DELETE') { state.tags = state.tags.filter((tag) => !path.endsWith(tag.id)); return route.fulfill({ status: 204 }); }
    if (path.endsWith('/events') && method === 'POST') {
      if (state.failCreate) return send({}, 503);
      if (state.conflict && !body.conflictConfirmation) return send({ code: 'planning_conflict', conflicts: state.busy, confirmationToken: 'confirmed-slot' }, 409);
      state.events.push(event(world, { id: 'new-event', title: body.title, recurring: !!body.recurrence, allDay: body.schedule.kind === 'ALL_DAY', tags: state.tags.filter((tag) => body.tagIds.includes(tag.id)), ...(body.schedule.kind === 'ALL_DAY' ? { start: body.schedule.startDate + 'T00:00:00+02:00', end: body.schedule.endDate + 'T00:00:00+02:00' } : {}) })); return send({ ...body, id: 'new-event', version: 1 }, 201);
    }
    const current = state.events.find((item) => path.includes(item.id));
    if (method === 'GET' && path.includes('/occurrences/')) return send(current);
    if (method === 'GET' && current) return send({ ...current, schedule: { kind: 'TIMED', localStart: DAY + 'T09:00', durationMinutes: 60, timeZone: 'Europe/Warsaw' }, recurrence: { frequency: 'WEEKLY', interval: 1, byDay: [1], until: null, count: null }, tagIds: current.tags.map((tag) => tag.id), exceptions: state.exceptions });
    if (path.endsWith('/participation/me')) { current.participation = body.status; return send(body); }
    if (method === 'PUT' && path.includes('/exceptions/')) { if (body.cancelled) state.events = state.events.filter((item) => item.id !== current.id); else Object.assign(current, body.changes); return send({ ...current, version: current.version + 1 }); }
    if (method === 'DELETE' && path.includes('/exceptions/')) { delete state.exceptions[decodeURIComponent(path.split('/').at(-1))]; return send({ ...current, schedule: { kind: 'TIMED', localStart: DAY + 'T09:00', durationMinutes: 60, timeZone: 'Europe/Warsaw' }, recurrence: null, tagIds: current.tags.map((tag) => tag.id), exceptions: state.exceptions }); }
    if (method === 'PATCH') { if (state.needsReset && !body.resetExceptions) return send({ code: 'exceptions_reset_required' }, 409); if (state.stale) return send({}, 412); Object.assign(current, body); return send({ ...current, version: current.version + 1 }); }
    if (method === 'DELETE') { state.events = state.events.filter((item) => item.id !== current.id); return route.fulfill({ status: 204 }); }
    return send({}, 404);
  });
  return state;
};
const open = async (app) => { await app.signIn(); await app.goTo('Plan dnia'); await app.field('Data').fill(DAY); };
const eventTeam = async (page) => { await button(page, 'Zespół wydarzenia').click(); await button(page, TEAM.name).click(); };
const moreOptions = (page) => button(page, 'Więcej opcji').click();
const displayZone = async (page, zone) => { await page.getByRole('button', { name: /^Strefa wyświetlania: / }).click(); await button(page, zone).click(); };
const person = (page, name) => page.getByRole('checkbox', { name: `Pokaż osobę: ${name}` });
/** The first opaque colour behind a control, past the translucent hover layer; chips fill what is chosen and outline the rest. */
const fill = (locator) => locator.evaluate((node) => {
  for (let element = node; element; element = element.parentElement) {
    const color = getComputedStyle(element).backgroundColor;
    if (color.startsWith('rgb(')) return color;
  }
  return '';
});

test('keeps busy placeholders under tag filtering without offering private details', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)];
  await open(app); await page.getByText('Plan zespołu', { exact: true }).click();
  await expect(person(page, 'Bartek Kowalski')).toBeChecked(); await expect(button(page, 'Zespół')).toHaveCount(0);
  await expect(page.getByTestId('day-busy')).toContainText('Zajęty · Bartek Kowalski'); await page.getByText('Praca', { exact: true }).click();
  await expect(page.getByTestId('day-event-' + state.events[0].id)).toHaveCount(0); await expect(page.getByTestId('day-busy')).toBeVisible();
  await page.getByTestId('day-busy').click(); expect(state.calls.filter((call) => call.path.includes('/occurrences/'))).toHaveLength(0);
});

test('creates an overnight team recurrence with stable idempotency across failure and confirmed conflict', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
  await app.field('Tytuł wydarzenia').fill('Nocna podróż'); await eventTeam(page); await moreOptions(page); await app.field('Strefa czasowa IANA').fill('Europe/Warsaw'); await app.field('Godzina początku').fill('23:00'); await app.field('Data końca').fill('2026-09-22'); await app.field('Godzina końca').fill('01:00');
  await button(page, 'Powtarzanie').click(); await button(page, 'Co kilka tygodni').click(); await page.getByRole('checkbox', { name: 'Zaproś: Bartek Kowalski' }).click();
  state.failCreate = true; await button(page, 'Zapisz').click(); await expect(page.getByRole('alert')).toContainText('Sprawdź połączenie');
  state.failCreate = false; state.conflict = true; await button(page, 'Zapisz').click(); await button(page, 'Zapisz mimo kolizji').click();
  await expect(page.getByTestId('day-event-new-event')).toContainText('Nocna podróż');
  const saves = state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events'));
  expect(new Set(saves.map((call) => call.headers['idempotency-key'])).size).toBe(1);
  expect(saves.at(-1).body).toMatchObject({ teamId: TEAM.id, visibility: 'TEAM', participantIds: [world.me.id, world.users[1].id], schedule: { localStart: DAY + 'T23:00', durationMinutes: 120 }, recurrence: { frequency: 'WEEKLY', interval: 1, byDay: [1] }, conflictConfirmation: 'confirmed-slot' });
});

test('lets a team admin write an event only into somebody else\'s calendar', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
  await app.field('Tytuł wydarzenia').fill('Dentysta'); await eventTeam(page); await page.getByRole('checkbox', { name: 'Biorę udział' }).click();
  await button(page, 'Zapisz').click(); await expect(page.getByRole('alert')).toContainText('przynajmniej jednego uczestnika');
  await page.getByRole('checkbox', { name: 'Zaproś: Bartek Kowalski' }).click(); await button(page, 'Zapisz').click();
  await expect(page.getByTestId('day-event-new-event')).toContainText('Dentysta');
  const save = state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events')).at(-1);
  expect(save.body).toMatchObject({ ownerParticipates: false, participantIds: [world.users[1].id] });
});

test('names the person who wrote an event into my calendar without joining it', async ({ app, page, world }) => {
  const state = await install(page, world);
  state.events = [event(world, { ownerId: world.users[1].id, canEdit: false, participantIds: [world.me.id], participants: [{ personId: world.me.id, status: 'INCLUDED' }] })];
  await open(app); await page.getByTestId('day-event-' + state.events[0].id).click();
  await expect(page.getByTestId('day-event-detail')).toContainText('Wpisał(a): Bartek Kowalski');
});

test('edits a single occurrence with version checks and preserves invitee details after declining', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; await open(app); await page.getByTestId('day-event-' + state.events[0].id).click();
  await expect(button(page, 'Tylko ten dzień')).toBeVisible(); await button(page, 'Edytuj').click();
  await expect(button(page, 'Powtarzanie')).toHaveCount(0); await app.field('Tytuł wydarzenia').fill('Wycieczka szkolna'); await button(page, 'Zapisz').click(); await expect(page.getByText('Wycieczka szkolna', { exact: true })).toBeVisible();
  const change = state.calls.find((call) => call.method === 'PUT' && call.path.includes('/exceptions/')); expect(change.headers['if-match']).toBe('"3"'); expect(change.body.changes.title).toBe('Wycieczka szkolna'); expect(change.body.changes).not.toHaveProperty('recurrence'); expect(change.body.changes).not.toHaveProperty('teamId');
  state.events[0].canEdit = false; state.events[0].ownerId = world.users[1].id; await page.getByTestId('day-event-' + state.events[0].id).click(); await expect(button(page, 'Edytuj')).toHaveCount(0); await button(page, 'Zrezygnuj z udziału').click();
  await expect(page.getByTestId('day-event-' + state.events[0].id)).toContainText('Udział odrzucony'); await page.getByTestId('day-event-' + state.events[0].id).click(); await expect(page.getByText('Przynieś zeszyt')).toBeVisible(); await button(page, 'Wróć do udziału').click();
  expect(state.calls.filter((call) => call.path.endsWith('/participation/me')).map((call) => call.body)).toEqual([{ status: 'DECLINED', occurrenceKey: DAY + 'T09:00' }, { status: 'INCLUDED', occurrenceKey: DAY + 'T09:00' }]);
});

test('includes the author and all calendars in planning and prefills a selected slot', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await page.getByText('Praca', { exact: true }).click(); await page.getByText('Znajdź termin', { exact: true }).click();
  await expect(person(page, 'Bartek Kowalski')).toBeChecked(); await expect(person(page, world.me.name)).toBeDisabled();
  await app.field('Ostatni dzień poszukiwań').fill('2026-09-27'); await displayZone(page, 'Europe/Warsaw'); await button(page, 'Znajdź wspólny termin').click(); await expect(page.getByTestId('day-suggestions')).toBeVisible();
  const query = state.calls.find((call) => call.path.endsWith('/planning/suggestions')).body; expect(query.personIds).toContain(world.me.id); expect(query.personIds).toContain(world.users[1].id); expect(query).not.toHaveProperty('tagIds');
  await button(page, 'Wybierz termin').click(); await expect(app.field('Godzina początku')).toHaveValue('15:30'); await expect(app.field('Godzina końca')).toHaveValue('16:30'); await expect(page.getByRole('checkbox', { name: 'Zaproś: Bartek Kowalski' })).toBeChecked();
});

test('manages tags and reloads stale versions while preserving rejected drafts', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; await open(app); await button(page, 'Zarządzaj tagami').click(); await app.field('Nazwa tagu').fill('Muzyka'); await button(page, 'Zapisz').click(); await expect(page.getByText('Muzyka · Osobisty')).toBeVisible();
  await button(page, 'Archiwizuj tag: Muzyka').click(); await button(page, 'Potwierdź archiwizację').click(); await expect(page.getByText('Muzyka · Osobisty')).toHaveCount(0); await button(page, 'Wróć do kalendarza').click();
  await page.getByTestId('day-event-' + state.events[0].id).click(); await button(page, 'Cała seria').click(); await button(page, 'Edytuj').click(); state.stale = true; await app.field('Tytuł wydarzenia').fill('Nowy plan'); await button(page, 'Zapisz').click(); await expect(page.getByRole('alert')).toContainText('Ktoś zmienił'); await expect(app.field('Tytuł wydarzenia')).toHaveValue('Nowy plan');
  await button(page, 'Wczytaj aktualną wersję').click(); await expect(app.field('Tytuł wydarzenia')).toHaveValue('Plan lekcji');
});

test('all-day dates have an exclusive API end and the screen fits a narrow dark phone', async ({ app, page, world }, testInfo) => {
  const state = await install(page, world); world.personalisation.themeMode = 'dark'; const errors = []; page.on('pageerror', (error) => errors.push(error.message)); await page.setViewportSize({ width: 320, height: 740 });
  await open(app); await button(page, 'Nowe wydarzenie').click(); await app.field('Tytuł wydarzenia').fill('Wolny dzień'); await page.getByRole('checkbox', { name: 'Cały dzień', exact: true }).click(); await button(page, 'Zapisz').click(); await expect(page.getByTestId('day-event-new-event')).toBeVisible();
  expect(state.calls.find((call) => call.path.endsWith('/events') && call.method === 'POST').body.schedule).toMatchObject({ kind: 'ALL_DAY', startDate: DAY, endDate: '2026-09-22' }); expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320); expect(errors).toEqual([]); await page.getByTestId('day-event-new-event').scrollIntoViewIfNeeded(); await page.screenshot({ path: testInfo.outputPath('day-plan-mobile-dark.png') });
});


test('shows cancelled exceptions and requires confirmation before resetting a series schedule', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; state.exceptions = { '2026-09-28T09:00': { cancelled: true } };
  await open(app); await page.getByTestId('day-event-' + state.events[0].id).click(); await button(page, 'Cała seria').click(); await button(page, 'Edytuj').click();
  await expect(page.getByText('2026-09-28 09:00 · Anulowane')).toBeVisible(); await button(page, 'Przywróć według serii').click();
  await expect(page.getByText('2026-09-28 09:00 · Anulowane')).toHaveCount(0);
  const restored = state.calls.find((call) => call.method === 'DELETE' && call.path.includes('/exceptions/'));
  expect(restored.headers['if-match']).toBe('"3"');
  state.needsReset = true; await app.field('Godzina początku').fill('08:30'); await button(page, 'Zapisz').click();
  await expect(page.getByText('Ta zmiana usunie wszystkie wyjątki tej serii, w tym przeniesione i anulowane dni. Pojedyncze wystąpienia będą znów wynikać z nowego harmonogramu.')).toBeVisible();
  await button(page, 'Usuń wyjątki i zapisz').click(); await expect(page.getByTestId('day-event-' + state.events[0].id)).toBeVisible();
  expect(state.calls.filter((call) => call.method === 'PATCH').at(-1).body.resetExceptions).toBe(true);
});

test('rejects a nonexistent DST time and lets a user choose the second autumn occurrence', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
  await app.field('Tytuł wydarzenia').fill('Zmiana czasu'); await moreOptions(page); await app.field('Strefa czasowa IANA').fill('Europe/Warsaw');
  await app.field('Data początku').fill('2026-03-29'); await app.field('Data końca').fill('2026-03-29');
  await app.field('Godzina początku').fill('02:30'); await app.field('Godzina końca').fill('03:30'); await button(page, 'Zapisz').click();
  await expect(page.getByRole('alert')).toContainText('godzina musi istnieć'); expect(state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events'))).toHaveLength(0);
  await app.field('Data początku').fill('2026-10-25'); await app.field('Data końca').fill('2026-10-25');
  await button(page, 'Godzina podczas zmiany czasu').click(); await button(page, 'Drugie wystąpienie godziny (UTC+01:00)').click(); await button(page, 'Zapisz').click();
  expect(state.calls.find((call) => call.method === 'POST' && call.path.endsWith('/events')).body.schedule).toMatchObject({ utcOffset: '+01:00', durationMinutes: 60 });
});


test('a calendar notification invalidates open private details and reloads the agenda', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; await page.clock.install();
  await open(app); await page.getByTestId('day-event-' + state.events[0].id).click(); await expect(page.getByTestId('day-event-detail')).toContainText('Przynieś zeszyt');
  state.events = [];
  world.notifications = [{ id: 'calendar-revoked', subject: 'Zmiana w planie dnia', message: 'Twoje wydarzenie się zmieniło', createdAt: '2026-09-21T10:00:00Z', readAt: null, parameters: { url: '/day-planning' } }];
  await page.clock.runFor(11000);
  await expect(page.getByTestId('day-event-detail')).toHaveCount(0); await expect(page.getByText('Przynieś zeszyt')).toHaveCount(0);
  await expect(page.getByText('Brak wydarzeń w tym dniu.')).toBeVisible();
});

test.describe('weekly calendar', () => {
  test.use({ timezoneId: 'Europe/Warsaw' });

  test('switches from the selected day to a Monday–Sunday week and preserves the list range', async ({ app, page, world }) => {
    const state = await install(page, world);
    await open(app); await app.field('Data').fill('2026-09-23');
    await button(page, 'Tydzień').click();
    await expect(page.getByTestId('day-week-calendar')).toBeVisible();
    await expect(page.getByTestId(/^week-day-/)).toHaveCount(7);
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('from')).toBe('2026-09-20T22:00:00.000Z');
    expect(state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('to')).toBe('2026-09-27T22:00:00.000Z');
    await expect(page.getByText('Brak wydarzeń w tym tygodniu.')).toBeVisible();
    await button(page, 'Następny okres').click();
    await expect(page.getByTestId('week-day-2026-09-28')).toBeVisible();
    await button(page, 'Poprzedni okres').click();
    await button(page, 'Dzień').click();
    await expect(page.getByTestId('day-agenda-2026-09-23')).toBeVisible();
    await expect(page.getByTestId('day-week-calendar')).toHaveCount(0);
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('to')).toBe('2026-09-23T22:00:00.000Z');
  });

  test('positions overlapping events side by side, keeps all-day events separate and opens authorized details', async ({ app, page, world }, testInfo) => {
    const state = await install(page, world);
    state.events = [
      event(world, { end: DAY + 'T09:00:00Z' }),
      event(world, { id: 'second', title: 'Trening', start: DAY + 'T07:30:00Z', end: DAY + 'T08:30:00Z', tags: [{ id: 'work', name: 'Praca', color: '#325f99' }] }),
      event(world, { id: 'all-day', title: 'Dzień wolny', allDay: true, start: '2026-09-20T22:00:00Z', end: DAY + 'T22:00:00Z' }),
    ];
    await open(app); await button(page, 'Tydzień').click();
    const first = page.getByTestId(`week-event-${state.events[0].id}-${DAY}`);
    const second = page.getByTestId(`week-event-second-${DAY}`);
    await expect(first).toHaveAttribute('aria-label', /Jednocześnie: 2/);
    await expect(page.getByTestId(`week-all-day-${DAY}`)).toContainText('Dzień wolny');
    await first.scrollIntoViewIfNeeded();
    await expect(page.getByTestId(`week-day-${DAY}`)).toBeInViewport();
    const a = await first.boundingBox(), b = await second.boundingBox();
    expect(a.x + a.width).toBeLessThanOrEqual(b.x);
    expect(a.y).toBeLessThan(b.y); expect(b.y).toBeLessThan(a.y + a.height);
    expect(a.height).toBeGreaterThan(b.height * 1.9);
    await page.screenshot({ path: testInfo.outputPath('weekly-mobile-overlaps.png') });
    await second.click(); await expect(page.getByTestId('day-event-detail')).toContainText('Trening');
    await button(page, 'Wróć do kalendarza').click();
    await expect(page.getByTestId('day-week-calendar')).toBeVisible();
  });

  test('starts an event from a tapped hour or the all-day strip and still opens tapped events', async ({ app, page, world }) => {
    const state = await install(page, world);
    state.events = [event(world, { id: 'all-day', title: 'Dzień wolny', allDay: true, start: '2026-09-20T22:00:00Z', end: DAY + 'T22:00:00Z' })];
    await open(app); await button(page, 'Tydzień').click();
    await page.getByTestId(`week-event-all-day-${DAY}`).click();
    await expect(page.getByTestId('day-event-detail')).toContainText('Dzień wolny');
    await button(page, 'Wróć do kalendarza').click();
    await page.getByTestId('week-slot-2026-09-24-14').click();
    await expect(page.getByTestId('day-event-editor')).toBeVisible();
    await expect(app.field('Data początku')).toHaveValue('2026-09-24');
    await expect(app.field('Godzina początku')).toHaveValue('14:00');
    await expect(app.field('Godzina końca')).toHaveValue('15:00');
    await app.field('Tytuł wydarzenia').fill('Dentysta'); await button(page, 'Zapisz').click();
    await expect(page.getByTestId('day-week-calendar')).toBeVisible();
    expect(state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events')).at(-1).body).toMatchObject({ title: 'Dentysta', teamId: null, schedule: { kind: 'TIMED', localStart: '2026-09-24T14:00', durationMinutes: 60, timeZone: 'Europe/Warsaw' } });
    await page.getByTestId('week-all-day-2026-09-26').click();
    await expect(page.getByRole('checkbox', { name: 'Cały dzień', exact: true })).toBeChecked();
    await expect(app.field('Data początku')).toHaveValue('2026-09-26');
    await expect(app.field('Data końca')).toHaveValue('2026-09-26');
    await app.field('Tytuł wydarzenia').fill('Wyjazd'); await button(page, 'Zapisz').click();
    await expect(page.getByTestId('day-week-calendar')).toBeVisible();
    expect(state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events')).at(-1).body.schedule).toEqual({ kind: 'ALL_DAY', startDate: '2026-09-26', endDate: '2026-09-27', timeZone: 'Europe/Warsaw' });
  });

  test('keeps noninteractive busy blocks visible in the team week under tag filtering', async ({ app, page, world }) => {
    const state = await install(page, world); state.events = [event(world)];
    state.busy = [{ kind: 'busy', personId: world.users[1].id, start: DAY + 'T07:30:00Z', end: DAY + 'T08:30:00Z' }];
    await open(app); await page.getByText('Plan zespołu', { exact: true }).click();
    await expect(person(page, 'Bartek Kowalski')).toBeChecked();
    await button(page, 'Tydzień').click();
    const busy = page.getByTestId(`week-busy-${DAY}`);
    await expect(busy).toContainText('Zajęty · Bartek Kowalski');
    await expect(busy).not.toHaveAttribute('role', 'button');
    await busy.click(); expect(state.calls.filter((call) => call.path.includes('/occurrences/'))).toHaveLength(0);
    await expect(page.getByTestId('day-event-editor')).toHaveCount(0);
    await page.getByText('Praca', { exact: true }).click();
    await expect(page.getByTestId(/^week-event-/)).toHaveCount(0); await expect(busy).toBeVisible();
    await expect(busy).not.toContainText('Plan lekcji');
    expect(state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.getAll('personIds[]')).toContain(world.users[1].id);
  });

  test('splits overnight events across days and scrolls the full week inside a narrow dark phone', async ({ app, page, world }, testInfo) => {
    const state = await install(page, world); world.personalisation.themeMode = 'dark';
    await page.setViewportSize({ width: 320, height: 740 });
    const errors = []; page.on('pageerror', (error) => errors.push(error.message));
    state.events = [event(world, { id: 'overnight', title: 'Nocna podróż', start: '2026-09-25T21:00:00Z', end: '2026-09-25T23:00:00Z' })];
    await open(app); await button(page, 'Tydzień').click();
    const friday = page.getByTestId('week-event-overnight-2026-09-25');
    const saturday = page.getByTestId('week-event-overnight-2026-09-26');
    await expect(friday).toHaveAttribute('aria-label', /Trwa również następnego dnia/);
    await expect(saturday).toHaveAttribute('aria-label', /Ciąg dalszy z poprzedniego dnia/);
    await saturday.scrollIntoViewIfNeeded();
    await expect(saturday).toBeVisible();
    await expect(page.getByTestId('week-day-2026-09-26')).toBeInViewport();
    await expect.poll(async () => Math.abs((await page.getByTestId('week-day-2026-09-26').boundingBox()).x + 4 - (await saturday.boundingBox()).x)).toBeLessThan(2);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
    expect(errors).toEqual([]);
    await page.screenshot({ path: testInfo.outputPath('weekly-mobile-dark.png') });
    await saturday.click(); await expect(page.getByTestId('day-event-detail')).toContainText('Nocna podróż');
  });
});

test.describe('tag colors and inline creation', () => {
  test('uses accessible color swatches, preserves custom colors and sends the selected color', async ({ app, page, world }) => {
    const state = await install(page, world); state.tags[0].color = '#123456';
    await open(app); await button(page, 'Zarządzaj tagami').click();
    await button(page, 'Edytuj tag: Szkoła').click();
    await expect(page.getByRole('radio', { name: 'Obecny kolor', exact: true })).toBeChecked();
    await expect(page.getByText('#123456', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('textbox', { name: /Kolor tagu/ })).toHaveCount(0);
    const blue = page.getByRole('radio', { name: 'Niebieski', exact: true });
    const box = await blue.boundingBox(); expect(box.width).toBeGreaterThanOrEqual(44); expect(box.height).toBeGreaterThanOrEqual(44);
    await blue.click(); await expect(blue).toBeChecked();
    expect(await blue.evaluate((node) => [...node.querySelectorAll('*')].some((element) => getComputedStyle(element).backgroundColor === 'rgb(50, 95, 153)'))).toBe(true);
    await button(page, 'Zapisz').click();
    await expect.poll(() => state.calls.find((call) => call.method === 'PATCH' && call.path.endsWith('/tags/school'))?.body.color).toBe('#325f99');
    expect(state.tags[0].color).toBe('#325f99');
  });

  test('creates and selects a personal tag without losing the event draft', async ({ app, page, world }) => {
    const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
    await app.field('Tytuł wydarzenia').fill('Próba chóru'); await app.field('Opis').fill('Nowy utwór');
    await app.field('Godzina początku').fill('16:00'); await app.field('Godzina końca').fill('17:30');
    await button(page, 'Nowy tag').click(); await expect(button(page, 'Zapisz')).toBeDisabled();
    await app.field('Nazwa tagu').fill('  Muzyka  '); await page.getByRole('radio', { name: 'Fioletowy', exact: true }).click();
    await button(page, 'Utwórz tag').click();
    await expect(button(page, 'Muzyka')).toBeVisible();
    await expect(button(page, 'Muzyka').getByText('✓')).toBeVisible();
    await expect(app.field('Tytuł wydarzenia')).toHaveValue('Próba chóru'); await expect(app.field('Opis')).toHaveValue('Nowy utwór');
    await expect(app.field('Godzina początku')).toHaveValue('16:00');
    expect(state.calls.find((call) => call.path.endsWith('/tags') && call.method === 'POST').body).toEqual({ name: 'Muzyka', color: '#86549e', scope: 'PERSONAL', teamId: null });
    await button(page, 'Zapisz').click();
    await expect.poll(() => state.calls.find((call) => call.path.endsWith('/events') && call.method === 'POST')?.body.tagIds).toEqual(['new-tag']);
    await expect(page.getByTestId('day-event-new-event')).toContainText('Muzyka');
  });

  test('keeps failed tag drafts, blocks duplicate creates and refreshes the catalog after event cancellation', async ({ app, page, world }) => {
    const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
    await app.field('Tytuł wydarzenia').fill('Zachowany szkic'); await button(page, 'Nowy tag').click();
    await app.field('Nazwa tagu').fill('Basen'); await page.getByRole('radio', { name: 'Turkusowy', exact: true }).click();
    state.failTagCreate = true; await button(page, 'Utwórz tag').click(); await expect(page.getByTestId('day-inline-tag').getByRole('alert')).toBeVisible();
    await expect(app.field('Nazwa tagu')).toHaveValue('Basen'); await expect(app.field('Tytuł wydarzenia')).toHaveValue('Zachowany szkic');
    await expect(page.getByRole('radio', { name: 'Turkusowy', exact: true })).toBeChecked(); await expect(button(page, 'Zapisz')).toBeDisabled();
    state.failTagCreate = false; let finish; state.tagCreateGate = new Promise((resolve) => { finish = resolve; });
    await button(page, 'Utwórz tag').click(); await expect(button(page, 'Utwórz tag')).toBeDisabled(); await expect(button(page, 'Zapisz')).toBeDisabled();
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/tags') && call.method === 'POST').length).toBe(2);
    await button(page, 'Anuluj').click(); finish();
    await expect(page.getByTestId('day-event-editor')).toHaveCount(0); await expect(button(page, 'Basen')).toBeVisible();
    expect(state.calls.filter((call) => call.path.endsWith('/events') && call.method === 'POST')).toHaveLength(0);
    expect(state.calls.filter((call) => call.path.endsWith('/tags') && call.method === 'POST')).toHaveLength(2);
  });

  test('restricts team tag creation to team administrators and hides personal tags on team events', async ({ app, page, world }) => {
    const state = await install(page, world); world.teams[0].role = 'member';
    await open(app); await button(page, 'Nowe wydarzenie').click();
    await button(page, 'Nowy tag').click(); await expect(button(page, 'Zakres tagu')).toHaveCount(0); await expect(page.getByTestId('day-inline-tag')).toContainText('Osobisty');
    await button(page, 'Anuluj tworzenie tagu').click(); await eventTeam(page);
    await expect(button(page, 'Cały zespół')).toHaveCount(0);
    await expect(button(page, 'Praca')).toHaveCount(0); await expect(button(page, 'Szkoła')).toBeVisible();
    await expect(button(page, 'Nowy tag')).toHaveCount(0); await expect(page.getByText('Wydarzenia zespołowe używają tagów zespołu. Nowy tag może dodać administrator zespołu.')).toBeVisible();
    expect(state.calls.filter((call) => call.path.endsWith('/tags') && call.method === 'POST')).toHaveLength(0);
  });

  test('creates a team tag with colors on a narrow dark phone and keeps it out of a changed event scope', async ({ app, page, world }, testInfo) => {
    const state = await install(page, world); world.personalisation.themeMode = 'dark'; await page.setViewportSize({ width: 320, height: 740 });
    await open(app); await button(page, 'Nowe wydarzenie').click(); await app.field('Tytuł wydarzenia').fill('Spotkanie'); await eventTeam(page);
    await button(page, 'Nowy tag').click();
    await app.field('Nazwa tagu').fill('Rodzinne'); await page.getByRole('radio', { name: 'Pomarańczowy', exact: true }).click();
    await page.getByRole('radio', { name: 'Pomarańczowy', exact: true }).scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
    await page.waitForTimeout(300);
    await page.screenshot({ path: testInfo.outputPath('inline-tag-mobile-dark.png'), animations: 'disabled' });
    let finish; state.tagCreateGate = new Promise((resolve) => { finish = resolve; }); await button(page, 'Utwórz tag').click();
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/tags') && call.method === 'POST').length).toBe(1);
    expect(state.calls.find((call) => call.path.endsWith('/tags') && call.method === 'POST').body).toEqual({ name: 'Rodzinne', color: '#a45132', scope: 'TEAM', teamId: TEAM.id });
    await button(page, 'Zespół wydarzenia').click(); await button(page, 'Tylko mój kalendarz').click();
    await expect(page.getByTestId('day-inline-tag')).toHaveCount(0); finish();
    await expect(app.field('Tytuł wydarzenia')).toHaveValue('Spotkanie'); await button(page, 'Zapisz').click();
    await expect.poll(() => state.calls.find((call) => call.path.endsWith('/events') && call.method === 'POST')?.body.tagIds).toEqual([]);
    expect(state.calls.find((call) => call.path.endsWith('/events') && call.method === 'POST').body.teamId).toBe(null);
  });
});

test.describe('date and time pickers', () => {
  test('uses browser pickers, preserves cancellation and saves minute precision with recurrence until', async ({ app, page, world }, testInfo) => {
    const state = await install(page, world);
    world.personalisation.themeMode = 'dark';
    await page.setViewportSize({ width: 320, height: 740 });
    await open(app);
    await expect(app.field('Data')).toHaveAttribute('type', 'date');
    await button(page, 'Nowe wydarzenie').click();
    await app.field('Tytuł wydarzenia').fill('Próba z minutami');
    await moreOptions(page);
    await app.field('Strefa czasowa IANA').fill('Europe/Warsaw');
    for (const label of ['Data początku', 'Data końca']) await expect(app.field(label)).toHaveAttribute('type', 'date');
    for (const label of ['Godzina początku', 'Godzina końca']) await expect(app.field(label)).toHaveAttribute('type', 'time');
    await app.field('Data początku').click();
    await page.keyboard.press('Escape');
    await expect(app.field('Data początku')).toHaveValue(DAY);
    await app.field('Godzina początku').fill('09:37');
    await app.field('Godzina końca').fill('10:43');
    await button(page, 'Powtarzanie').click();
    await button(page, 'Co kilka dni').click();
    await button(page, 'Zakończenie cyklu').click();
    await button(page, 'Do wskazanej daty').click();
    await expect(app.field('Ostatni dzień cyklu')).toHaveAttribute('type', 'date');
    await app.field('Ostatni dzień cyklu').fill('2026-11-29');
    await app.field('Data początku').scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
    await page.screenshot({ path: testInfo.outputPath('date-pickers-mobile-dark.png') });
    await button(page, 'Zapisz').click();
    await expect(page.getByTestId('day-event-new-event')).toBeVisible();
    expect(state.calls.find((call) => call.method === 'POST' && call.path.endsWith('/events')).body).toMatchObject({
      schedule: { localStart: DAY + 'T09:37', durationMinutes: 66, timeZone: 'Europe/Warsaw' },
      recurrence: { frequency: 'DAILY', until: '2026-11-29' },
    });
  });

  test('applies picked planning dates and arbitrary-minute windows and locks fields during a search', async ({ app, page, world }) => {
    const state = await install(page, world);
    await open(app);
    await page.getByText('Znajdź termin', { exact: true }).click();
    await app.field('Od dnia').fill('2026-10-25');
    await app.field('Ostatni dzień poszukiwań').fill('2026-10-31');
    await app.field('Szukaj od godziny').fill('08:17');
    await app.field('Szukaj do godziny').fill('19:43');
    await displayZone(page, 'Europe/Warsaw');
    let release;
    const gate = new Promise((resolve) => { release = resolve; });
    await page.route('**/api/day-planning/planning/suggestions', async (route) => { await gate; await route.fallback(); });
    await button(page, 'Znajdź wspólny termin').click();
    for (const label of ['Od dnia', 'Ostatni dzień poszukiwań', 'Szukaj od godziny', 'Szukaj do godziny']) await expect(app.field(label)).toBeDisabled();
    release();
    await expect(page.getByTestId('day-suggestions')).toBeVisible();
    expect(state.calls.find((call) => call.path.endsWith('/planning/suggestions')).body).toMatchObject({
      from: '2026-10-24T22:00:00.000Z', to: '2026-10-31T23:00:00.000Z', windowStart: '08:17', windowEnd: '19:43', timeZone: 'Europe/Warsaw',
    });
    await expect(app.field('Od dnia')).toBeEnabled();
  });
});

test.describe('simplified day plan', () => {
  test('shows only the everyday controls and filters by tags from every catalog', async ({ app, page, world }) => {
    const state = await install(page, world); state.events = [event(world)];
    await open(app);
    await expect(button(page, 'Zespół')).toHaveCount(0);
    await expect(button(page, 'Lista')).toHaveCount(0);
    await expect(button(page, 'Kalendarz tygodniowy')).toHaveCount(0);
    await expect(button(page, 'Dzień')).toBeVisible();
    await expect(button(page, 'Tydzień')).toBeVisible();
    await expect(button(page, 'Nowe wydarzenie')).toBeVisible();
    await expect(button(page, 'Szkoła')).toBeVisible();
    await expect(button(page, 'Praca')).toBeVisible();
    expect(new Set(state.calls.filter((call) => call.method === 'GET' && call.path.endsWith('/tags')).map((call) => call.query.get('teamId')))).toEqual(new Set([null, TEAM.id]));
    await expect(button(page, 'Wyczyść')).toHaveCount(0);
    await button(page, 'Szkoła').click();
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.getAll('tagIds[]')).toEqual(['school']);
    await button(page, 'Wyczyść').click();
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.getAll('tagIds[]')).toEqual([]);
    await expect(page.getByText('Prywatne wydarzenia innych osób widać tylko jako „Zajęty”.')).toHaveCount(0);
    await page.getByText('Plan zespołu', { exact: true }).click();
    await expect(page.getByText('Prywatne wydarzenia innych osób widać tylko jako „Zajęty”.')).toBeVisible();
    await expect(page.getByTestId('day-event-' + state.events[0].id)).toContainText('Ala Kowalska, Bartek Kowalski');
  });

  test('fills the chosen view, tags and zone so they stand out from the rest', async ({ app, page, world }) => {
    await install(page, world);
    await open(app);
    const chosen = await fill(page.getByText('Mój plan', { exact: true }));
    expect(await fill(page.getByText('Plan zespołu', { exact: true }))).not.toBe(chosen);
    expect(await fill(button(page, 'Szkoła'))).not.toBe(chosen);
    await button(page, 'Szkoła').click();
    await expect.poll(() => fill(button(page, 'Szkoła'))).toBe(chosen);
    expect(await fill(button(page, 'Praca'))).not.toBe(chosen);
    await displayZone(page, 'Europe/London');
    await page.getByRole('button', { name: 'Strefa wyświetlania: Europe/London' }).click();
    expect(await fill(button(page, 'Europe/London'))).toBe(chosen);
    expect(await fill(button(page, 'America/New_York'))).not.toBe(chosen);
    await button(page, 'Nowe wydarzenie').click();
    expect(await fill(button(page, 'Praca'))).not.toBe(chosen);
    await button(page, 'Praca').click();
    await expect.poll(() => fill(button(page, 'Praca'))).toBe(chosen);
  });

  test('keeps a person without a team in one personal view', async ({ app, page, world }) => {
    world.teams = [];
    const state = await install(page, world);
    await open(app);
    await expect(page.getByText('Plan zespołu', { exact: true })).toHaveCount(0);
    await button(page, 'Nowe wydarzenie').click();
    await expect(button(page, 'Zespół wydarzenia')).toHaveCount(0);
    await expect(button(page, 'Cały zespół')).toHaveCount(0);
    await expect(page.getByRole('checkbox', { name: /^Zaproś:/ })).toHaveCount(0);
    await app.field('Tytuł wydarzenia').fill('Czytanie');
    await button(page, 'Zapisz').click();
    await expect.poll(() => state.calls.find((call) => call.method === 'POST' && call.path.endsWith('/events'))?.body).toMatchObject({ teamId: null, visibility: 'PRIVATE', participantIds: [world.me.id] });
  });

  test('opens the team plan on a team where somebody else is waiting', async ({ app, page, world }) => {
    const solo = { ...TEAM, id: '66666666-6666-4666-8666-666666666666', name: 'Moja rodzina' };
    world.teams.unshift(solo);
    world.members[solo.id] = [{ ...world.members[TEAM.id][0], id: 'm-5' }];
    const state = await install(page, world);
    await open(app); await page.getByText('Plan zespołu', { exact: true }).click();
    await expect(button(page, 'Zespół')).toContainText(TEAM.name);
    await expect(person(page, 'Bartek Kowalski')).toBeChecked();
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('teamId')).toBe(TEAM.id);
  });

  test('offers a team picker only to people in several teams', async ({ app, page, world }) => {
    const club = { ...TEAM, id: '44444444-4444-4444-8444-444444444444', name: 'Klub sportowy', role: 'member' };
    world.teams.push(club);
    world.members[club.id] = [{ ...world.members[TEAM.id][0], id: 'm-3' }, { id: 'm-4', userId: '55555555-5555-4555-8555-555555555555', userName: 'Celina Nowak', givenName: 'Celina', userEmail: 'celina@example.com', role: 'member', joinedAt: '2026-01-01T10:00:00+00:00', face: null }];
    const state = await install(page, world);
    await open(app); await page.getByText('Plan zespołu', { exact: true }).click();
    await expect(person(page, 'Bartek Kowalski')).toBeChecked();
    await button(page, 'Zespół').click(); await button(page, 'Klub sportowy').click();
    await expect(person(page, 'Celina Nowak')).toBeChecked();
    await expect(person(page, 'Bartek Kowalski')).toHaveCount(0);
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('teamId')).toBe(club.id);
    expect(state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.getAll('personIds[]').sort()).toEqual([world.me.id, '55555555-5555-4555-8555-555555555555'].sort());
  });
});

test.describe('event form rules', () => {
  const weekday = (page, name) => page.getByRole('button', { name, exact: true });
  /** Paper marks a chosen chip with a check icon; RN Web does not expose aria-selected on buttons. */
  const chosen = (page, name) => expect(weekday(page, name).getByRole('img')).toHaveCount(1);
  const notChosen = (page, name) => expect(weekday(page, name).getByRole('img')).toHaveCount(0);

  test('checks the weekday of the chosen start date for a weekly repeat and moves the end with it', async ({ app, page, world }) => {
    const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
    await app.field('Tytuł wydarzenia').fill('Basen');
    await app.field('Data początku').fill('2026-09-23');
    await expect(app.field('Data końca')).toHaveValue('2026-09-23');
    await button(page, 'Powtarzanie').click(); await button(page, 'Co kilka tygodni').click();
    await chosen(page, 'Śr'); await notChosen(page, 'Pon');
    await app.field('Data początku').fill('2026-09-25');
    await chosen(page, 'Pt'); await notChosen(page, 'Śr');
    await expect(app.field('Data końca')).toHaveValue('2026-09-25');
    await weekday(page, 'Wt').click();
    await app.field('Data początku').fill('2026-09-26');
    for (const name of ['Wt', 'Pt', 'Sob']) await chosen(page, name);
    for (const name of ['Pon', 'Śr', 'Czw', 'Ndz']) await notChosen(page, name);
    await button(page, 'Zapisz').click();
    await expect(page.getByTestId('day-event-new-event')).toBeVisible();
    expect(state.calls.find((call) => call.method === 'POST' && call.path.endsWith('/events')).body).toMatchObject({
      schedule: { localStart: '2026-09-26T09:00', durationMinutes: 60 }, recurrence: { frequency: 'WEEKLY', byDay: [2, 5, 6] },
    });
  });

  test('always shares a team event with its team and says who sees the details', async ({ app, page, world }) => {
    const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
    await expect(page.getByTestId('day-event-audience')).toHaveText('Szczegóły widzisz tylko Ty.');
    await app.field('Tytuł wydarzenia').fill('Kino'); await eventTeam(page);
    await expect(page.getByTestId('day-event-audience')).toHaveText('Szczegóły wydarzenia widzą wszyscy aktualni członkowie wybranego zespołu.');
    await expect(button(page, 'Cały zespół')).toHaveCount(0); await expect(button(page, 'Autor i zaproszeni')).toHaveCount(0);
    await button(page, 'Zapisz').click(); await expect(page.getByTestId('day-event-new-event')).toBeVisible();
    expect(state.calls.find((call) => call.method === 'POST' && call.path.endsWith('/events')).body).toMatchObject({ title: 'Kino', teamId: TEAM.id, visibility: 'TEAM' });
  });

  test('shares an older private team event with its team once it is edited', async ({ app, page, world }) => {
    const state = await install(page, world); state.events = [event(world)];
    await open(app); await page.getByTestId('day-event-' + state.events[0].id).click();
    await expect(page.getByTestId('day-event-detail')).toContainText('Autor i zaproszeni');
    await button(page, 'Edytuj').click();
    await expect(page.getByTestId('day-event-audience')).toHaveText('Szczegóły wydarzenia widzą wszyscy aktualni członkowie wybranego zespołu.');
    await app.field('Tytuł wydarzenia').fill('Wycieczka'); await button(page, 'Zapisz').click();
    await expect(page.getByText('Wycieczka', { exact: true })).toBeVisible();
    expect(state.calls.find((call) => call.method === 'PUT' && call.path.includes('/exceptions/')).body.changes).toEqual({ title: 'Wycieczka', visibility: 'TEAM' });
    await page.getByTestId('day-event-' + state.events[0].id).click(); await button(page, 'Cała seria').click(); await button(page, 'Edytuj').click();
    await button(page, 'Zapisz').click(); await expect(page.getByTestId('day-event-editor')).toHaveCount(0);
    expect(state.calls.filter((call) => call.method === 'PATCH').at(-1).body).toMatchObject({ teamId: TEAM.id, visibility: 'TEAM' });
  });
});
