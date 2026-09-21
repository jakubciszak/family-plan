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
    await page.addInitScript(() => localStorage.setItem('i18nextLng', 'pl'));
    await page.route('**/api/**', (route) => route.fulfill({ status: 404, json: {} }));
    await setupAuthenticatedSession(page);
    await page.route('**/api/teams/team-1/members', (route) => route.fulfill({ json: { members: [{ userId: '1', name: 'Anna' }, { userId: '2', name: 'Bartek' }] } }));
    await page.route('**/api/day-planning/**', async (route) => {
        const req = route.request();
        const url = new URL(req.url());
        const path = url.pathname;
        if (path.endsWith('/calendar')) {
            state.queries.push(url.searchParams);
            const selectedTags = url.searchParams.getAll('tagIds[]');
            return route.fulfill({ json: { events: state.event && (!selectedTags.length || selectedTags.includes('school')) ? [state.event] : [], busy: url.searchParams.get('teamId') ? [{ kind: 'busy', personId: '2', start: '2026-09-21T10:00:00Z', end: '2026-09-21T11:00:00Z' }] : [], coverage: { complete: true } } });
        }
        if (path.endsWith('/tags') && req.method() === 'GET') return route.fulfill({ json: { tags: state.tags } });
        if (req.method() !== 'GET') state.writes.push({ method: req.method(), path, data: req.postDataJSON(), headers: req.headers() });
        if (path.includes('/tags')) {
            if (req.method() === 'DELETE') { state.tags = state.tags.filter((tag) => !path.endsWith(`/${tag.id}`)); return route.fulfill({ status: 204 }); }
            const tag = { ...req.postDataJSON(), id: req.postDataJSON().id || 'tag-new', canEdit: true };
            state.tags = [...state.tags.filter((item) => item.id !== tag.id), tag];
            return route.fulfill({ json: tag });
        }
        if (path.endsWith('/suggestions')) return route.fulfill({ json: { slots: [{ start: '2026-09-21T17:00:00Z', end: '2026-09-21T18:00:00Z' }], personIds: ['1', '2'], busy: [], coverage: { complete: true } } });
        if (path.includes('/participation/me') && state.participationConflict && req.postDataJSON().status === 'INCLUDED' && !req.postDataJSON().conflictConfirmation) return route.fulfill({ status: 409, json: { code: 'planning_conflict', confirmationToken: 'join-token' } });
        if (path.includes('/participation/me')) { state.event.participation = req.postDataJSON().status; return route.fulfill({ json: req.postDataJSON() }); }
        if (path.includes('/occurrences/')) return route.fulfill({ json: state.event });
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
    await page.goto('/');
    await page.getByRole('button', { name: 'Plan dnia', exact: true }).click();
    await page.getByLabel('Data planu', { exact: true }).fill('2026-09-21');
    await page.getByLabel('Strefa wyświetlania', { exact: true }).selectOption('Europe/Warsaw');
    await expect(page.getByRole('status').filter({ hasText: 'Ładowanie' })).toHaveCount(0);
    return state;
}

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
    await expect(page.getByLabel('Test User · Ty', { exact: true })).toBeDisabled();
    await page.getByLabel('Szukaj od godziny', { exact: true }).fill('16:00');
    await page.getByRole('button', { name: 'Pokaż wspólne terminy', exact: true }).click();
    await page.getByRole('button', { name: /Wybierz termin/ }).click();
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('2026-09-21T19:00');
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('2026-09-21T20:00');
    await expect(page.getByLabel('Bartek', { exact: true })).toBeChecked();
    const query = state.writes.find((write) => write.path.endsWith('/suggestions')).data;
    expect(query.personIds).toEqual(['1', '2']);
    expect(query.tagIds).toBeUndefined();
    expect(query.windowStart).toBe('16:00');
});

test('allows invitees to decline one occurrence and rejoin the series without editing', async ({ page }) => {
    const event = { ...sample(), canEdit: false, ownerId: '2', visibility: 'PRIVATE' };
    const state = await setup(page, { event });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await expect(page.getByRole('button', { name: 'Edytuj', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Rezygnuję z udziału', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Wracam do udziału', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ status: 'DECLINED', occurrenceKey: '2026-09-21T09:00' });
    await page.getByLabel('Zakres zmiany', { exact: true }).selectOption('series');
    await page.getByRole('button', { name: 'Wracam do udziału', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Rezygnuję z udziału', exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ status: 'INCLUDED', occurrenceKey: null });
});

test('retains unsaved edits on a version conflict and requires refreshing', async ({ page }) => {
    const state = await setup(page, { failure: { status: 412, json: { code: 'version_conflict' } } });
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByLabel('Zakres zmiany', { exact: true }).selectOption('series');
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
    await expect(page.getByLabel('Zakres tagu', { exact: true }).locator('option[value="TEAM"]')).toHaveCount(0);
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
    await page.getByLabel('Zakres zmiany', { exact: true }).selectOption('series');
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
    await page.getByLabel('Zakres', { exact: true }).selectOption('week');
    await expect(page.locator('.day-column-title')).toHaveCount(7);
    await expect.poll(() => new Date(state.queries.at(-1).get('to')) - new Date(state.queries.at(-1).get('from'))).toBe(7 * 86400000);
    await page.getByRole('button', { name: 'Następny zakres', exact: true }).click();
    await expect(page.getByLabel('Data planu', { exact: true })).toHaveValue('2026-09-28');
    await page.getByRole('button', { name: 'Poprzedni zakres', exact: true }).click();
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Usuń', exact: true }).click();
    await expect(page.getByText('Wydarzenie usunięte.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1)).toMatchObject({ method: 'PUT', data: { cancelled: true } });
    expect(decodeURIComponent(state.writes.at(-1).path)).toContain('/exceptions/2026-09-21T09:00');
});

test('invalidates open details when a calendar notification arrives', async ({ page }) => {
    await page.clock.install();
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
    const state = await setup(page, { event });
    await page.getByLabel('Data planu', { exact: true }).fill('2026-10-25');
    await page.getByRole('button', { name: /Plan lekcji, / }).click();
    await page.getByRole('button', { name: 'Edytuj', exact: true }).click();
    await expect(page.getByLabel('Początek', { exact: true })).toHaveValue('2026-10-25T02:30');
    await expect(page.getByLabel('Koniec', { exact: true })).toHaveValue('2026-10-25T02:30');
    await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Przesunięcie zegara');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByText('Wydarzenie zapisane.', { exact: true })).toBeVisible();
    expect(state.writes.at(-1).data).toEqual({ changes: { title: 'Przesunięcie zegara' } });
});
