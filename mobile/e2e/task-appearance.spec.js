const { test, expect } = require('./app');
const { execution, template } = require('./fake-api');

test('rodzic widzi kalendarz dziecka, kolejkę akceptacji i zwinięte sekcje', async ({ app, page }) => {
  const child = app.world.users[1];
  app.world.templates = [template({ id: 't1', name: 'Zmywanie' })];
  app.world.executions = [execution({ id: 'e1', taskTemplateId: 't1', name: 'Zmywanie', status: 'completed', assignedUserId: child.id, assignedUserName: child.name })];
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  await app.signIn();
  await expect(page.getByTestId('member-weeks')).toBeVisible();
  await expect(page.getByTestId('approval-Zmywanie')).toContainText(child.name);
  await expect(page.getByTestId('available-tasks')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Tabela wyników', exact: true })).toHaveAttribute('aria-expanded', 'false');
  await page.getByTestId('approval-Zmywanie').getByRole('button', { name: 'Zatwierdź', exact: true }).click();
  await expect(page.getByText('Nic nie czeka na zatwierdzenie')).toBeVisible();
  await page.getByRole('button', { name: 'Zadania do wzięcia', exact: true }).click();
  await expect(page.getByTestId('available-tasks')).toContainText('Zmywanie');
  expect(errors).toEqual([]);
});

test('siedem dni mieści się w jednym rzędzie na telefonie 320 px', async ({ app, page }) => {
  app.world.teams[0].role = 'member';
  app.world.personalisation.home = ['week'];
  await page.setViewportSize({ width: 320, height: 740 });
  await app.signIn();
  const cells = page.getByTestId('week-days').getByRole('button');
  await expect(cells).toHaveCount(7);
  const boxes = await cells.evaluateAll(nodes => nodes.map(node => { const b = node.getBoundingClientRect(); return { top: b.top, right: b.right, width: b.width }; }));
  expect(new Set(boxes.map(box => box.top)).size).toBe(1);
  expect(boxes.every(box => box.right <= 320 && box.width > 25)).toBeTruthy();
});

for (const met of [false, true]) {
  test(`seria ${met ? 'ukończona' : 'w trakcie'} pokazuje ogień i pasek pod kalendarzem`, async ({ app, page }) => {
    app.world.teams[0].role = 'member';
    app.world.personalisation.home = ['week'];
    const length = met ? 3 : 2;
    app.world.week.streak = { length, requiredDays: 3, pointsPerDay: 5, bonusPoints: 20, met };
    app.world.week.days.forEach((day, index) => { day.inStreak = index < length; day.points = index < length ? 5 : 0; });
    await app.signIn();
    await expect(page.getByTestId(/^week-day-flame-/)).toHaveCount(length);
    const streak = page.getByTestId('week-streak');
    await expect(streak).toHaveText(met ? /Seria 3 dni z rzędu — bonus 20 pkt/ : /Seria: 2 z 3 dni \(min. 5 pkt dziennie\)/);
    const calendar = await page.getByTestId('week-days').boundingBox();
    const banner = await streak.boundingBox();
    expect(banner.y).toBeGreaterThan(calendar.y + calendar.height);
  });
}

test('wyszukiwarka pomija wyczerpane limity i zadania innych zespołów', async ({ app, page }) => {
  app.world.teams[0].role = 'member';
  app.world.personalisation.home = ['tasks'];
  app.world.templates = [template({ name: 'Zmywanie', description: 'Umyj talerze.', remaining: 2, executionLimit: { type: 'per_day', count: 3 } }), template({ name: 'Kwiaty', remaining: 0 }), template({ name: 'Obca rodzina', teamId: 'another-team' })];
  await app.signIn();
  await expect(page.getByTestId('task-Zmywanie')).toContainText('Umyj talerze.');
  await expect(page.getByTestId('task-Kwiaty')).toHaveCount(0);
  await expect(page.getByTestId('task-Obca rodzina')).toHaveCount(0);
  await app.field('Szukaj zadania').fill('spacer');
  await expect(page.getByText('Nic nie pasuje do wpisanej nazwy')).toBeVisible();
  await app.field('Szukaj zadania').fill('ZMYW');
  await expect(page.getByTestId('task-Zmywanie')).toBeVisible();
});

test('wyłączenie domownika chowa jego kalendarz', async ({ app, page }) => {
  app.world.personalisation.home = ['week'];
  await app.signIn();
  const weeks = page.getByTestId('member-weeks');
  await expect(weeks.getByTestId('points-calendar')).toHaveCount(1);
  await weeks.getByRole('button', { name: app.world.users[1].name, exact: true }).click();
  await expect(weeks.getByTestId('points-calendar')).toHaveCount(0);
});
