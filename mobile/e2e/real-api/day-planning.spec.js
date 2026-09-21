const { test, expect } = require('@playwright/test');
const { randomUUID } = require('node:crypto');
const api = process.env.MOBILE_API_URL || 'http://127.0.0.1:19080';
const button = (page, name) => page.getByRole('button', { name, exact: true });

test.use({ timezoneId: 'Europe/Warsaw' });

test('mobile calendar, private team availability, weekly exception and planner use the real API', async ({ page, request }, testInfo) => {
  const stamp = Date.now();
  const password = 'MobilePlanningTest123!';
  const users = [];
  const call = async (method, path, data, headers) => {
    const response = await request[method](`${api}${path}`, { data, headers });
    expect(response.ok(), `${method} ${path}: ${await response.text()}`).toBeTruthy();
    return response.status() === 204 ? null : response.json();
  };
  for (const name of ['Mobile Author', 'Mobile Friend']) {
    const email = `${name.replaceAll(' ', '-').toLowerCase()}-${stamp}@example.test`;
    await call('post', '/api/auth/register', { name, email, password });
    const issued = await call('post', '/api/auth/token', { email, password });
    users.push({ email, id: issued.user.id, headers: { Authorization: `Bearer ${issued.token}` } });
  }
  const [author, friend] = users;
  const team = await call('post', '/api/teams', { name: `Mobile calendar ${stamp}` }, author.headers);
  const invitation = await call('post', `/api/teams/${team.id}/invite`, { email: friend.email, role: 'member' }, author.headers);
  await call('post', `/api/teams/invitations/${invitation.invitation.token}/accept`, {}, friend.headers);
  await call('post', '/api/day-planning/events', { title: 'Ukryty termin znajomego', description: 'Nie można ujawnić administratorowi', location: '', teamId: team.id, visibility: 'PRIVATE', schedule: { kind: 'TIMED', localStart: '2026-09-21T11:00', durationMinutes: 60, timeZone: 'Europe/Warsaw' }, recurrence: null, participantIds: [friend.id], tagIds: [], blocksTime: true }, { ...friend.headers, 'Idempotency-Key': randomUUID() });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/');
  await page.getByLabel('Email', { exact: true }).fill(author.email);
  await page.getByLabel('Hasło', { exact: true }).fill(password);
  await button(page, 'Zaloguj').click();
  await button(page, 'Zadania').waitFor();
  await page.goto('/day-planning');
  await page.getByLabel('Data (RRRR-MM-DD)', { exact: true }).fill('2026-09-21');
  await button(page, 'Zespół').click(); await button(page, team.name).click();
  await button(page, 'Nowe wydarzenie').click();
  await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Wspólna nauka mobile');
  await button(page, 'Powtarzanie').click(); await button(page, 'Co kilka tygodni').click();
  await page.getByRole('checkbox', { name: 'Zaproś: Mobile Friend' }).click();
  await button(page, 'Zapisz').click();
  await expect(page.getByText('Wspólna nauka mobile', { exact: true })).toBeVisible();
  const calendar = await call('get', '/api/day-planning/calendar?from=2026-09-21T00%3A00%3A00%2B02%3A00&to=2026-09-22T00%3A00%3A00%2B02%3A00', undefined, author.headers);
  const saved = calendar.events.find((entry) => entry.title === 'Wspólna nauka mobile');
  expect(saved.recurring).toBe(true);
  const invited = await call('get', `/api/day-planning/events/${saved.id}/occurrences/${encodeURIComponent(saved.occurrenceKey)}`, undefined, friend.headers);
  expect(invited.title).toBe('Wspólna nauka mobile');
  expect(invited.canEdit).toBe(false);
  await page.getByTestId(`day-event-${saved.id}`).click(); await button(page, 'Edytuj ten dzień').click();
  await page.getByLabel('Tytuł wydarzenia', { exact: true }).fill('Nauka w bibliotece mobile');
  await page.getByLabel('Godzina początku (GG:MM)', { exact: true }).fill('13:00');
  await page.getByLabel('Godzina końca (GG:MM)', { exact: true }).fill('14:00');
  await button(page, 'Zapisz').click(); await expect(page.getByText('Nauka w bibliotece mobile', { exact: true })).toBeVisible();
  const definition = await call('get', `/api/day-planning/events/${saved.id}`, undefined, author.headers);
  expect(definition.exceptions[saved.occurrenceKey].changes.title).toBe('Nauka w bibliotece mobile');
  expect(definition.exceptions[saved.occurrenceKey].changes.schedule.localStart).toBe('2026-09-21T13:00');
  await button(page, 'Plan zespołu').click(); await page.getByRole('checkbox', { name: 'Pokaż osobę: Mobile Friend' }).click();
  await expect(page.getByTestId('day-busy')).toContainText('Zajęty'); await expect(page.getByText('Ukryty termin znajomego')).toHaveCount(0);
  await page.getByTestId('day-busy').scrollIntoViewIfNeeded(); await page.screenshot({ path: testInfo.outputPath('day-planning-real-mobile-agenda.png') });
  await button(page, 'Znajdź termin').click(); await page.getByLabel('Ostatni dzień poszukiwań (RRRR-MM-DD)', { exact: true }).fill('2026-09-21');
  await button(page, 'Znajdź wspólny termin').click(); await expect(page.getByTestId('day-suggestions')).toBeVisible();
  await button(page, 'Wybierz termin').first().scrollIntoViewIfNeeded(); await page.screenshot({ path: testInfo.outputPath('day-planning-real-mobile-planner.png') });
  await button(page, 'Wybierz termin').first().click(); await expect(page.getByRole('checkbox', { name: 'Zaproś: Mobile Friend' })).toBeChecked();
  await button(page, 'Anuluj').click();
  expect(errors).toEqual([]);
});
