const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

const sample = () => ({
    id: '1f6e22e6-90b6-44b7-948c-033610ae7411', name: 'Sprzątanie pokoju',
    steps: [{ name: 'Pościelić łóżko', stages: [] }, { name: 'Posprzątać szafę', stages: ['Zdjąć lego', 'Odkurzyć półki'] }],
    estimatedMinutes: 3, reminderMinutes: 10,
});

async function setup(page, initial = [sample()]) {
    let plans = initial;
    await page.addInitScript(() => {
        localStorage.setItem('i18nextLng', 'pl');
        window.NativeAudioContext = window.AudioContext;
        window.reminderSounds = 0;
        window.reminderNotes = [];
        window.AudioContext = class {
            state = 'running';
            currentTime = 0;
            destination = {};
            resume() { return Promise.resolve(); }
            close() { return Promise.resolve(); }
            createOscillator() { return { frequency: {}, connect() {}, disconnect() {}, start(at) { window.reminderSounds++; window.reminderNotes.push({ frequency: this.frequency.value, at }); }, stop() {} }; }
            createGain() { return { gain: { setValueAtTime() {}, linearRampToValueAtTime() {}, exponentialRampToValueAtTime() {} }, connect() {}, disconnect() {} }; }
        };
    });
    await page.route('**/api/**', (route) => route.fulfill({ status: 404, json: {} }));
    await setupAuthenticatedSession(page);
    await page.route('**/api/action-plans**', async (route) => {
        const request = route.request();
        const id = request.url().split('/').pop();
        if (request.method() === 'GET') return route.fulfill({ json: { plans } });
        if (request.method() === 'DELETE') {
            plans = plans.filter((plan) => plan.id !== id);
            return route.fulfill({ status: 204 });
        }
        const plan = { ...request.postDataJSON(), id: request.method() === 'POST' ? sample().id : id };
        plans = [...plans.filter((item) => item.id !== plan.id), plan];
        return route.fulfill({ status: request.method() === 'POST' ? 201 : 200, json: plan });
    });
    await page.goto('/');
    await page.getByRole('button', { name: 'Plany działania', exact: true }).click();
}

test('creates, reorders, edits and deletes a reusable plan with stages', async ({ page }) => {
    await setup(page, []);
    await page.getByRole('button', { name: 'Nowy plan', exact: true }).click();
    await page.getByLabel('Nazwa planu', { exact: true }).fill('Pakowanie');
    await page.getByRole('textbox', { name: 'Krok 1', exact: true }).fill('Ubrania');
    await page.getByRole('button', { name: 'Dodaj etap', exact: true }).click();
    await page.getByLabel('Etap 1.1', { exact: true }).fill('Skarpetki');
    await page.getByRole('button', { name: 'Dodaj etap', exact: true }).click();
    await page.getByLabel('Etap 1.2', { exact: true }).fill('Koszulki');
    await page.getByRole('button', { name: 'Przesuń w górę: Koszulki', exact: true }).click();
    await expect(page.getByLabel('Etap 1.1', { exact: true })).toHaveValue('Koszulki');
    await page.getByRole('button', { name: 'Dodaj krok', exact: true }).click();
    await page.getByRole('textbox', { name: 'Krok 2', exact: true }).fill('Dokumenty');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pakowanie', exact: true })).toBeVisible();
    await page.reload();
    await page.getByRole('button', { name: 'Plany działania', exact: true }).click();
    await page.getByRole('button', { name: 'Edytuj plan', exact: true }).click();
    await expect(page.getByLabel('Etap 1.1', { exact: true })).toHaveValue('Koszulki');
    await expect(page.getByLabel('Przypomnienie co ile minut')).toHaveValue('10');
    await page.getByLabel('Nazwa planu', { exact: true }).fill('Wyjazd');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Usuń', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Zacznij od jednej czynności' })).toBeVisible();
});

test('guides through stages, pauses both clocks, restores progress and completes', async ({ page }) => {
    await setup(page);
    await page.clock.install({ time: new Date('2026-09-18T12:00:00Z') });
    await page.clock.pauseAt(new Date('2026-09-18T12:00:00Z'));
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await expect(page.getByRole('navigation')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Pościelić łóżko' })).toBeFocused();
    await expect(page.getByTestId('step-clock')).toHaveText('01:00');
    await page.clock.runFor(12000);
    await expect(page.getByTestId('total-clock')).toHaveText('02:48');
    await page.getByRole('button', { name: 'Pokaż czas, który upłynął' }).click();
    await expect(page.getByTestId('step-clock')).toHaveText('00:12');
    await page.getByRole('button', { name: 'Pauza', exact: true }).click();
    await page.clock.runFor(600000);
    await expect(page.getByTestId('total-clock')).toHaveText('00:12');
    expect(await page.evaluate(() => window.reminderSounds)).toBe(0);
    await page.getByRole('button', { name: 'Wznów', exact: true }).click();
    await page.getByRole('button', { name: 'Dalej', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Zdjąć lego' })).toBeVisible();
    await expect(page.getByText('Posprzątać szafę', { exact: true })).toBeVisible();
    await expect(page.getByTestId('step-clock')).toHaveText('00:00');
    await page.reload();
    await page.getByRole('button', { name: 'Wróć do wykonywania', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Zdjąć lego' })).toBeVisible();
    await page.getByRole('button', { name: 'Wznów', exact: true }).click();
    await page.getByRole('button', { name: 'Dalej', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Odkurzyć półki' })).toBeVisible();
    await page.getByRole('button', { name: 'Gotowe', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Plan wykonany' })).toBeVisible();
    await page.getByRole('button', { name: 'Wróć do planów', exact: true }).click();
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pościelić łóżko' })).toBeVisible();
});

test('reminds at the estimated activity time, repeats, resets on next and respects mute', async ({ page }) => {
    await setup(page);
    await page.clock.install();
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await page.clock.runFor(59000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(0);
    await page.clock.runFor(1000);
    await expect(page.getByText('Wróć do bieżącej czynności.', { exact: false })).toBeVisible();
    expect(await page.evaluate(() => window.reminderSounds)).toBe(1);
    await expect(page.getByTestId('step-clock')).toHaveText('00:00');
    await page.clock.runFor(14000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(1);
    await page.clock.runFor(1000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(2);
    await page.clock.runFor(15000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(3);
    await page.getByRole('button', { name: 'Jeszcze pracuję nad tym', exact: true }).click();
    await page.clock.runFor(59000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(3);
    await page.clock.runFor(1000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(4);
    await page.getByRole('button', { name: 'Pauza', exact: true }).click();
    await page.clock.runFor(30000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(4);
    await page.getByRole('button', { name: 'Wznów', exact: true }).click();
    await page.getByRole('button', { name: 'Dalej', exact: true }).click();
    await expect(page.getByText('Wróć do bieżącej czynności.', { exact: false })).toHaveCount(0);
    await page.getByLabel('Dźwięk przypomnienia', { exact: true }).uncheck();
    await page.clock.runFor(90000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(4);
    await expect(page.getByText('Wróć do bieżącej czynności.', { exact: false })).toBeVisible();
});

test('uses ten minutes without an estimate and allows changing that interval', async ({ page }) => {
    await setup(page, [{ ...sample(), estimatedMinutes: null }]);
    await page.clock.install();
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await page.clock.runFor(599000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(0);
    await page.clock.runFor(1000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(1);
    await expect(page.getByTestId('total-clock')).toHaveText('10:00');
    await expect(page.getByRole('button', { name: 'Pokaż pozostały czas' })).toHaveCount(0);
    await page.getByRole('button', { name: 'Zatrzymaj i wróć do planów', exact: true }).click();
    await page.getByRole('button', { name: 'Edytuj plan', exact: true }).click();
    await page.getByLabel('Przypomnienie co ile minut').fill('2');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await page.clock.runFor(120000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(2);
});

test('previews and saves a sound, repeats it and restores a per-run sound choice', async ({ page }) => {
    await setup(page);
    await page.clock.install();
    await page.getByRole('button', { name: 'Edytuj plan', exact: true }).click();
    await page.getByRole('combobox', { name: 'Rodzaj dźwięku', exact: true }).click();
    await page.getByRole('option', { name: 'Krótka melodia', exact: true }).click();
    await page.getByRole('button', { name: 'Odsłuchaj', exact: true }).click();
    expect(await page.evaluate(() => window.reminderNotes.map((note) => note.frequency))).toEqual([523.25, 659.25, 783.99]);
    const savedRequest = page.waitForRequest((request) => request.method() === 'PUT' && request.url().includes('/api/action-plans/'));
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    expect((await savedRequest).postDataJSON().reminderSound).toBe('melody');
    await page.reload();
    await page.getByRole('button', { name: 'Plany działania', exact: true }).click();
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Rodzaj dźwięku' })).toContainText('Krótka melodia');
    await page.clock.runFor(60000);
    expect(await page.evaluate(() => window.reminderNotes.map((note) => note.frequency))).toEqual([523.25, 659.25, 783.99]);
    await page.clock.runFor(15000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(6);
    await page.getByRole('combobox', { name: 'Rodzaj dźwięku', exact: true }).click();
    await page.getByRole('option', { name: 'Podwójny sygnał', exact: true }).click();
    await page.clock.runFor(15000);
    expect(await page.evaluate(() => window.reminderNotes.slice(-2).map((note) => note.frequency))).toEqual([660, 660]);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(8);
    await page.reload();
    await page.getByRole('button', { name: 'Wróć do wykonywania', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Rodzaj dźwięku' })).toContainText('Podwójny sygnał');
    await page.clock.runFor(90000);
    expect(await page.evaluate(() => window.reminderSounds)).toBe(0);
});

test('keeps draft on save failure and remains usable on narrow screens', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await setup(page, []);
    await page.getByRole('button', { name: 'Nowy plan', exact: true }).click();
    await page.getByLabel('Nazwa planu', { exact: true }).fill('Plan dnia');
    await page.getByRole('textbox', { name: 'Krok 1', exact: true }).fill('Śniadanie');
    await page.route('**/api/action-plans', (route) => route.fulfill({ status: 500, json: {} }));
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Nie udało się zapisać');
    await expect(page.getByRole('textbox', { name: 'Krok 1', exact: true })).toHaveValue('Śniadanie');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});


test('starts native browser audio from the start button and shows a focused narrow layout', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await setup(page);
    await page.evaluate(() => {
        window.AudioContext = class extends window.NativeAudioContext {
            constructor() {
                super();
                window.planAudioContext = this;
            }
        };
    });
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await expect.poll(() => page.evaluate(() => window.planAudioContext?.state)).toBe('running');
    await expect(page.getByRole('navigation')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('action-plan-runner.png'), fullPage: true });
});

test('shows reminders without audio support and caps short reminders at thirty seconds', async ({ page }) => {
    await setup(page, [{ ...sample(), estimatedMinutes: 1 }]);
    await page.evaluate(() => { window.AudioContext = undefined; window.webkitAudioContext = undefined; });
    await page.clock.install();
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    await expect(page.getByText('Dźwięk jest niedostępny.', { exact: false })).toBeVisible();
    await page.clock.runFor(29000);
    await expect(page.getByText('Wróć do bieżącej czynności.', { exact: false })).toHaveCount(0);
    await page.clock.runFor(1000);
    await expect(page.getByText('Wróć do bieżącej czynności.', { exact: false })).toBeVisible();
    await page.getByRole('button', { name: 'Dalej', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Zdjąć lego' })).toBeVisible();
});

test('members can choose team visibility and filter shared plans without editing someone else’s plan', async ({ page }) => {
    await setup(page, [{ ...sample(), teamId: 'team-1', canManage: false, canChangeScope: false }]);
    await expect(page.getByRole('button', { name: 'Edytuj plan', exact: true })).toHaveCount(0);
    const scope = page.getByRole('combobox', { name: 'Pokaż plany', exact: true });
    await scope.fill('moj');
    await page.getByRole('option', { name: 'Tylko mój', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Sprzątanie pokoju', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Nowy plan', exact: true }).click();
    await expect(page.getByLabel('Dla kogo jest ten plan?')).toHaveValue('');
    await page.getByLabel('Dla kogo jest ten plan?').selectOption('team-1');
    await page.getByLabel('Nazwa planu', { exact: true }).fill('Plan rodzinny');
    await page.getByRole('textbox', { name: 'Krok 1', exact: true }).fill('Przygotuj obiad');
    const savedRequest = page.waitForRequest((request) => request.url().endsWith('/api/action-plans') && request.method() === 'POST');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    expect((await savedRequest).postDataJSON().teamId).toBe('team-1');
    await scope.fill('Family');
    await scope.press('Enter');
    await expect(page.getByRole('heading', { name: 'Plan rodzinny', exact: true })).toBeVisible();
});

test('finishing a task plan saves the linked task and retains pending completion after a failed request and reload', async ({ page }) => {
    const { mockApiResponses } = require('./fixtures');
    await setup(page, []);
    const execution = {
        id: 'execution-with-plan', taskTemplateId: mockApiResponses.sampleTaskTypes.templates[0].id,
        name: 'Sprzątanie z planem', status: 'pending', points: 10, assignedUserId: 1,
        actionPlan: { ...sample(), steps: [{ name: 'Pościelić łóżko', stages: [] }] },
    };
    let attempts = 0;
    await page.route('**/api/task-executions/mine', (route) => route.fulfill({ json: { executions: [execution] } }));
    await page.route('**/api/task-executions/execution-with-plan/complete', (route) => {
        attempts++;
        if (attempts === 1) return route.fulfill({ status: 500, json: {} });
        execution.status = 'completed';
        return route.fulfill({ json: execution });
    });
    await page.getByRole('button', { name: 'Zadania', exact: true }).click();
    await page.getByRole('button', { name: 'Pokaż plan działania', exact: true }).click();
    await expect(page.getByText('Do zadania: Sprzątanie z planem')).toBeVisible();
    await page.getByRole('button', { name: 'Do dzieła', exact: true }).click();
    expect(attempts).toBe(0);
    await page.getByRole('button', { name: 'Gotowe', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Nie udało się zapisać ukończenia zadania');
    await expect(page.getByRole('button', { name: 'Wróć do zadań', exact: true })).toBeDisabled();
    await page.reload();
    await page.getByRole('button', { name: 'Wróć do wykonywania', exact: true }).click();
    await expect(page.getByText('Zadanie jest ukończone.', { exact: false })).toBeVisible();
    expect(attempts).toBe(2);
    await page.getByRole('button', { name: 'Wróć do zadań', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Zadania', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Pokaż plan działania', exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => localStorage.getItem('action-plan-run:1'))).toBeNull();
});

test('administrator can attach a team plan in the task type editor', async ({ page }) => {
    const { mockApiResponses } = require('./fixtures');
    await setup(page, [{ ...sample(), teamId: 'team-1' }, { ...sample(), id: 'private-plan', name: 'Prywatny plan', teamId: null }]);
    await setupAuthenticatedSession(page, 'admin');
    await page.reload();
    await page.getByRole('button', { name: 'Typy zadań', exact: true }).click();
    const type = mockApiResponses.sampleTaskTypes.templates[0];
    await page.route(`**/api/task-templates/${type.id}`, (route) => route.fulfill({ json: { ...type, ...route.request().postDataJSON() } }));
    await page.getByRole('button', { name: 'Edytuj', exact: true }).first().click();
    await page.getByRole('combobox', { name: 'Plan działania do zadania', exact: true }).click();
    await expect(page.getByRole('option', { name: 'Prywatny plan', exact: true })).toHaveCount(0);
    await page.getByRole('option', { name: 'Sprzątanie pokoju', exact: true }).click();
    const update = page.waitForRequest((request) => request.url().endsWith(`/api/task-templates/${type.id}`) && request.method() === 'PUT');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    expect((await update).postDataJSON().actionPlanId).toBe(sample().id);
});
