const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

const sample = () => ({
    id: 'event-1', occurrenceKey: '2026-09-21T09:00', title: 'Plan lekcji', description: 'Materiały na lekcję', location: 'Szkoła', ownerId: '1', teamId: 'team-1', visibility: 'TEAM',
    start: '2026-09-21T07:00:00Z', end: '2026-09-21T08:00:00Z', allDay: false, timeZone: 'Europe/Warsaw', participantIds: ['1', '2'],
    participants: [{ personId: '1', status: 'INCLUDED' }, { personId: '2', status: 'INCLUDED' }], tags: [{ id: 'school', name: 'Lekcje', color: '#226a4c' }],
    blocksTime: true, recurring: true, canEdit: true, canChangeParticipation: true, participation: 'INCLUDED', version: 7,
});
const definition = () => ({ ...sample(), schedule: { kind: 'TIMED', localStart: '2026-09-21T09:00', durationMinutes: 60, timeZone: 'Europe/Warsaw' }, recurrence: { frequency: 'WEEKLY', interval: 1, byDay: [1], until: '2026-12-31', count: null }, tagIds: ['school'] });
const tags = () => [
    { id: 'school', name: 'Lekcje', color: '#226a4c', scope: 'TEAM', teamId: 'team-1', canEdit: false },
    { id: 'work', name: 'Praca', color: '#3955af', scope: 'PERSONAL', teamId: null, canEdit: true },
];
async function setup(page, options = {}) {
    const state = { event: sample(), definition: definition(), writes: [], queries: [], tags: tags(), ...options };
    await page.addInitScript((locale) => localStorage.setItem('i18nextLng', locale), options.locale || 'pl');
    await page.route('**/api/**', (route) => route.fulfill({ status: 404, json: {} }));
    await setupAuthenticatedSession(page);
    if (state.teams || state.teamRole) await page.route('**/api/teams', (route) => route.fulfill({ json: { teams: state.teams || [{ id: 'team-1', name: 'Family Team', role: state.teamRole }] } }));
    await page.route('**/api/teams/team-2/members', (route) => route.fulfill({ json: { members: [{ userId: '1', name: 'Anna' }, { userId: '3', name: 'Celina' }] } }));
    await page.route('**/api/teams/team-solo/members', (route) => route.fulfill({ json: { members: [{ userId: '1', name: 'Anna' }] } }));
    await page.route('**/api/teams/team-1/members', (route) => route.fulfill({ json: { members: [{ userId: '1', name: 'Anna' }, { userId: '2', name: 'Bartek' }] } }));
    await page.route('**/api/day-planning/**', async (route) => {
        const req = route.request();
        const url = new URL(req.url());
        const path = url.pathname;
        if (path.endsWith('/calendar')) {
            state.queries.push(url.searchParams);
            const selectedTags = url.searchParams.getAll('tagIds[]');
            const events = (state.events || (state.event ? [state.event] : [])).filter((item) => !selectedTags.length || item.tags?.some((tag) => selectedTags.includes(tag.id)));
            return route.fulfill({ json: { events, busy: url.searchParams.get('teamId') ? state.busy || [{ kind: 'busy', personId: '2', start: '2026-09-21T10:00:00Z', end: '2026-09-21T11:00:00Z' }] : [], coverage: { complete: true } } });
        }
        if (path.endsWith('/tags') && req.method() === 'GET') {
            state.tagQueries = [...(state.tagQueries || []), url.searchParams.get('teamId')];
            const team = url.searchParams.get('teamId');
            return route.fulfill({ json: { tags: state.scopedTags ? state.tags.filter((tag) => tag.scope === 'PERSONAL' || tag.teamId === team) : state.tags } });
        }
        if (req.method() !== 'GET') state.writes.push({ method: req.method(), path, data: req.postDataJSON(), headers: req.headers() });
        if (path.includes('/tags')) {
            if (state.tagFailures > 0) { state.tagFailures--; return route.fulfill({ status: 503, json: { message: 'Nie udało się utworzyć tagu.' } }); }
            if (state.tagWait) await state.tagWait;
            if (req.method() === 'DELETE') { state.tags = state.tags.filter((tag) => !path.endsWith(`/${tag.id}`)); return route.fulfill({ status: 204 }); }
            const tag = { ...req.postDataJSON(), id: req.postDataJSON().id || 'tag-new', canEdit: true };
            state.tags = [...state.tags.filter((item) => item.id !== tag.id), tag];
            return route.fulfill({ json: tag });
        }
        if (path.endsWith('/suggestions')) return route.fulfill({ json: { slots: [{ start: '2026-09-21T17:00:00Z', end: '2026-09-21T18:00:00Z' }], personIds: ['1', '2'], busy: [], coverage: { complete: true } } });
        if (path.includes('/participation/me') && state.participationConflict && req.postDataJSON().status === 'INCLUDED' && !req.postDataJSON().conflictConfirmation) return route.fulfill({ status: 409, json: { code: 'planning_conflict', confirmationToken: 'join-token' } });
        if (path.includes('/participation/me')) { state.event.participation = req.postDataJSON().status; return route.fulfill({ json: req.postDataJSON() }); }
        if (path.includes('/occurrences/')) return route.fulfill({ json: state.events?.find((item) => path.includes(`/events/${item.id}/`)) || state.event });
        if (req.method() === 'GET') return route.fulfill({ json: state.definition });
        if (state.failure) return route.fulfill(state.failure);
        if (req.method() === 'DELETE' && path.includes('/exceptions/')) {
            if (state.restoreConflict && !req.postDataJSON().conflictConfirmation) return route.fulfill({ status: 409, json: { code: 'planning_conflict', confirmationToken: 'restore-token', conflicts: [] } });
            state.definition.exceptions = {};
            return route.fulfill({ json: { ...state.definition, version: 8 } });
        }
        if (req.method() === 'DELETE') { state.event = null; return route.fulfill({ status: 204 }); }
        if (path.includes('/exceptions/')) {
            if (req.postDataJSON().cancelled) state.event = null;
            else state.event = { ...state.event, ...req.postDataJSON().changes, version: 8 };
            return route.fulfill({ json: { ...state.definition, version: 8 } });
        }
        const payload = req.postDataJSON();
        if (state.conflict && !payload.conflictConfirmation) return route.fulfill({ status: 409, json: { code: 'planning_conflict', confirmationToken: 'safe-token', conflicts: [{ personId: '2', start: '2026-09-21T07:00:00Z', end: '2026-09-21T08:00:00Z' }] } });
        state.event = { ...sample(), ...payload, id: 'event-1' };
        return route.fulfill({ json: { ...payload, id: 'event-1', ownerId: '1', version: 8 } });
    });
    await page.clock.install({ time: new Date(options.now || '2026-09-21T08:00:00Z') });
    await page.goto('/');
    await page.getByRole('button', { name: options.locale === 'en' ? 'Day planner' : 'Plan dnia', exact: true }).click();
    await page.getByLabel(options.locale === 'en' ? 'Display time zone' : 'Strefa wyświetlania', { exact: true }).selectOption('Europe/Warsaw');
    await expect(page.getByRole('status').filter({ hasText: 'Ładowanie' })).toHaveCount(0);
    return state;
}
const dateButton = (page, label = 'Data planu') => page.getByRole('button', { name: new RegExp(`^${label}: `) });

test('keeps anonymous busy blocks under OR tag filters and shows team participants', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await expect(page.locator('.day-event--busy:visible')).toHaveCount(1);
    await expect(page.locator('.day-event--busy:visible')).toContainText('Zajęty');
    await page.getByRole('button', { name: 'Praca', exact: true }).click();
    await expect(page.locator('.day-event--busy:visible')).toHaveCount(1);
    await expect(page.getByRole('button', { name: /Plan lekcji, / })).toHaveCount(0);
    await page.getByRole('button', { name: 'Lekcje', exact: true }).click();
    await expect(page.getByRole('button', { name: /Plan lekcji, / })).toHaveCount(2);
    expect(state.queries.at(-1).getAll('tagIds[]').sort()).toEqual(['school', 'work']);
    expect(await page.locator('.day-event--busy:visible').getAttribute('data-event-id')).toBeNull();
    await page.screenshot({ path: test.info().outputPath('day-team-calendar.png'), fullPage: true });
});

test('creates a weekly overnight event and confirms conflicts with the same idempotency key', async ({ page }) => {
    const state = await setup(page, { event: null, conflict: true });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Nocna praca');
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await page.getByLabel('Początek', { exact: true }).fill('2026-09-21T23:00');
    await page.getByLabel('Koniec', { exact: true }).fill('2026-09-22T01:00');
    await page.getByLabel('Powtarzanie', { exact: true }).selectOption('WEEKLY');
    await page.getByLabel('Koniec cyklu', { exact: true }).selectOption('until');
    await page.getByLabel('Ostatni dzień cyklu', { exact: true }).fill('2026-12-31');
    await page.getByLabel('Bartek', { exact: true }).check();
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Ten termin nakłada się na inne wydarzenia' })).toBeVisible();
    await page.getByRole('button', { name: 'Potwierdzam — zapisz', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Wydarzenie zapisane' })).toBeVisible();
    const writes = state.writes.filter((write) => write.path.endsWith('/events'));
    expect(writes).toHaveLength(2);
    expect(writes[0].data.schedule).toEqual({ kind: 'TIMED', localStart: '2026-09-21T23:00', durationMinutes: 120, timeZone: 'Europe/Warsaw' });
    expect(writes[0].data.recurrence).toMatchObject({ frequency: 'WEEKLY', byDay: [1], until: '2026-12-31' });
    expect(writes[0].data.participantIds).toEqual(['1', '2']);
    expect(writes[0].headers['idempotency-key']).toBe(writes[1].headers['idempotency-key']);
    expect(writes[1].data.conflictConfirmation).toBe('safe-token');
});

test('edits only one recurrence and preserves the series with optimistic version', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wyjątkowo biblioteka');
    await expect(page.getByLabel('Powtarzanie', { exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Wydarzenie zapisane' })).toBeVisible();
    const write = state.writes.at(-1);
    expect(write.method).toBe('PUT');
    expect(decodeURIComponent(write.path)).toBe('/api/day-planning/events/event-1/exceptions/2026-09-21T09:00');
    expect(write.headers['if-match']).toBe('"7"');
    expect(write.data).toEqual({ changes: { title: 'Wyjątkowo biblioteka' } });
});

test('plans with the author included and creates from a real suggested slot', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await page.getByRole('button', { name: 'Lekcje', exact: true }).click();
    await page.getByRole('tab', { name: 'Znajdź termin', exact: true }).click();
    await expect(page.getByLabel('Ty', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Ty', { exact: true })).toBeChecked();
    await page.getByLabel('Szukaj od godziny', { exact: true }).fill('16:00');
    await page.getByRole('button', { name: 'Pokaż wspólne terminy', exact: true }).click();
    await page.getByRole('button', { name: /Wybierz termin/ }).click();
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('21.09.2026 19:00');
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('21.09.2026 20:00');
    await expect(page.getByLabel('Bartek', { exact: true })).toBeChecked();
    const query = state.writes.find((write) => write.path.endsWith('/suggestions')).data;
    expect(query.personIds).toEqual(['1', '2']);
    expect(query.tagIds).toBeUndefined();
    expect(query.windowStart).toBe('16:00');
});

test('lets a team admin write an event only into somebody else\'s calendar', async ({ page }) => {
    const state = await setup(page, { event: null, teamRole: 'admin' });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Dentysta');
    await page.getByLabel('Ty', { exact: true }).uncheck();
    await expect(page.getByRole('alert').filter({ hasText: 'przynajmniej jednego uczestnika' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeDisabled();
    await page.getByLabel('Bartek', { exact: true }).check();
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Wydarzenie zapisane' })).toBeVisible();
    const write = state.writes.find((item) => item.path.endsWith('/events'));
    expect(write.data.ownerParticipates).toBe(false);
    expect(write.data.participantIds).toEqual(['2']);
});

test('keeps a plain member inside every event they create', async ({ page }) => {
    await setup(page, { event: null, teamRole: 'member' });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await expect(page.getByLabel('Ty', { exact: true })).toBeChecked();
    await expect(page.getByLabel('Ty', { exact: true })).toBeDisabled();
});

test('names the person who wrote an event into my calendar without joining it', async ({ page }) => {
    const event = { ...sample(), canEdit: false, ownerId: '2', visibility: 'PRIVATE', participantIds: ['1'], participants: [{ personId: '1', status: 'INCLUDED' }] };
    await setup(page, { event });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await expect(page.getByText('Wpisał(a): Bartek')).toBeVisible();
});

test('allows invitees to decline one occurrence and rejoin the series without editing', async ({ page }) => {
    const event = { ...sample(), canEdit: false, ownerId: '2', visibility: 'PRIVATE' };
    const state = await setup(page, { event });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await expect(page.getByRole('button', { name: 'Edytuj', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Rezygnuję z udziału', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Wracam do udziału', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ status: 'DECLINED', occurrenceKey: '2026-09-21T09:00' });
    await page.getByRole('radio', { name: 'Cała seria', exact: true }).check();
    await page.getByRole('button', { name: 'Wracam do udziału', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Rezygnuję z udziału', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ status: 'INCLUDED', occurrenceKey: null });
});

test('retains unsaved edits on a version conflict and requires refreshing', async ({ page }) => {
    const state = await setup(page, { failure: { status: 412, json: { code: 'version_conflict' } } });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('radio', { name: 'Cała seria', exact: true }).check();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Nowa nazwa');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Wydarzenie zostało zmienione');
    await expect(page.getByLabel('Tytuł wydarzenia', { exact: true })).toHaveValue('Nowa nazwa');
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeDisabled();
    expect(state.writes.at(-1).method).toBe('PATCH');
    expect(state.writes.at(-1).headers['if-match']).toBe('"7"');
});

test('manages personal tags but does not offer team tag administration to a member', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: 'Zarządzaj tagami', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Edytuj tag Lekcje', exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Zakres tagu', { exact: true })).toHaveCount(0);
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Sport');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Edytuj tag Sport', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ name: 'Sport', scope: 'PERSONAL', teamId: null });
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Usuń tag Sport', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Edytuj tag Sport', exact: true })).toHaveCount(0);
});

test('supports a narrow dark agenda and all-day recurrence ending after a count', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 760 });
    await page.emulateMedia({ colorScheme: 'dark' });
    const state = await setup(page);
    await expect(page.locator('.day-agenda')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('day-mobile-agenda.png'), fullPage: true });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Urlop');
    await page.getByLabel('Cały dzień', { exact: true }).check();
    await page.getByLabel('Powtarzanie', { exact: true }).selectOption('DAILY');
    await page.getByLabel('Koniec cyklu', { exact: true }).selectOption('count');
    await page.getByLabel('Liczba wystąpień', { exact: true }).fill('3');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('day-mobile-editor.png'), fullPage: true });
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Wydarzenie zapisane' })).toBeVisible();
    expect(state.writes.at(-1).data.schedule).toEqual({ kind: 'ALL_DAY', startDate: '2026-09-21', endDate: '2026-09-22', timeZone: 'Europe/Warsaw' });
    expect(state.writes.at(-1).data.recurrence).toMatchObject({ frequency: 'DAILY', count: 3, until: null });
});

test('restores a cancelled occurrence after a privacy-safe conflict confirmation', async ({ page }) => {
    const state = await setup(page, { definition: { ...definition(), exceptions: { '2026-09-28T09:00': { cancelled: true } } }, restoreConflict: true });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('radio', { name: 'Cała seria', exact: true }).check();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Przywróć wystąpienie 2026-09-28 09:00', exact: true }).click();
    await page.getByRole('button', { name: 'Potwierdzam — zapisz', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Wydarzenie zapisane' })).toBeVisible();
    expect(state.writes.at(-1)).toMatchObject({ method: 'DELETE', data: { conflictConfirmation: 'restore-token' } });
    expect(state.writes.at(-1).headers['if-match']).toBe('"7"');
});

test('requires confirmation before rejoining a conflicting occurrence', async ({ page }) => {
    const state = await setup(page, { event: { ...sample(), canEdit: false, participation: 'DECLINED' }, participationConflict: true });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('button', { name: 'Wracam do udziału', exact: true }).click();
    await page.getByRole('button', { name: 'Potwierdzam — biorę udział', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Rezygnuję z udziału', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ status: 'INCLUDED', occurrenceKey: '2026-09-21T09:00', conflictConfirmation: 'join-token' });
});

test('keeps historical tags and refreshes protected details after an invalidation', async ({ page }) => {
    const state = await setup(page, { tags: [] });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeEnabled();
    await page.getByRole('button', { name: 'Anuluj', exact: true }).first().click();
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    state.event = null;
    await page.evaluate(() => window.dispatchEvent(new Event('day-planning:changed')));
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Plan lekcji, / })).toHaveCount(0);
});

test('maps ambiguous Warsaw times to the first occurrence and rejects spring gaps', async () => {
    const path = require('path');
    const code = require('@babel/core').transformFileSync(path.join(__dirname, '../../src/services/dayPlanningTime.js'), { babelrc: false, configFile: false, presets: [[require.resolve('@babel/preset-env'), { targets: { node: 'current' }, modules: 'commonjs' }]] }).code;
    const module = { exports: {} };
    new Function('module', 'exports', code)(module, module.exports);
    const { zonedIso } = module.exports;
    expect(zonedIso('2026-10-25T02:30', 'Europe/Warsaw')).toBe('2026-10-25T00:30:00.000Z');
    expect(zonedIso('2026-10-25T03:30', 'Europe/Warsaw')).toBe('2026-10-25T02:30:00.000Z');
    expect(() => zonedIso('2026-03-29T02:30', 'Europe/Warsaw')).toThrow('invalid_local_time');
});

test('navigates a full week and explicitly deletes only one occurrence', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    await expect(page.locator('.day-week-header')).toHaveCount(7);
    await expect(dateButton(page)).toHaveText(/: 21–27 września 2026/);
    await expect.poll(() => new Date(state.queries.at(-1).get('to')) - new Date(state.queries.at(-1).get('from'))).toBe(7 * 86400000);
    await page.getByRole('button', { name: 'Następny zakres', exact: true }).click();
    await expect(dateButton(page)).toHaveText(/: 28 września\s–\s4 października 2026/);
    await page.getByRole('button', { name: 'Poprzedni zakres', exact: true }).click();
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Usuń', exact: true }).click();
    await expect(page.getByText('Wydarzenie usunięte.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1)).toMatchObject({ method: 'PUT', data: { cancelled: true } });
    expect(decodeURIComponent(state.writes.at(-1).path)).toContain('/exceptions/2026-09-21T09:00');
});

test('invalidates open details when a calendar notification arrives', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    state.event = null;
    await page.route('**/api/notifications?**', (route) => route.fulfill({ json: { notifications: [{ id: 'notification-calendar', message: 'Zmiana w kalendarzu', parameters: { url: '/day-planning' } }] } }));
    await page.clock.runFor(10001);
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Plan lekcji, / })).toHaveCount(0);
});

test('opens the current occurrence before editing and uses its matching version', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    state.event = { ...state.event, title: 'Nowa wersja od autora', version: 8 };
    state.definition = { ...state.definition, version: 8 };
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await expect(page.getByLabel('Tytuł wydarzenia', { exact: true })).toHaveValue('Nowa wersja od autora');
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Moja aktualizacja');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).headers['if-match']).toBe('"8"');
});

test('edits an occurrence spanning the repeated DST hour without changing its duration', async ({ page }) => {
    const event = { ...sample(), occurrenceKey: '2026-10-25T02:30', start: '2026-10-25T00:30:00Z', end: '2026-10-25T01:30:00Z' };
    const state = await setup(page, { event, now: '2026-10-25T10:00:00Z' });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('25.10.2026 02:30');
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('25.10.2026 02:30');
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Przesunięcie zegara');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ changes: { title: 'Przesunięcie zegara' } });
});


test('switches a Wednesday list to a full week and places overlapping events side by side', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 1100 });
    const state = await setup(page, { events: [
        { ...sample(), title: 'Praca w domu', tags: [{ id: 'work', name: 'Praca', color: '#b57632' }], start: '2026-09-21T07:00:00Z', end: '2026-09-21T10:00:00Z' },
        { ...sample(), id: 'meeting', title: 'Spotkanie', tags: [{ id: 'other', name: 'Inne', color: '#5770af' }], start: '2026-09-21T08:00:00Z', end: '2026-09-21T09:00:00Z' },
        { ...sample(), id: 'call', title: 'Rozmowa', tags: [{ id: 'other', name: 'Inne', color: '#5770af' }], start: '2026-09-21T08:30:00Z', end: '2026-09-21T09:30:00Z' },
        { ...sample(), id: 'day-off', title: 'Wolne od szkoły', allDay: true, start: '2026-09-20T22:00:00Z', end: '2026-09-21T22:00:00Z' },
    ], now: '2026-09-23T08:00:00Z' });
    await expect(dateButton(page)).toHaveText(/: Środa, 23 września 2026/);
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Tydzień', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('.day-week-header')).toHaveCount(7);
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-09-20T22:00:00.000Z');
    expect(state.queries.at(-1).get('to')).toBe('2026-09-27T22:00:00.000Z');
    const work = page.getByRole('button', { name: /^Praca w domu,/ });
    const meeting = page.getByRole('button', { name: /^Spotkanie,/ });
    const call = page.getByRole('button', { name: /^Rozmowa,/ });
    await expect(work).toHaveAttribute('data-overlap-count', '2');
    const [a, b, c] = await Promise.all([work.boundingBox(), meeting.boundingBox(), call.boundingBox()]);
    expect(a.x + a.width).toBeLessThanOrEqual(b.x);
    expect(b.x + b.width).toBeLessThanOrEqual(c.x);
    expect(b.y - a.y).toBeCloseTo(60, 0);
    expect(c.y - b.y).toBeCloseTo(30, 0);
    expect(a.height).toBeCloseTo(179, 0);
    const header = await page.locator('.day-week-header').first().boundingBox();
    const allDay = await page.locator('.day-week-allday').first().boundingBox();
    expect(allDay.y).toBeCloseTo(header.y + header.height, 0);
    expect(await page.locator('.day-week-scroll').evaluate((element) => element.scrollTop)).toBeGreaterThan(0);
    await expect(page.getByRole('button', { name: /^Wolne od szkoły,/ })).toBeVisible();
    await page.screenshot({ path: test.info().outputPath('weekly-overlaps-desktop.png'), fullPage: true });
    await meeting.click();
    await expect(page.getByRole('dialog')).toContainText('Spotkanie');
    await page.getByRole('button', { name: 'Zamknij', exact: true }).click();
    await page.getByRole('button', { name: 'Następny zakres', exact: true }).click();
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-09-27T22:00:00.000Z');
    await page.getByRole('button', { name: 'Dzień', exact: true }).click();
    await expect(page.locator('.day-week-grid')).toHaveCount(0);
    await expect(dateButton(page)).toHaveText(/: Środa, 30 września 2026/);
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-09-29T22:00:00.000Z');
    expect(state.queries.at(-1).get('to')).toBe('2026-09-30T22:00:00.000Z');
});

test('keeps private busy placeholders in the team week when filtering shared events', async ({ page }) => {
    const state = await setup(page, { busy: [{ kind: 'busy', personId: '2', start: '2026-09-21T07:30:00Z', end: '2026-09-21T08:30:00Z' }] });
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    const busy = page.locator('.day-week-event.day-event--busy');
    await expect(busy).toHaveCount(1);
    await expect(busy).toContainText('Zajęty');
    await expect(busy).toContainText('Bartek');
    await expect(busy).toHaveAttribute('data-overlap-count', '1');
    expect(await busy.evaluate((element) => element.tagName)).toBe('DIV');
    await expect(busy.locator('button, a')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /^Plan lekcji,/ })).toHaveCount(1);
    await page.getByRole('button', { name: 'Praca', exact: true }).click();
    await expect(page.getByRole('button', { name: /^Plan lekcji,/ })).toHaveCount(0);
    await expect(busy).toHaveCount(1);
    expect(state.queries.at(-1).getAll('personIds[]')).toEqual(['1', '2']);
    expect(new Date(state.queries.at(-1).get('to')) - new Date(state.queries.at(-1).get('from'))).toBe(7 * 86400000);
    await page.getByRole('button', { name: 'Następny zakres', exact: true }).click();
    await expect(dateButton(page)).toHaveText(/: 28 września\s–\s4 października 2026/);
});

test('separates all-day events, splits overnight events and scrolls a narrow dark week', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ colorScheme: 'dark' });
    await setup(page, { events: [
        { ...sample(), id: 'holiday', title: 'Urlop', allDay: true, start: '2026-09-20T22:00:00Z', end: '2026-09-23T22:00:00Z' },
        { ...sample(), id: 'night', title: 'Nocna podróż', start: '2026-09-21T21:00:00Z', end: '2026-09-22T01:00:00Z' },
    ] });
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    await expect(page.locator('.day-week-allday .day-week-event')).toHaveCount(3);
    await expect(page.locator('.day-week-timeline .day-week-event')).toHaveCount(2);
    await expect(page.locator('.day-week-timeline[data-date="2026-09-21"]')).toContainText('Nocna podróż');
    await expect(page.locator('.day-week-timeline[data-date="2026-09-22"]')).toContainText('Nocna podróż');
    await expect(page.locator('.day-week-timeline[data-date="2026-09-23"] .day-week-event')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.locator('.day-week-scroll').evaluate((element) => { element.scrollLeft = 146; });
    await expect.poll(() => page.locator('.day-week-scroll').evaluate((element) => element.scrollLeft)).toBe(146);
    await page.locator('.day-week-scroll').scrollIntoViewIfNeeded();
    await page.screenshot({ path: test.info().outputPath('weekly-narrow-dark.png') });
});

test('shows a complete empty weekly grid instead of hiding the calendar', async ({ page }) => {
    await setup(page, { event: null });
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    await expect(page.locator('.day-week-header')).toHaveCount(7);
    await expect(page.locator('.day-week-hours span')).toHaveCount(24);
    await expect(page.locator('.day-week-event')).toHaveCount(0);
    await expect(page.getByRole('region', { name: 'Kalendarz tygodniowy', exact: true })).toBeVisible();
});


test('uses named color swatches and preserves an existing custom tag color', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: 'Zarządzaj tagami', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('radio', { name: 'Zielony', exact: true })).toBeChecked();
    await dialog.getByRole('radio', { name: 'Niebieski', exact: true }).check();
    await expect(dialog.getByRole('radio', { name: 'Niebieski', exact: true })).toBeChecked();
    await expect(dialog.locator('.day-color-option:has(input:checked) .day-color-swatch')).toHaveCSS('background-color', 'rgb(50, 95, 153)');
    await expect(dialog.locator('.day-color-option:has(input:checked)')).not.toHaveCSS('border-top-color', 'rgba(0, 0, 0, 0)');
    expect(await dialog.innerText()).not.toMatch(/#[0-9a-f]{6}/i);
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('  Rower  ');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Edytuj tag Rower', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ name: 'Rower', color: '#325f99', scope: 'PERSONAL' });
    await page.getByRole('button', { name: 'Edytuj tag Praca', exact: true }).click();
    await expect(dialog.getByRole('radio', { name: 'Obecny kolor', exact: true })).toBeChecked();
    await dialog.getByRole('radio', { name: 'Niebieski', exact: true }).check();
    await dialog.getByRole('radio', { name: 'Obecny kolor', exact: true }).check();
    await expect(dialog.getByRole('radio', { name: 'Obecny kolor', exact: true })).toBeChecked();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Praca w domu');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Edytuj tag Praca w domu', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data.color).toBe('#3955af');
});

test('creates and selects a personal tag inside an event without losing the event draft', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wieczorna piłka');
    await page.getByRole('textbox', { name: 'Opis', exact: true }).fill('Weź wodę');
    await page.getByRole('button', { name: 'Nowy tag', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeDisabled();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('  Sport  ');
    await page.getByRole('radio', { name: 'Fioletowy', exact: true }).check();
    await page.getByRole('button', { name: 'Utwórz tag', exact: true }).click();
    await expect(page.getByRole('checkbox', { name: 'Sport', exact: true })).toBeChecked();
    await expect(page.getByLabel('Tytuł wydarzenia', { exact: true })).toHaveValue('Wieczorna piłka');
    await expect(page.getByRole('textbox', { name: 'Opis', exact: true })).toHaveValue('Weź wodę');
    expect(state.writes).toHaveLength(1);
    expect(state.writes[0].data).toEqual({ name: 'Sport', color: '#86549e', scope: 'PERSONAL', teamId: null });
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ title: 'Wieczorna piłka', description: 'Weź wodę', tagIds: ['tag-new'] });
});

test('preserves a failed tag draft and blocks duplicate creation and event save during retry', async ({ page }) => {
    let release;
    const state = await setup(page, { event: null, tagFailures: 1, tagWait: new Promise((resolve) => { release = resolve; }) });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Nie zgub wydarzenia');
    await page.getByRole('button', { name: 'Nowy tag', exact: true }).click();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Wakacje');
    await page.getByRole('radio', { name: 'Złoty', exact: true }).check();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).press('Enter');
    expect(state.writes).toHaveLength(0);
    await page.getByRole('button', { name: 'Utwórz tag', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Nie udało się utworzyć tagu.' })).toBeVisible();
    await expect(page.getByLabel('Nazwa tagu', { exact: true })).toHaveValue('Wakacje');
    await expect(page.getByRole('radio', { name: 'Złoty', exact: true })).toBeChecked();
    await page.getByRole('button', { name: 'Utwórz tag', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Tworzenie tagu…', exact: true })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeDisabled();
    await expect(page.getByLabel('Zespół wydarzenia', { exact: true })).toBeDisabled();
    expect(state.writes).toHaveLength(2);
    release();
    await expect(page.getByRole('checkbox', { name: 'Wakacje', exact: true })).toBeChecked();
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeEnabled();
    await expect(page.getByLabel('Tytuł wydarzenia', { exact: true })).toHaveValue('Nie zgub wydarzenia');
});

test('keeps a member to team tags on a team event while preserving a personal tag draft', async ({ page }) => {
    const state = await setup(page, { event: null, scopedTags: true });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wspólny spacer');
    await page.getByRole('button', { name: 'Nowy tag', exact: true }).click();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Prywatny sport');
    await expect(page.getByRole('region', { name: 'Nowy tag', exact: true })).toContainText('Tag osobisty');
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await expect(page.getByText('Do wydarzenia zespołowego można dodać tylko tagi zespołu. Nowe tagi zespołu tworzy administrator.', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Utwórz tag', exact: true })).toBeDisabled();
    await expect(page.getByRole('checkbox', { name: 'Lekcje', exact: true })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Praca', exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Zakres tagu', { exact: true })).toHaveCount(0);
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('');
    await expect(page.getByLabel('Nazwa tagu', { exact: true })).toHaveValue('Prywatny sport');
    await expect(page.getByRole('button', { name: 'Utwórz tag', exact: true })).toBeEnabled();
    await page.getByRole('button', { name: 'Anuluj tworzenie tagu', exact: true }).click();
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await expect(page.getByRole('button', { name: 'Nowy tag', exact: true })).toHaveCount(0);
    expect(state.writes).toHaveLength(0);
});

test('gives an administrator a team tag on a team event and a personal tag without a team', async ({ page }) => {
    const state = await setup(page, { event: null, teamRole: 'admin', scopedTags: true });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wspólna nauka');
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await page.getByRole('button', { name: 'Nowy tag', exact: true }).click();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Nauka');
    const creator = page.getByRole('region', { name: 'Nowy tag', exact: true });
    await expect(creator).toContainText('Tag zespołu Family Team');
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('');
    await expect(creator).toContainText('Tag osobisty');
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await expect(page.getByLabel('Nazwa tagu', { exact: true })).toHaveValue('Nauka');
    await page.getByRole('button', { name: 'Utwórz tag', exact: true }).click();
    await expect(page.getByRole('checkbox', { name: 'Nauka', exact: true })).toBeChecked();
    expect(state.writes.at(-1).data).toMatchObject({ name: 'Nauka', scope: 'TEAM', teamId: 'team-1' });
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ title: 'Wspólna nauka', teamId: 'team-1', visibility: 'TEAM', tagIds: ['tag-new'] });
});

test('retains a created tag in the catalog when cancelling an event and fits a narrow dark screen', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ colorScheme: 'dark' });
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByRole('button', { name: 'Nowy tag', exact: true }).click();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Sztuka');
    await page.getByRole('radio', { name: 'Różowy', exact: true }).check();
    const creator = page.getByRole('region', { name: 'Nowy tag', exact: true });
    await creator.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(await creator.innerText()).not.toMatch(/#[0-9a-f]{6}/i);
    await page.screenshot({ path: test.info().outputPath('inline-tag-narrow-dark.png') });
    await page.getByRole('button', { name: 'Utwórz tag', exact: true }).click();
    await expect(page.getByRole('checkbox', { name: 'Sztuka', exact: true })).toBeChecked();
    await page.getByRole('button', { name: 'Anuluj', exact: true }).first().click();
    await expect(page.getByRole('button', { name: 'Sztuka', exact: true })).toBeVisible();
    expect(state.writes).toHaveLength(1);
    expect(state.writes[0].path).toBe('/api/day-planning/tags');
});


test('keeps the selection check readable on a light custom tag color', async ({ page }) => {
    await setup(page, { tags: [{ id: 'custom', name: 'Jasny tag', color: '#fffce0', scope: 'PERSONAL', teamId: null, canEdit: true }] });
    await page.getByRole('button', { name: 'Zarządzaj tagami', exact: true }).click();
    await page.getByRole('button', { name: 'Edytuj tag Jasny tag', exact: true }).click();
    const selected = page.locator('.day-color-option:has(input:checked) .day-color-swatch');
    await expect(selected).toHaveCSS('background-color', 'rgb(255, 252, 224)');
    await expect(selected).toHaveCSS('color', 'rgb(0, 0, 0)');
    await expect(selected).toContainText('✓');
    await page.screenshot({ path: test.info().outputPath('tag-palette-light-custom.png') });
});


test('date picker supports keyboard navigation, cancellation and focus return without changing the calendar early', async ({ page }) => {
    const state = await setup(page);
    const trigger = dateButton(page);
    const initialQueries = state.queries.length;
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'Data planu', exact: true });
    await expect(dialog.getByRole('columnheader').first()).toHaveText('pon.');
    const selected = dialog.getByRole('gridcell', { name: /21 września 2026/ });
    await expect(selected).toBeFocused();
    await selected.press('ArrowRight');
    const next = dialog.getByRole('gridcell', { name: /22 września 2026/ });
    await expect(next).toBeFocused();
    await next.press('Enter');
    await expect(next).toHaveAttribute('aria-selected', 'true');
    expect(state.queries.length).toBe(initialQueries);
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
    await expect(trigger).toHaveText(/: Poniedziałek, 21 września 2026/);
    await trigger.click();
    await dialog.getByRole('gridcell', { name: /21 września 2026/ }).press('PageDown');
    await expect(dialog.getByRole('gridcell', { name: /21 października 2026/ })).toBeFocused();
    await page.keyboard.press('Home');
    await expect(dialog.getByRole('gridcell', { name: /19 października 2026/ })).toBeFocused();
    await page.keyboard.press('End');
    const sunday = dialog.getByRole('gridcell', { name: /25 października 2026/ });
    await expect(sunday).toBeFocused();
    await sunday.press('Enter');
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await expect(trigger).toHaveText(/: Niedziela, 25 października 2026/);
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-10-24T22:00:00.000Z');
});

test('date and time pickers retain arbitrary minutes across midnight without submitting the event', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Nocna wyprawa');
    await page.getByLabel('Początek', { exact: true }).click();
    let dialog = page.getByRole('dialog', { name: 'Początek', exact: true });
    expect(await dialog.evaluate((element) => element.closest('form'))).toBeNull();
    await page.screenshot({ path: test.info().outputPath('date-picker-desktop.png') });
    await dialog.getByRole('tab', { name: 'Godzina', exact: true }).click();
    await dialog.getByRole('listbox', { name: 'Godziny', exact: true }).getByRole('option', { name: '23', exact: true }).click();
    await dialog.getByRole('listbox', { name: 'Minuty', exact: true }).getByRole('option', { name: '37', exact: true }).click();
    await page.screenshot({ path: test.info().outputPath('time-picker-desktop.png') });
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('21.09.2026 23:37');
    await page.getByRole('button', { name: 'Wybierz: Koniec', exact: true }).click();
    dialog = page.getByRole('dialog', { name: 'Koniec', exact: true });
    await dialog.getByRole('gridcell', { name: /22 września 2026/ }).click();
    await dialog.getByRole('tab', { name: 'Godzina', exact: true }).click();
    await dialog.getByRole('listbox', { name: 'Godziny', exact: true }).getByRole('option', { name: '01', exact: true }).click();
    await dialog.getByRole('listbox', { name: 'Minuty', exact: true }).getByRole('option', { name: '13', exact: true }).click();
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    expect(state.writes).toHaveLength(0);
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data.schedule).toEqual({ kind: 'TIMED', localStart: '2026-09-21T23:37', durationMinutes: 96, timeZone: 'Europe/Warsaw' });
});

test('all-day and recurrence date pickers preserve exclusive end dates and enforce the recurrence minimum', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wyjazd');
    await page.getByLabel('Cały dzień', { exact: true }).check();
    await page.getByRole('button', { name: 'Wybierz: Początek', exact: true }).click();
    let dialog = page.getByRole('dialog', { name: 'Początek', exact: true });
    await expect(dialog.getByRole('tab')).toHaveCount(0);
    await dialog.getByLabel('Miesiąc', { exact: true }).selectOption('10');
    await dialog.getByRole('gridcell', { name: /, 4 października 2026$/ }).click();
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await page.getByRole('button', { name: /Wybierz: Koniec/ }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel('Miesiąc', { exact: true }).selectOption('10');
    await dialog.getByRole('gridcell', { name: /, 6 października 2026$/ }).click();
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await page.getByLabel('Powtarzanie', { exact: true }).selectOption('WEEKLY');
    await page.getByLabel('Koniec cyklu', { exact: true }).selectOption('until');
    await page.getByRole('button', { name: 'Wybierz: Ostatni dzień cyklu', exact: true }).click();
    dialog = page.getByRole('dialog', { name: 'Ostatni dzień cyklu', exact: true });
    await dialog.getByLabel('Miesiąc', { exact: true }).selectOption('10');
    await expect(dialog.getByRole('gridcell', { name: /, 3 października 2026$/ })).toBeDisabled();
    await dialog.getByLabel('Miesiąc', { exact: true }).selectOption('11');
    await dialog.getByLabel('Miesiąc', { exact: true }).selectOption('10');
    const firstAllowed = dialog.getByRole('gridcell', { name: /, 4 października 2026$/ });
    await expect(firstAllowed).toHaveAttribute('tabindex', '0');
    await dialog.getByLabel('Miesiąc', { exact: true }).focus();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await expect(firstAllowed).toBeFocused();
    await dialog.getByRole('gridcell', { name: /18 października 2026/ }).click();
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data.schedule).toEqual({ kind: 'ALL_DAY', startDate: '2026-10-04', endDate: '2026-10-06', timeZone: 'Europe/Warsaw' });
    expect(state.writes.at(-1).data.recurrence.until).toBe('2026-10-18');
});

test('planning date and time-window pickers apply values only when confirmed', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await dateButton(page).click();
    await expect(page.getByRole('dialog', { name: 'Data planu', exact: true })).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Anuluj', exact: true }).click();
    await page.getByRole('tab', { name: 'Znajdź termin', exact: true }).click();
    await page.getByRole('button', { name: 'Wybierz: Od dnia', exact: true }).click();
    await page.getByRole('dialog').getByRole('gridcell', { name: /22 września 2026/ }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Wybierz', exact: true }).click();
    for (const [field, hour, minute] of [['Szukaj od godziny', '16', '37'], ['Szukaj do godziny', '18', '41']]) {
        await page.getByRole('button', { name: `Wybierz: ${field}`, exact: true }).click();
        const dialog = page.getByRole('dialog', { name: field, exact: true });
        await dialog.getByRole('listbox', { name: 'Godziny', exact: true }).getByRole('option', { name: hour, exact: true }).click();
        await dialog.getByRole('listbox', { name: 'Minuty', exact: true }).getByRole('option', { name: minute, exact: true }).click();
        await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    }
    expect(state.writes).toHaveLength(0);
    await page.getByRole('button', { name: 'Pokaż wspólne terminy', exact: true }).click();
    await expect(page.getByRole('button', { name: /Wybierz termin/ })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ windowStart: '16:37', windowEnd: '18:41', from: '2026-09-21T22:00:00.000Z' });
});

test('date picker localizes English labels and uses Sunday as the first column', async ({ page }) => {
    await setup(page, { locale: 'en' });
    await dateButton(page, 'Calendar date').click();
    const dialog = page.getByRole('dialog', { name: 'Calendar date', exact: true });
    await expect(dialog.getByRole('columnheader').first()).toHaveText('Sun');
    await dialog.getByRole('gridcell', { name: /September 23, 2026/ }).click();
    await dialog.getByRole('button', { name: 'Choose', exact: true }).click();
    await expect(dateButton(page, 'Calendar date')).toHaveText(/: Wednesday, September 23, 2026/);
});

test('date picker fits a narrow dark viewport and traps keyboard focus until cancelled', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 760 });
    await page.emulateMedia({ colorScheme: 'dark' });
    await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByRole('button', { name: 'Wybierz: Początek', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Początek', exact: true });
    const bounds = await dialog.boundingBox();
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(320);
    expect(await dialog.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    for (let index = 0; index < 12; index++) {
        await page.keyboard.press('Tab');
        expect(await page.evaluate(() => document.activeElement.closest('dialog') !== null)).toBe(true);
    }
    await page.screenshot({ path: test.info().outputPath('date-picker-narrow-dark.png') });
    await dialog.getByRole('tab', { name: 'Godzina', exact: true }).click();
    await page.screenshot({ path: test.info().outputPath('time-picker-narrow-dark.png') });
    await dialog.getByRole('button', { name: 'Anuluj', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Wybierz: Początek', exact: true })).toBeFocused();
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('21.09.2026 09:00');
});

test('date picker rejects impossible typed dates without replacing the calendar query', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('tab', { name: 'Znajdź termin', exact: true }).click();
    const field = page.getByLabel('Od dnia', { exact: true });
    await field.fill('2026-02-30');
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    expect(await field.evaluate((element) => element.checkValidity())).toBe(false);
    await page.getByRole('tab', { name: 'Mój plan', exact: true }).click();
    await expect(dateButton(page)).toHaveText(/: Poniedziałek, 21 września 2026/);
    expect(state.queries.at(-1).get('from')).toBe('2026-09-20T22:00:00.000Z');
    await page.getByRole('tab', { name: 'Znajdź termin', exact: true }).click();
    await page.getByLabel('Od dnia', { exact: true }).fill('2028-02-29');
    await expect(page.getByLabel('Od dnia', { exact: true })).toHaveAttribute('aria-invalid', 'false');
    await page.getByRole('tab', { name: 'Mój plan', exact: true }).click();
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2028-02-28T23:00:00.000Z');
});

test('localized date and time input keeps local API values and normalizes the displayed format', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wpisany z klawiatury');
    await page.getByLabel('Początek', { exact: true }).fill('29.02.2028 07:13');
    await page.getByLabel('Koniec', { exact: true }).fill('2028-02-29T08:27');
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('29.02.2028 07:13');
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('29.02.2028 08:27');
    await expect(page.getByLabel('Początek', { exact: true })).toHaveAttribute('placeholder', 'DD.MM.RRRR GG:mm');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data.schedule).toEqual({ kind: 'TIMED', localStart: '2028-02-29T07:13', durationMinutes: 74, timeZone: 'Europe/Warsaw' });
});

test('an open picker cannot modify a field disabled by its parent fieldset', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByRole('button', { name: 'Wybierz: Początek', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Początek', exact: true });
    await dialog.getByRole('gridcell', { name: /22 września 2026/ }).click();
    await page.locator('input[aria-label="Początek"]').evaluate((element) => { element.closest('fieldset').disabled = true; });
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('21.09.2026 09:00');
    expect(state.writes).toHaveLength(0);
});

test('shows only the essential controls and filters by tags from every catalog', async ({ page }) => {
    const state = await setup(page, { scopedTags: true });
    const planPage = page.locator('section.day-planning');
    await expect(planPage.getByRole('tab')).toHaveCount(3);
    await expect(planPage.getByRole('combobox')).toHaveCount(1);
    await expect(page.getByLabel('Strefa wyświetlania', { exact: true })).toHaveValue('Europe/Warsaw');
    await expect(page.getByRole('button', { name: 'Dzień', exact: true })).toHaveAttribute('aria-pressed', 'true');
    const filter = page.getByRole('group', { name: 'Tagi', exact: true });
    await expect(filter.getByRole('button', { name: 'Lekcje', exact: true })).toBeVisible();
    await expect(filter.getByRole('button', { name: 'Praca', exact: true })).toBeVisible();
    expect(new Set(state.tagQueries)).toEqual(new Set([null, 'team-1']));
    await expect(page.getByRole('button', { name: 'Wyczyść', exact: true })).toHaveCount(0);
    await filter.getByRole('button', { name: 'Lekcje', exact: true }).click();
    await expect.poll(() => state.queries.at(-1).getAll('tagIds[]')).toEqual(['school']);
    await page.getByRole('button', { name: 'Wyczyść', exact: true }).click();
    await expect.poll(() => state.queries.at(-1).getAll('tagIds[]')).toEqual([]);
    await expect(page.getByText('Prywatne wydarzenia innych osób widać tylko jako „Zajęty”.')).toHaveCount(0);
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await expect(page.getByLabel('Zespół', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('group', { name: 'Osoby', exact: true }).getByRole('checkbox')).toHaveCount(2);
    await expect(page.getByText('Prywatne wydarzenia innych osób widać tylko jako „Zajęty”.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Ty', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Bartek', exact: true })).toBeVisible();
});

test('keeps a person without a team in one personal view', async ({ page }) => {
    await setup(page, { teams: [], event: { ...sample(), teamId: null, visibility: 'PRIVATE', participantIds: ['1'], participants: [{ personId: '1', status: 'INCLUDED' }] } });
    await expect(page.getByRole('tab')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Plan lekcji, / })).toBeVisible();
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await expect(page.getByLabel('Tytuł wydarzenia', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Zespół wydarzenia', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('group', { name: 'Widoczność', exact: true })).toHaveCount(0);
    await expect(page.getByRole('group', { name: 'Uczestnicy', exact: true })).toHaveCount(0);
});

test('offers a team picker only to people in several teams', async ({ page }) => {
    const state = await setup(page, { teams: [{ id: 'team-1', name: 'Family Team', role: 'member' }, { id: 'team-2', name: 'Klub sportowy', role: 'member' }] });
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await expect(page.getByLabel('Zespół', { exact: true })).toHaveValue('team-1');
    await expect(page.getByLabel('Bartek', { exact: true })).toBeChecked();
    await page.getByLabel('Bartek', { exact: true }).uncheck();
    await expect.poll(() => state.queries.at(-1).getAll('personIds[]')).toEqual(['1']);
    await page.getByLabel('Zespół', { exact: true }).selectOption('team-2');
    await expect(page.getByLabel('Celina', { exact: true })).toBeChecked();
    await expect(page.getByLabel('Bartek', { exact: true })).toHaveCount(0);
    await expect.poll(() => state.queries.at(-1).get('teamId')).toBe('team-2');
    expect(state.queries.at(-1).getAll('personIds[]')).toEqual(['1', '3']);
});

test('keeps rarely used event options folded away and always shares a team event with its team', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await expect(page.getByRole('group', { name: 'Widoczność', exact: true })).toHaveCount(0);
    await expect(page.getByRole('group', { name: 'Uczestnicy', exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Strefa wydarzenia', { exact: true })).toBeHidden();
    await expect(page.getByLabel('Zajmuje czas uczestników', { exact: true })).toBeHidden();
    await expect(page.getByLabel('Zespół wydarzenia', { exact: true })).toHaveAccessibleDescription('Szczegóły widzisz tylko Ty.');
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Lot do Londynu');
    await page.getByText('Więcej opcji', { exact: true }).click();
    await page.getByLabel('Strefa wydarzenia', { exact: true }).fill('Europe/London');
    await page.getByLabel('Zajmuje czas uczestników', { exact: true }).uncheck();
    await page.getByLabel('Zespół wydarzenia', { exact: true }).selectOption('team-1');
    await expect(page.getByRole('radio')).toHaveCount(0);
    await expect(page.getByText('Widoczność', { exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Zespół wydarzenia', { exact: true })).toHaveAccessibleDescription('Szczegóły zobaczą wszyscy członkowie zespołu Family Team.');
    await expect(page.getByRole('group', { name: 'Uczestnicy', exact: true }).getByRole('checkbox')).toHaveCount(2);
    await page.screenshot({ path: test.info().outputPath('team-event-editor.png'), fullPage: true });
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ title: 'Lot do Londynu', teamId: 'team-1', visibility: 'TEAM', blocksTime: false, schedule: { kind: 'TIMED', localStart: '2026-09-21T09:00', durationMinutes: 60, timeZone: 'Europe/London' } });
});

test('creates team tags for any team the user administers', async ({ page }) => {
    const state = await setup(page, { teams: [{ id: 'team-1', name: 'Family Team', role: 'admin' }, { id: 'team-2', name: 'Klub sportowy', role: 'admin' }] });
    await page.getByRole('button', { name: 'Zarządzaj tagami', exact: true }).click();
    const scope = page.getByLabel('Zakres tagu', { exact: true });
    await expect(scope.locator('option')).toHaveText(['Tag osobisty', 'Tag zespołu Family Team', 'Tag zespołu Klub sportowy']);
    await expect(page.locator('.day-tag-list').getByText('Tag zespołu Family Team', { exact: true })).toBeVisible();
    await page.getByLabel('Nazwa tagu', { exact: true }).fill('Mecz');
    await scope.selectOption('team-2');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Edytuj tag Mecz', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ name: 'Mecz', scope: 'TEAM', teamId: 'team-2' });
});

test('opens the team plan on a team where somebody else is waiting', async ({ page }) => {
    const state = await setup(page, { teams: [{ id: 'team-solo', name: 'Moja rodzina', role: 'admin' }, { id: 'team-1', name: 'Family Team', role: 'member' }] });
    await page.getByRole('tab', { name: 'Plan zespołu', exact: true }).click();
    await expect(page.getByLabel('Zespół', { exact: true })).toHaveValue('team-1');
    await expect(page.getByLabel('Bartek', { exact: true })).toBeChecked();
    await expect.poll(() => state.queries.at(-1).get('teamId')).toBe('team-1');
    await page.getByLabel('Zespół', { exact: true }).selectOption('team-solo');
    await expect(page.getByLabel('Bartek', { exact: true })).toHaveCount(0);
    await expect.poll(() => state.queries.at(-1).get('teamId')).toBe('team-solo');
});

const monthEvents = () => [
    { ...sample(), id: 'lesson', title: 'Plan lekcji', start: '2026-09-21T07:00:00Z', end: '2026-09-21T08:00:00Z' },
    { ...sample(), id: 'trip', title: 'Wycieczka klasowa', allDay: true, tags: [{ id: 'trip-tag', name: 'Wyjazdy', color: '#a45132' }], start: '2026-09-22T22:00:00Z', end: '2026-09-25T22:00:00Z' },
    ...Array.from({ length: 6 }, (_, index) => ({ ...sample(), id: `errand-${index + 1}`, title: `Sprawa ${index + 1}`, tags: [{ id: 'work', name: 'Praca', color: '#325f99' }], start: `2026-09-24T${String(5 + index).padStart(2, '0')}:00:00Z`, end: `2026-09-24T${String(5 + index).padStart(2, '0')}:30:00Z` })),
    { ...sample(), id: 'night', title: 'Nocny pociąg', start: '2026-09-26T20:00:00Z', end: '2026-09-27T05:00:00Z' },
    { ...sample(), id: 'october', title: 'Wywiadówka', start: '2026-10-02T15:00:00Z', end: '2026-10-02T16:00:00Z' },
];

test('opens the calendar on the whole screen and moves between month, day and week like Google Calendar', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const state = await setup(page, { events: monthEvents() });
    const screen = page.locator('section.day-planning');
    await page.getByRole('button', { name: 'Pełny ekran', exact: true }).click();
    await expect(screen).toHaveClass(/is-fullscreen/);
    await expect(page.getByRole('button', { name: 'Zamknij pełny ekran', exact: true })).toBeFocused();
    await expect.poll(() => page.evaluate(() => document.fullscreenElement === document.documentElement)).toBe(true);
    expect(await screen.boundingBox()).toEqual({ x: 0, y: 0, width: 1440, height: 900 });
    expect(await page.evaluate(() => [[8, 8], [1432, 892]].every(([x, y]) => document.elementFromPoint(x, y)?.closest('section.day-planning')))).toBe(true);
    await expect(page.locator('.app-header, nav.app-nav')).toHaveCount(0);
    await page.locator('body').focus();
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => !!document.activeElement.closest('section.day-planning.is-fullscreen'))).toBe(true);
    await expect(page.getByRole('tab')).toHaveCount(0);
    await expect(page.getByLabel('Widoki planu dnia', { exact: true }).locator('option')).toHaveText(['Mój plan', 'Plan zespołu']);
    await page.getByRole('button', { name: 'Miesiąc', exact: true }).click();
    const month = page.getByRole('region', { name: 'Kalendarz miesięczny', exact: true });
    await expect(month).toBeVisible();
    await expect(dateButton(page)).toHaveText(/: Wrzesień 2026/);
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-08-30T22:00:00.000Z');
    expect(state.queries.at(-1).get('to')).toBe('2026-10-04T22:00:00.000Z');
    await expect(month.locator('.day-month-cell')).toHaveCount(35);
    await expect(month.locator('.day-month-weekdays span')).toHaveText(['pon.', 'wt.', 'śr.', 'czw.', 'pt.', 'sob.', 'niedz.']);
    await expect(month.locator('.day-month-cell.is-today')).toHaveAttribute('data-date', '2026-09-21');
    await expect(month.locator('.day-month-cell.is-outside')).toHaveCount(5);
    for (const day of ['2026-09-23', '2026-09-24', '2026-09-25']) await expect(month.locator(`[data-date="${day}"]`).getByRole('button', { name: /^Wycieczka klasowa, Cały dzień/ })).toBeVisible();
    await expect(month.locator('[data-date="2026-09-26"]')).not.toContainText('Wycieczka klasowa');
    for (const day of ['2026-09-26', '2026-09-27']) await expect(month.locator(`[data-date="${day}"]`)).toContainText('Nocny pociąg');
    const crowded = month.locator('[data-date="2026-09-24"]');
    const more = crowded.getByRole('button', { name: /więcej: Czwartek, 24 września 2026$/ });
    await expect(more).toBeVisible();
    const box = await crowded.boundingBox();
    const lastLine = await crowded.locator('li').last().boundingBox();
    expect(lastLine.y + lastLine.height).toBeLessThanOrEqual(box.y + box.height);
    await page.screenshot({ path: test.info().outputPath('fullscreen-month-desktop.png') });
    await more.click();
    const list = page.getByRole('dialog', { name: 'Czwartek, 24 września 2026', exact: true });
    await expect(list.getByRole('button', { name: /^Sprawa \d, / })).toHaveCount(6);
    await list.getByRole('button', { name: /^Sprawa 6, / }).click();
    await expect(page.getByRole('dialog', { name: 'Sprawa 6', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Zamknij', exact: true }).click();
    await page.getByRole('button', { name: 'Następny zakres', exact: true }).click();
    await expect(dateButton(page)).toHaveText(/: Październik 2026/);
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-09-27T22:00:00.000Z');
    await month.getByRole('button', { name: /^Pokaż dzień: Piątek, 2 października 2026, 1 wydarzenie$/ }).click();
    await expect(page.getByRole('button', { name: 'Dzień', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(dateButton(page)).toHaveText(/: Piątek, 2 października 2026/);
    await expect(page.getByRole('button', { name: /^Wywiadówka, / })).toBeVisible();
    await expect(screen).toHaveClass(/is-fullscreen/);
    await page.screenshot({ path: test.info().outputPath('fullscreen-day-desktop.png') });
    await page.getByRole('button', { name: 'Zamknij pełny ekran', exact: true }).click();
    await expect(screen).not.toHaveClass(/is-fullscreen/);
    await expect(page.getByRole('button', { name: 'Pełny ekran', exact: true })).toBeFocused();
    await expect(page.getByRole('tab')).toHaveCount(3);
    await expect(page.locator('.app-header')).toHaveCount(1);
    expect(await page.evaluate(() => document.fullscreenElement)).toBeNull();
});

test('keeps editing and the team plan inside the full screen and leaves it with Escape', async ({ page }) => {
    const state = await setup(page, { event: null });
    const screen = page.locator('section.day-planning');
    await page.getByRole('button', { name: 'Pełny ekran', exact: true }).click();
    await page.getByLabel('Widoki planu dnia', { exact: true }).selectOption('team');
    await expect(page.getByRole('group', { name: 'Osoby', exact: true }).getByRole('checkbox')).toHaveCount(2);
    await page.getByRole('button', { name: 'Miesiąc', exact: true }).click();
    const busy = page.locator('.day-month-cell[data-date="2026-09-21"] .day-event--busy');
    await expect(busy).toHaveText('12:00Zajęty · Bartek');
    await expect(busy).toHaveAttribute('aria-label', 'Zajęty, 12:00–13:00, Bartek');
    expect(await busy.evaluate((element) => element.tagName)).toBe('DIV');
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await expect(page.locator('.day-planning.is-fullscreen .day-editor')).toBeVisible();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Kino');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Wydarzenie zapisane' })).toBeVisible();
    await expect(screen).toHaveClass(/is-fullscreen/);
    await expect(page.getByRole('region', { name: 'Kalendarz miesięczny', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toMatchObject({ title: 'Kino', teamId: 'team-1' });
    await page.locator('.day-month-cell[data-date="2026-09-21"]').getByRole('button', { name: /^Kino, 09:00–10:00/ }).click();
    await expect(page.getByRole('dialog', { name: 'Kino', exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(screen).toHaveClass(/is-fullscreen/);
    await page.keyboard.press('Escape');
    await expect(screen).not.toHaveClass(/is-fullscreen/);
    await expect(page.getByRole('tab', { name: 'Plan zespołu', exact: true })).toHaveAttribute('aria-selected', 'true');
});

test('fills the screen with the week grid, marks the current time and opens a day from its header', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await setup(page, { now: '2026-09-23T12:30:00Z' });
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    const scroller = page.locator('.day-week-scroll');
    const regular = await scroller.boundingBox();
    const line = page.locator('.day-week-now');
    await expect(line).toHaveCount(1);
    await expect(page.locator('.day-week-timeline[data-date="2026-09-23"] .day-week-now')).toHaveAttribute('style', /top: 870px/);
    await page.getByRole('button', { name: 'Pełny ekran', exact: true }).click();
    await expect.poll(async () => (await scroller.boundingBox()).height).toBeGreaterThan(regular.height);
    const filled = await scroller.boundingBox();
    expect(filled.y + filled.height).toBeGreaterThan(780);
    await page.screenshot({ path: test.info().outputPath('fullscreen-week-desktop.png') });
    await page.getByRole('button', { name: /^Pokaż dzień: Piątek, 25 września 2026$/ }).click();
    await expect(page.getByRole('button', { name: 'Dzień', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(dateButton(page)).toHaveText(/: Piątek, 25 września 2026/);
    await expect.poll(() => page.evaluate(() => !!document.fullscreenElement)).toBe(true);
    await page.evaluate(() => document.exitFullscreen());
    await expect(page.locator('section.day-planning')).not.toHaveClass(/is-fullscreen/);
    await expect(page.locator('.app-header')).toHaveCount(1);
});

test('fits the full-screen month and a seven-column week on a narrow dark phone', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ colorScheme: 'dark' });
    await setup(page, { events: monthEvents() });
    await page.getByRole('button', { name: 'Pełny ekran', exact: true }).click();
    await page.getByRole('button', { name: 'Miesiąc', exact: true }).click();
    const screen = page.locator('section.day-planning');
    await expect(page.locator('.day-month-cell')).toHaveCount(35);
    expect(await screen.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    expect(await page.locator('.day-month-cell[data-date="2026-09-24"]').evaluate((element) => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('fullscreen-month-narrow-dark.png') });
    await page.getByRole('button', { name: 'Tydzień', exact: true }).click();
    const scroller = page.locator('.day-week-scroll');
    await expect(page.locator('.day-week-header')).toHaveCount(7);
    expect(await scroller.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    const monday = await page.locator('.day-week-header').first().boundingBox();
    const sunday = await page.locator('.day-week-header').last().boundingBox();
    expect(sunday.x + sunday.width).toBeLessThanOrEqual(390);
    expect(monday.width).toBeGreaterThan(40);
    await page.getByRole('button', { name: /^Plan lekcji, / }).scrollIntoViewIfNeeded();
    await page.screenshot({ path: test.info().outputPath('fullscreen-week-narrow-dark.png') });
});

test('switches the regular page to a month, names it in English and keeps Monday first', async ({ page }) => {
    const state = await setup(page, { locale: 'en', events: monthEvents() });
    await page.getByRole('button', { name: 'Month', exact: true }).click();
    await expect(dateButton(page, 'Calendar date')).toHaveText(/: September 2026/);
    await expect(page.locator('.day-month-weekdays span')).toHaveText(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    await expect(page.locator('.day-month-cell[data-date="2026-10-01"] .day-month-day')).toHaveText('Oct 1');
    await page.screenshot({ path: test.info().outputPath('month-regular-desktop.png'), fullPage: true });
    await page.getByRole('button', { name: 'Previous range', exact: true }).click();
    await expect(dateButton(page, 'Calendar date')).toHaveText(/: August 2026/);
    await expect.poll(() => state.queries.at(-1).get('from')).toBe('2026-07-26T22:00:00.000Z');
    expect(state.queries.at(-1).get('to')).toBe('2026-09-06T22:00:00.000Z');
    await expect(page.locator('.day-month-cell')).toHaveCount(42);
    await page.getByRole('button', { name: 'Week', exact: true }).click();
    await expect(dateButton(page, 'Calendar date')).toHaveText(/: August 17\s?–\s?23, 2026/);
});

test('checks the weekday of the chosen date for a weekly repeat and keeps the event length', async ({ page }) => {
    const state = await setup(page, { event: null });
    await page.getByRole('button', { name: 'Nowe wydarzenie', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Basen');
    await page.getByLabel('Początek', { exact: true }).fill('23.09.2026 17:00');
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('23.09.2026 18:00');
    await page.getByLabel('Powtarzanie', { exact: true }).selectOption('WEEKLY');
    const days = page.getByRole('group', { name: 'Dni tygodnia', exact: true });
    const checked = () => days.getByRole('checkbox').evaluateAll((boxes) => boxes.filter((box) => box.checked).map((box) => box.parentElement.textContent));
    await expect.poll(checked).toEqual(['śr.']);
    await page.getByLabel('Początek', { exact: true }).fill('25.09.2026 17:00');
    await expect.poll(checked).toEqual(['pt.']);
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('25.09.2026 18:00');
    await days.getByLabel('wt.', { exact: true }).check();
    await page.getByLabel('Początek', { exact: true }).fill('26.09.2026 17:00');
    await expect.poll(checked).toEqual(['wt.', 'pt.', 'sob.']);
    await page.getByLabel('Powtarzanie', { exact: true }).selectOption('DAILY');
    await page.getByLabel('Początek', { exact: true }).fill('27.09.2026 17:00');
    await page.getByLabel('Powtarzanie', { exact: true }).selectOption('WEEKLY');
    await expect.poll(checked).toEqual(['wt.', 'pt.', 'sob.', 'niedz.']);
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data.schedule).toEqual({ kind: 'TIMED', localStart: '2026-09-27T17:00', durationMinutes: 60, timeZone: 'Europe/Warsaw' });
    expect(state.writes.at(-1).data.recurrence).toMatchObject({ frequency: 'WEEKLY', byDay: [2, 5, 6, 7] });
});

test('moves a weekly series to the weekday of its new start date', async ({ page }) => {
    const state = await setup(page);
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('radio', { name: 'Cała seria', exact: true }).check();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    const days = page.getByRole('group', { name: 'Dni tygodnia', exact: true });
    await expect(days.getByLabel('pon.', { exact: true })).toBeChecked();
    await page.getByRole('button', { name: 'Wybierz: Początek', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Początek', exact: true });
    await dialog.getByRole('gridcell', { name: /24 września 2026/ }).click();
    await dialog.getByRole('button', { name: 'Wybierz', exact: true }).click();
    await expect(days.getByLabel('czw.', { exact: true })).toBeChecked();
    await expect(days.getByLabel('pon.', { exact: true })).not.toBeChecked();
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('24.09.2026 10:00');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1)).toMatchObject({ method: 'PATCH', data: { schedule: { localStart: '2026-09-24T09:00', durationMinutes: 60 }, recurrence: { frequency: 'WEEKLY', byDay: [4] } } });
});

test('shares an older private team series with its team once it is edited', async ({ page }) => {
    const privateTag = { id: 'work', name: 'Praca', color: '#3955af' };
    const state = await setup(page, { scopedTags: true, event: { ...sample(), visibility: 'PRIVATE', tags: [privateTag] }, definition: { ...definition(), visibility: 'PRIVATE', tags: [privateTag], tagIds: ['work'] } });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await expect(page.getByRole('dialog')).toContainText('Prywatne');
    await page.getByRole('radio', { name: 'Cała seria', exact: true }).check();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await expect(page.getByLabel('Zespół wydarzenia', { exact: true })).toHaveAccessibleDescription('Szczegóły zobaczą wszyscy członkowie zespołu Family Team.');
    await expect(page.getByRole('radio')).toHaveCount(0);
    await expect(page.getByRole('alert').filter({ hasText: 'Wydarzenie zespołu może mieć tylko tagi tego zespołu.' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Zapisz', exact: true })).toBeDisabled();
    await page.getByRole('button', { name: 'Usuń niedostępne tagi', exact: true }).click();
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1)).toMatchObject({ method: 'PATCH', data: { teamId: 'team-1', visibility: 'TEAM', tagIds: [] } });
});

test('shares one edited occurrence of an older private team series with the team', async ({ page }) => {
    const state = await setup(page, { event: { ...sample(), visibility: 'PRIVATE' }, definition: { ...definition(), visibility: 'PRIVATE' } });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Plan lekcji w auli');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(decodeURIComponent(state.writes.at(-1).path)).toBe('/api/day-planning/events/event-1/exceptions/2026-09-21T09:00');
    expect(state.writes.at(-1).data).toEqual({ changes: { title: 'Plan lekcji w auli', visibility: 'TEAM' } });
});
