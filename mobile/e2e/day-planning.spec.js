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
const open = async (app) => { await app.signIn(); await app.goTo('Plan dnia'); await app.field('Data (RRRR-MM-DD)').fill(DAY); };
const chooseTeam = async (page) => { await button(page, 'Zespół').click(); await button(page, TEAM.name).click(); };

test('keeps busy placeholders under tag filtering without offering private details', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)];
  await open(app); await page.getByText('Plan zespołu', { exact: true }).click(); await chooseTeam(page);
  await page.getByRole('checkbox', { name: 'Pokaż osobę: Bartek Kowalski' }).click();
  await expect(page.getByTestId('day-busy')).toContainText('Zajęty'); await page.getByText('Praca', { exact: true }).click();
  await expect(page.getByTestId('day-event-' + state.events[0].id)).toHaveCount(0); await expect(page.getByTestId('day-busy')).toBeVisible();
  await page.getByTestId('day-busy').click(); expect(state.calls.filter((call) => call.path.includes('/occurrences/'))).toHaveLength(0);
});

test('creates an overnight private recurrence with stable idempotency across failure and confirmed conflict', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await chooseTeam(page); await button(page, 'Nowe wydarzenie').click();
  await app.field('Tytuł wydarzenia').fill('Nocna podróż'); await app.field('Strefa czasowa IANA').fill('Europe/Warsaw'); await app.field('Godzina początku (GG:MM)').fill('23:00'); await app.field('Data końca (RRRR-MM-DD)').fill('2026-09-22'); await app.field('Godzina końca (GG:MM)').fill('01:00');
  await button(page, 'Powtarzanie').click(); await button(page, 'Co kilka tygodni').click(); await page.getByRole('checkbox', { name: 'Zaproś: Bartek Kowalski' }).click();
  state.failCreate = true; await button(page, 'Zapisz').click(); await expect(page.getByRole('alert')).toContainText('Sprawdź połączenie');
  state.failCreate = false; state.conflict = true; await button(page, 'Zapisz').click(); await button(page, 'Zapisz mimo kolizji').click();
  await expect(page.getByTestId('day-event-new-event')).toContainText('Nocna podróż');
  const saves = state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events'));
  expect(new Set(saves.map((call) => call.headers['idempotency-key'])).size).toBe(1);
  expect(saves.at(-1).body).toMatchObject({ visibility: 'PRIVATE', participantIds: [world.me.id, world.users[1].id], schedule: { localStart: DAY + 'T23:00', durationMinutes: 120 }, recurrence: { frequency: 'WEEKLY', interval: 1 }, conflictConfirmation: 'confirmed-slot' });
});

test('edits a single occurrence with version checks and preserves invitee details after declining', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; await open(app); await page.getByTestId('day-event-' + state.events[0].id).click(); await button(page, 'Edytuj ten dzień').click();
  await expect(button(page, 'Powtarzanie')).toHaveCount(0); await app.field('Tytuł wydarzenia').fill('Wycieczka szkolna'); await button(page, 'Zapisz').click(); await expect(page.getByText('Wycieczka szkolna', { exact: true })).toBeVisible();
  const change = state.calls.find((call) => call.method === 'PUT' && call.path.includes('/exceptions/')); expect(change.headers['if-match']).toBe('"3"'); expect(change.body.changes.title).toBe('Wycieczka szkolna'); expect(change.body.changes).not.toHaveProperty('recurrence'); expect(change.body.changes).not.toHaveProperty('teamId');
  state.events[0].canEdit = false; state.events[0].ownerId = world.users[1].id; await page.getByTestId('day-event-' + state.events[0].id).click(); await expect(button(page, 'Edytuj całą serię')).toHaveCount(0); await button(page, 'Zrezygnuj tylko w tym dniu').click();
  await expect(page.getByTestId('day-event-' + state.events[0].id)).toContainText('Udział odrzucony'); await page.getByTestId('day-event-' + state.events[0].id).click(); await expect(page.getByText('Przynieś zeszyt')).toBeVisible(); await button(page, 'Wróć tylko w tym dniu').click();
  expect(state.calls.filter((call) => call.path.endsWith('/participation/me')).map((call) => call.body)).toEqual([{ status: 'DECLINED', occurrenceKey: DAY + 'T09:00' }, { status: 'INCLUDED', occurrenceKey: DAY + 'T09:00' }]);
});

test('includes the author and all calendars in planning and prefills a selected slot', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await chooseTeam(page); await page.getByText('Praca', { exact: true }).click(); await page.getByText('Znajdź termin', { exact: true }).click();
  await page.getByRole('checkbox', { name: 'Pokaż osobę: Bartek Kowalski' }).click(); await app.field('Ostatni dzień poszukiwań (RRRR-MM-DD)').fill('2026-09-27'); await app.field('Strefa czasowa IANA').fill('Europe/Warsaw'); await button(page, 'Znajdź wspólny termin').click(); await expect(page.getByTestId('day-suggestions')).toBeVisible();
  const query = state.calls.find((call) => call.path.endsWith('/planning/suggestions')).body; expect(query.personIds).toContain(world.me.id); expect(query.personIds).toContain(world.users[1].id); expect(query).not.toHaveProperty('tagIds');
  await button(page, 'Wybierz termin').click(); await expect(app.field('Godzina początku (GG:MM)')).toHaveValue('15:30'); await expect(app.field('Godzina końca (GG:MM)')).toHaveValue('16:30'); await expect(page.getByRole('checkbox', { name: 'Zaproś: Bartek Kowalski' })).toBeChecked();
});

test('manages tags and reloads stale versions while preserving rejected drafts', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; await open(app); await button(page, 'Zarządzaj tagami').click(); await app.field('Nazwa tagu').fill('Muzyka'); await button(page, 'Zapisz').click(); await expect(page.getByText('Muzyka · Osobisty')).toBeVisible();
  await button(page, 'Archiwizuj tag: Muzyka').click(); await button(page, 'Potwierdź archiwizację').click(); await expect(page.getByText('Muzyka · Osobisty')).toHaveCount(0); await button(page, 'Wróć do kalendarza').click();
  await page.getByTestId('day-event-' + state.events[0].id).click(); await button(page, 'Edytuj całą serię').click(); state.stale = true; await app.field('Tytuł wydarzenia').fill('Nowy plan'); await button(page, 'Zapisz').click(); await expect(page.getByRole('alert')).toContainText('Ktoś zmienił'); await expect(app.field('Tytuł wydarzenia')).toHaveValue('Nowy plan');
  await button(page, 'Wczytaj aktualną wersję').click(); await expect(app.field('Tytuł wydarzenia')).toHaveValue('Plan lekcji');
});

test('all-day dates have an exclusive API end and the screen fits a narrow dark phone', async ({ app, page, world }, testInfo) => {
  const state = await install(page, world); world.personalisation.themeMode = 'dark'; const errors = []; page.on('pageerror', (error) => errors.push(error.message)); await page.setViewportSize({ width: 320, height: 740 });
  await open(app); await button(page, 'Nowe wydarzenie').click(); await app.field('Tytuł wydarzenia').fill('Wolny dzień'); await page.getByRole('checkbox', { name: 'Cały dzień', exact: true }).click(); await button(page, 'Zapisz').click(); await expect(page.getByTestId('day-event-new-event')).toBeVisible();
  expect(state.calls.find((call) => call.path.endsWith('/events') && call.method === 'POST').body.schedule).toMatchObject({ kind: 'ALL_DAY', startDate: DAY, endDate: '2026-09-22' }); expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320); expect(errors).toEqual([]); await page.getByTestId('day-event-new-event').scrollIntoViewIfNeeded(); await page.screenshot({ path: testInfo.outputPath('day-plan-mobile-dark.png') });
});


test('shows cancelled exceptions and requires confirmation before resetting a series schedule', async ({ app, page, world }) => {
  const state = await install(page, world); state.events = [event(world)]; state.exceptions = { '2026-09-28T09:00': { cancelled: true } };
  await open(app); await page.getByTestId('day-event-' + state.events[0].id).click(); await button(page, 'Edytuj całą serię').click();
  await expect(page.getByText('2026-09-28 09:00 · Anulowane')).toBeVisible(); await button(page, 'Przywróć według serii').click();
  await expect(page.getByText('2026-09-28 09:00 · Anulowane')).toHaveCount(0);
  const restored = state.calls.find((call) => call.method === 'DELETE' && call.path.includes('/exceptions/'));
  expect(restored.headers['if-match']).toBe('"3"');
  state.needsReset = true; await app.field('Godzina początku (GG:MM)').fill('08:30'); await button(page, 'Zapisz').click();
  await expect(page.getByText('Ta zmiana usunie wszystkie wyjątki tej serii, w tym przeniesione i anulowane dni. Pojedyncze wystąpienia będą znów wynikać z nowego harmonogramu.')).toBeVisible();
  await button(page, 'Usuń wyjątki i zapisz').click(); await expect(page.getByTestId('day-event-' + state.events[0].id)).toBeVisible();
  expect(state.calls.filter((call) => call.method === 'PATCH').at(-1).body.resetExceptions).toBe(true);
});

test('rejects a nonexistent DST time and lets a user choose the second autumn occurrence', async ({ app, page, world }) => {
  const state = await install(page, world); await open(app); await button(page, 'Nowe wydarzenie').click();
  await app.field('Tytuł wydarzenia').fill('Zmiana czasu'); await app.field('Strefa czasowa IANA').fill('Europe/Warsaw');
  await app.field('Data początku (RRRR-MM-DD)').fill('2026-03-29'); await app.field('Data końca (RRRR-MM-DD)').fill('2026-03-29');
  await app.field('Godzina początku (GG:MM)').fill('02:30'); await app.field('Godzina końca (GG:MM)').fill('03:30'); await button(page, 'Zapisz').click();
  await expect(page.getByRole('alert')).toContainText('godzina musi istnieć'); expect(state.calls.filter((call) => call.method === 'POST' && call.path.endsWith('/events'))).toHaveLength(0);
  await app.field('Data początku (RRRR-MM-DD)').fill('2026-10-25'); await app.field('Data końca (RRRR-MM-DD)').fill('2026-10-25');
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
    await open(app); await app.field('Data (RRRR-MM-DD)').fill('2026-09-23');
    await button(page, 'Kalendarz tygodniowy').click();
    await expect(page.getByTestId('day-week-calendar')).toBeVisible();
    await expect(page.getByTestId(/^week-day-/)).toHaveCount(7);
    await expect.poll(() => state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('from')).toBe('2026-09-20T22:00:00.000Z');
    expect(state.calls.filter((call) => call.path.endsWith('/calendar')).at(-1).query.get('to')).toBe('2026-09-27T22:00:00.000Z');
    await expect(page.getByText('Brak wydarzeń w tym tygodniu.')).toBeVisible();
    await button(page, 'Następny okres').click();
    await expect(page.getByTestId('week-day-2026-09-28')).toBeVisible();
    await button(page, 'Poprzedni okres').click();
    await button(page, 'Lista').click();
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
    await open(app); await button(page, 'Kalendarz tygodniowy').click();
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

  test('keeps noninteractive busy blocks visible in the team week under tag filtering', async ({ app, page, world }) => {
    const state = await install(page, world); state.events = [event(world)];
    state.busy = [{ kind: 'busy', personId: world.users[1].id, start: DAY + 'T07:30:00Z', end: DAY + 'T08:30:00Z' }];
    await open(app); await page.getByText('Plan zespołu', { exact: true }).click(); await chooseTeam(page);
    await page.getByRole('checkbox', { name: 'Pokaż osobę: Bartek Kowalski' }).click();
    await button(page, 'Kalendarz tygodniowy').click();
    const busy = page.getByTestId(`week-busy-${DAY}`);
    await expect(busy).toContainText('Zajęty · Bartek Kowalski');
    await expect(busy).not.toHaveAttribute('role', 'button');
    await busy.click(); expect(state.calls.filter((call) => call.path.includes('/occurrences/'))).toHaveLength(0);
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
    await open(app); await button(page, 'Kalendarz tygodniowy').click();
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
    await app.field('Godzina początku (GG:MM)').fill('16:00'); await app.field('Godzina końca (GG:MM)').fill('17:30');
    await button(page, 'Nowy tag').click(); await expect(button(page, 'Zapisz')).toBeDisabled();
    await app.field('Nazwa tagu').fill('  Muzyka  '); await page.getByRole('radio', { name: 'Fioletowy', exact: true }).click();
    await button(page, 'Utwórz tag').click();
    await expect(button(page, 'Muzyka')).toBeVisible();
    await expect(button(page, 'Muzyka').getByText('✓')).toBeVisible();
    await expect(app.field('Tytuł wydarzenia')).toHaveValue('Próba chóru'); await expect(app.field('Opis')).toHaveValue('Nowy utwór');
    await expect(app.field('Godzina początku (GG:MM)')).toHaveValue('16:00');
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

  test('restricts team tag creation to team administrators and hides personal tags on shared events', async ({ app, page, world }) => {
    const state = await install(page, world); world.teams[0].role = 'member';
    await open(app); await chooseTeam(page); await button(page, 'Nowe wydarzenie').click();
    await button(page, 'Nowy tag').click(); await expect(button(page, 'Zakres tagu')).toHaveCount(0); await expect(page.getByTestId('day-inline-tag')).toContainText('Osobisty');
    await button(page, 'Anuluj tworzenie tagu').click(); await button(page, 'Widoczność').click(); await button(page, 'Cały zespół').click();
    await expect(button(page, 'Praca')).toHaveCount(0); await expect(button(page, 'Szkoła')).toBeVisible();
    await expect(button(page, 'Nowy tag')).toHaveCount(0); await expect(page.getByText('Wydarzenia zespołowe używają tagów zespołu. Nowy tag może dodać administrator zespołu.')).toBeVisible();
    expect(state.calls.filter((call) => call.path.endsWith('/tags') && call.method === 'POST')).toHaveLength(0);
  });

  test('creates a team tag with colors on a narrow dark phone and keeps it out of a changed event scope', async ({ app, page, world }, testInfo) => {
    const state = await install(page, world); world.personalisation.themeMode = 'dark'; await page.setViewportSize({ width: 320, height: 740 });
    await open(app); await chooseTeam(page); await button(page, 'Nowe wydarzenie').click(); await app.field('Tytuł wydarzenia').fill('Spotkanie');
    await button(page, 'Widoczność').click(); await button(page, 'Cały zespół').click(); await button(page, 'Nowy tag').click();
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
