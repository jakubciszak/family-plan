const { test, expect } = require('./app');
const { execution, template, TEAM } = require('./fake-api');

const plan = (over = {}) => ({
  id: 'plan-1', name: 'Poranek', teamId: null, canManage: true, canChangeScope: true,
  estimatedMinutes: 2, reminderMinutes: 1, reminderSound: 'soft',
  steps: [{ name: 'Przygotowanie', stages: ['Umyj zęby', 'Uczesz włosy'] }, { name: 'Spakuj plecak', stages: [] }], ...over,
});
const button = (page, name) => page.getByRole('button', { name, exact: true });
const openPlans = async (app) => { await app.signIn(); await app.goTo('Plany działania'); };

test('creates, reorders and edits steps and stages with private and team scopes', async ({ app, page, world }) => {
  await openPlans(app);
  await button(page, 'Nowy plan').click();
  await app.field('Nazwa planu').fill('Wyjście');
  await button(page, 'Dla kogo jest ten plan?').click();
  await app.field('Wpisz, aby wyszukać…').fill('Kowal');
  await button(page, 'Kowalscy').click();
  await app.field('Krok 1').fill('Przygotowanie');
  await button(page, 'Dodaj etap').click();
  await app.field('Etap 1.1').fill('Buty');
  await button(page, 'Dodaj etap').click();
  await app.field('Etap 1.2').fill('Kurtka');
  await button(page, 'Przesuń w górę: Kurtka').click();
  await button(page, 'Dodaj krok').click();
  await app.field('Krok 2').fill('Klucze');
  await button(page, 'Przesuń w górę: Klucze').click();
  await button(page, 'Rodzaj dźwięku').click();
  await button(page, 'Krótka melodia').click();
  await button(page, 'Zapisz').click();
  await expect(page.getByTestId('plan-Wyjście')).toBeVisible();
  expect(world.actionPlans[0]).toMatchObject({ teamId: TEAM.id, reminderSound: 'melody', estimatedMinutes: null,
    steps: [{ name: 'Klucze', stages: [] }, { name: 'Przygotowanie', stages: ['Kurtka', 'Buty'] }] });
  await button(page, 'Edytuj plan').click();
  await button(page, 'Usuń etap 2.1').click();
  await app.field('Czas całego planu w minutach (opcjonalnie)').fill('15');
  await button(page, 'Zapisz').click();
  await expect(page.getByTestId('plan-Wyjście')).toContainText('15 min');
  expect(world.actionPlans[0].steps[1].stages).toEqual(['Buty']);
});

test('validates values, preserves drafts after failure and confirms deletion', async ({ app, page, world }) => {
  await openPlans(app);
  await button(page, 'Nowy plan').click();
  await button(page, 'Zapisz').click();
  await expect(page.getByRole('alert')).toContainText('Każda nazwa');
  await app.field('Nazwa planu').fill('Próba');
  await app.field('Krok 1').fill('Krok');
  await app.field('Czas całego planu w minutach (opcjonalnie)').fill('1.5');
  await button(page, 'Zapisz').click();
  await expect(page.getByRole('alert')).toContainText('od 1 do 1440');
  await app.field('Czas całego planu w minutach (opcjonalnie)').fill('');
  world.failing.add('POST /api/action-plans');
  await button(page, 'Zapisz').click();
  await expect(page.getByRole('alert')).toContainText('Nie udało się zapisać');
  await expect(app.field('Nazwa planu')).toHaveValue('Próba');
  world.failing.clear();
  await button(page, 'Zapisz').click();
  await expect(page.getByTestId('plan-Próba')).toBeVisible();
  await button(page, 'Usuń').click();
  await button(page, 'Anuluj').click();
  expect(world.actionPlans).toHaveLength(1);
  await button(page, 'Usuń').click();
  await button(page, 'Potwierdź').click();
  await expect(page.getByTestId('plan-Próba')).toHaveCount(0);
  expect(world.actionPlans).toHaveLength(0);
});

test('flattens stages, focuses the activity, pauses and restores progress after reload', async ({ app, page, world }) => {
  world.actionPlans = [plan()];
  await openPlans(app);
  await button(page, 'Zobacz kroki').click();
  await expect(page.getByTestId('plan-Poranek')).toContainText('1.2. Uczesz włosy');
  await button(page, 'Do dzieła').click();
  await expect(page.getByRole('heading', { name: 'Umyj zęby' })).toBeVisible();
  await expect(button(page, 'Więcej')).toHaveCount(0);
  await button(page, 'Dalej').click();
  await expect(page.getByRole('heading', { name: 'Uczesz włosy' })).toBeVisible();
  await button(page, 'Zatrzymaj i wróć do planów').click();
  await expect(page.getByTestId('saved-plan')).toContainText('czynność 2 z 3');
  await page.reload();
  await button(page, 'Wróć do wykonywania').click();
  await expect(page.getByText('Plan jest wstrzymany. Wróć, kiedy chcesz.')).toBeVisible();
  await button(page, 'Wznów').click();
  await button(page, 'Dalej').click();
  await expect(page.getByRole('heading', { name: 'Spakuj plecak' })).toBeVisible();
  await button(page, 'Gotowe').click();
  await expect(page.getByTestId('plan-completed')).toContainText('Plan wykonany');
  await button(page, 'Wróć do planów').click();
  await expect(page.getByTestId('saved-plan')).toHaveCount(0);
});

test('counts time without auto-advancing, repeats reminders and resets after acknowledgement', async ({ app, page, world }) => {
  world.actionPlans = [plan({ estimatedMinutes: 1, steps: [{ name: 'Czytaj', stages: [] }] })];
  await openPlans(app);
  await page.clock.install();
  await button(page, 'Do dzieła').click();
  await page.clock.runFor(61000);
  await expect(page.getByTestId('step-clock')).toHaveText('00:00');
  await expect(page.getByText('To tylko oszacowanie. Możesz spokojnie dokończyć tę czynność.')).toBeVisible();
  await expect(button(page, 'Jeszcze pracuję nad tym')).toBeVisible();
  await button(page, 'Jeszcze pracuję nad tym').click();
  await page.clock.runFor(59000);
  await expect(button(page, 'Jeszcze pracuję nad tym')).toHaveCount(0);
  await page.clock.runFor(2000);
  await expect(button(page, 'Jeszcze pracuję nad tym')).toBeVisible();
  await button(page, 'Pauza').click();
  await button(page, 'Pokaż czas, który upłynął').click();
  const before = await page.getByTestId('total-clock').textContent();
  await page.clock.runFor(120000);
  await expect(page.getByTestId('total-clock')).toHaveText(before);
  await expect(button(page, 'Jeszcze pracuję nad tym')).toHaveCount(0);
});

test('filters searchable scopes and respects edit and scope permissions', async ({ app, page, world }) => {
  world.actionPlans = [plan(), plan({ id: 'team-plan', name: 'Rodzinny', teamId: TEAM.id, canManage: false }), plan({ id: 'admin-plan', name: 'Autora', teamId: TEAM.id, canChangeScope: false })];
  await openPlans(app);
  await expect(page.getByTestId('plan-Rodzinny').getByRole('button', { name: 'Edytuj plan' })).toHaveCount(0);
  await button(page, 'Pokaż plany').click();
  await app.field('Wpisz, aby wyszukać…').fill('Kowal');
  await button(page, 'Kowalscy').click();
  await expect(page.getByTestId('plan-Poranek')).toHaveCount(0);
  await page.getByTestId('plan-Autora').getByRole('button', { name: 'Edytuj plan' }).click();
  await expect(button(page, 'Dla kogo jest ten plan?')).toBeDisabled();
  await expect(page.getByText('Tylko autor może zmienić, dla kogo jest plan.')).toBeVisible();
});

test('task completion failure survives restart and can be retried without losing the snapshot', async ({ app, page, world }) => {
  world.teams[0].role = 'member';
  const snapshot = plan({ steps: [{ name: 'Ostatnia czynność', stages: [] }] });
  world.executions = [execution({ id: 'linked', name: 'Zadanie z planem', actionPlan: snapshot })];
  world.failing.add('POST /api/task-executions/linked/complete');
  await app.signIn();
  await button(page, 'Pokaż plan działania').click();
  await expect(page.getByTestId('task-plan-preview')).toContainText('Zadanie z planem');
  await button(page, 'Do dzieła').click();
  await button(page, 'Gotowe').click();
  await expect(page.getByRole('alert')).toContainText('Postęp planu został zachowany');
  await page.reload();
  await button(page, 'Wróć do wykonywania').click();
  await expect(page.getByRole('alert')).toContainText('Postęp planu został zachowany');
  world.failing.clear();
  await button(page, 'Spróbuj ponownie').click();
  await expect(page.getByTestId('plan-completed')).toContainText('Zadanie jest ukończone');
  await button(page, 'Wróć do zadań').click();
  await expect(app.onScreen('task-Zadanie z planem')).toContainText('Czeka na zatwierdzenie');
  expect(world.executions[0].status).toBe('completed');
});

test('confirms replacement and discard of unfinished plans', async ({ app, page, world }) => {
  world.actionPlans = [plan()];
  await openPlans(app);
  await button(page, 'Do dzieła').click();
  await button(page, 'Dalej').click();
  await button(page, 'Zatrzymaj i wróć do planów').click();
  await button(page, 'Do dzieła').click();
  await button(page, 'Anuluj').click();
  await expect(page.getByTestId('saved-plan')).toContainText('czynność 2 z 3');
  await button(page, 'Do dzieła').click();
  await button(page, 'Potwierdź').click();
  await expect(page.getByRole('heading', { name: 'Umyj zęby' })).toBeVisible();
  await button(page, 'Zatrzymaj i wróć do planów').click();
  await button(page, 'Odrzuć postęp').click();
  await button(page, 'Potwierdź').click();
  await expect(page.getByTestId('saved-plan')).toHaveCount(0);
});

test('list and team failures have separate recovery paths', async ({ app, page, world }) => {
  world.actionPlans = [plan()];
  await app.signIn();
  world.failing.add('GET /api/teams');
  await app.goTo('Plany działania');
  await expect(page.getByText('Nie udało się wczytać zespołów. Nadal możesz korzystać z prywatnych planów.')).toBeVisible();
  await expect(page.getByTestId('plan-Poranek')).toBeVisible();
  world.failing.clear();
  await button(page, 'Spróbuj ponownie').click();
  await expect(page.getByText('Nie udało się wczytać zespołów. Nadal możesz korzystać z prywatnych planów.')).toHaveCount(0);
});

test('task types offer only plans of the same team and preserve an unavailable attachment', async ({ app, page, world }) => {
  world.actionPlans = [plan(), plan({ id: 'team-plan', name: 'Wspólne sprzątanie', teamId: TEAM.id }), plan({ id: 'other-team', name: 'Inna rodzina', teamId: 'elsewhere' })];
  await app.signIn();
  await app.goTo('Typy zadań');
  await page.getByRole('button', { name: 'Dodaj typ zadania' }).click();
  await app.field('Nazwa zadania').fill('Sprzątanie');
  await button(page, 'Plan działania do zadania').click();
  await expect(button(page, 'Poranek')).toHaveCount(0);
  await expect(button(page, 'Inna rodzina')).toHaveCount(0);
  await button(page, 'Wspólne sprzątanie').click();
  await button(page, 'Zapisz').click();
  await expect(app.onScreen('task-type-Sprzątanie')).toBeVisible();
  expect(world.templates[0].actionPlanId).toBe('team-plan');
  world.failing.add('GET /api/action-plans');
  await app.goTo('Zadania');
  await app.goTo('Typy zadań');
  await app.onScreen('task-type-Sprzątanie').getByRole('button', { name: 'Więcej' }).click();
  await button(page, 'Edytuj').click();
  await expect(button(page, 'Plan działania do zadania')).toBeDisabled();
  await app.field('Nazwa zadania').fill('Sprzątanie pokoju');
  await button(page, 'Zapisz').click();
  await expect(app.onScreen('task-type-Sprzątanie pokoju')).toBeVisible();
  expect(world.templates[0].actionPlanId).toBe('team-plan');
});

test('does not delete a linked plan and preserves its editor after a rejected scope change', async ({ app, page, world }) => {
  world.actionPlans = [plan({ teamId: TEAM.id })];
  world.templates = [template({ actionPlanId: 'plan-1' })];
  await openPlans(app);
  await button(page, 'Usuń').click();
  await button(page, 'Potwierdź').click();
  await expect(page.getByRole('alert')).toContainText('Najpierw odłącz');
  await button(page, 'Edytuj plan').click();
  await button(page, 'Dla kogo jest ten plan?').click();
  await button(page, 'Tylko mój').click();
  await button(page, 'Zapisz').click();
  await expect(page.getByRole('alert')).toContainText('Najpierw odłącz');
  await expect(app.field('Nazwa planu')).toHaveValue('Poranek');
});

test('isolates progress between accounts and tolerates damaged saved data', async ({ app, page, world }) => {
  world.actionPlans = [plan()];
  await openPlans(app);
  await button(page, 'Do dzieła').click();
  await button(page, 'Dalej').click();
  await button(page, 'Zatrzymaj i wróć do planów').click();
  const firstId = world.me.id;
  await expect.poll(() => page.evaluate((id) => JSON.parse(localStorage.getItem(`action-plan-run:${id}`))?.index, firstId)).toBe(1);
  world.me = { ...world.users[1] };
  await page.reload();
  await expect(button(page, 'Nowy plan')).toBeVisible();
  await expect(page.getByTestId('saved-plan')).toHaveCount(0);
  world.me = { ...world.users[0] };
  await page.reload();
  await expect(page.getByTestId('saved-plan')).toContainText('czynność 2 z 3');
  await page.evaluate((id) => localStorage.setItem(`action-plan-run:${id}`, '{damaged'), firstId);
  await page.reload();
  await expect(button(page, 'Nowy plan')).toBeVisible();
  await expect(page.getByTestId('saved-plan')).toHaveCount(0);
});

test('plays all four previews, repeats the reminder after 15 seconds and obeys mute', async ({ app, page, world }) => {
  world.actionPlans = [plan({ estimatedMinutes: null, reminderMinutes: 1, steps: [{ name: 'Czytanie', stages: [] }] })];
  await page.addInitScript(() => {
    window.playedPlanSounds = [];
    const play = HTMLMediaElement.prototype.play;
    HTMLMediaElement.prototype.play = function () {
      window.playedPlanSounds.push(this.src);
      return play.call(this);
    };
  });
  await openPlans(app);
  await button(page, 'Edytuj plan').click();
  for (const name of ['Delikatny ton', 'Dzwonek', 'Podwójny sygnał', 'Krótka melodia']) {
    await button(page, 'Rodzaj dźwięku').click();
    await button(page, name).click();
    await button(page, 'Odsłuchaj').click();
  }
  await expect.poll(() => page.evaluate(() => new Set(window.playedPlanSounds).size)).toBe(4);
  await button(page, 'Anuluj').click();
  await page.clock.install();
  await button(page, 'Do dzieła').click();
  await page.clock.runFor(61000);
  await expect.poll(() => page.evaluate(() => window.playedPlanSounds.length)).toBe(5);
  await page.clock.runFor(15000);
  await expect.poll(() => page.evaluate(() => window.playedPlanSounds.length)).toBe(6);
  await page.getByRole('switch', { name: 'Dźwięk przypomnienia', exact: true }).click();
  await page.clock.runFor(30000);
  expect(await page.evaluate(() => window.playedPlanSounds.length)).toBe(6);
  await expect(button(page, 'Jeszcze pracuję nad tym')).toBeVisible();
});


test('a task preview replaces an editor left open on another tab', async ({ app, page, world }) => {
  world.teams[0].role = 'member';
  world.executions = [execution({ name: 'Zadanie', actionPlan: plan() })];
  await openPlans(app);
  await button(page, 'Nowy plan').click();
  await app.field('Nazwa planu').fill('Niezapisany');
  await app.goTo('Zadania');
  await button(page, 'Pokaż plan działania').click();
  await expect(page.getByTestId('task-plan-preview')).toBeVisible();
  await expect(page.getByTestId('plan-editor')).toHaveCount(0);
});

test('list, editor and focus mode fit a 320 px dark screen without rendering errors', async ({ app, page, world }, testInfo) => {
  world.actionPlans = [plan()];
  world.personalisation.themeMode = 'dark';
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  await page.setViewportSize({ width: 320, height: 740 });
  await openPlans(app);
  await button(page, 'Edytuj plan').click();
  await expect(app.field('Nazwa planu')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  await button(page, 'Anuluj').click();
  await button(page, 'Do dzieła').click();
  await expect(page.getByTestId('plan-runner')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  expect(errors).toEqual([]);
  await page.screenshot({ path: testInfo.outputPath('plan-runner-dark.png') });
});
