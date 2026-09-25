const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

const NOW = new Date('2026-09-25T18:00:00Z');
const at = (minutesAgo) => new Date(NOW.getTime() - minutesAgo * 60000).toISOString();
const note = (id, minutesAgo, subject, message, extra = {}) => ({
  id, subject, message, event: 'task_completed', parameters: { url: '/tasks' }, createdAt: at(minutesAgo), readAt: null, active: true, ...extra,
});

/** A fake notifications API: the unread list can change between polls, and every write is recorded. */
async function notificationsApi(page, { unread = [], history = [], preferences = null } = {}) {
  const api = { unread, history, preferences, writes: [] };
  await page.route('**/api/notifications/preferences', (route) => {
    if (route.request().method() === 'GET') return route.fulfill({ json: { events: api.preferences } });
    api.writes.push({ method: route.request().method(), url: '/api/notifications/preferences', body: route.request().postDataJSON() });
    return route.fulfill({ json: { events: api.preferences } });
  });
  await page.route(/\/api\/notifications(\?.*)?$/, (route) => {
    const url = new URL(route.request().url());
    const list = url.searchParams.get('unread') ? api.unread : [...api.unread, ...api.history];
    return route.fulfill({ json: { notifications: list, unreadCount: api.unread.length } });
  });
  await page.route('**/api/notifications/*/read', (route) => {
    const id = route.request().url().split('/').slice(-2)[0];
    api.writes.push({ method: 'POST', url: `/api/notifications/${id}/read` });
    api.unread = api.unread.filter((item) => item.id !== id);
    return route.fulfill({ json: { unreadCount: api.unread.length } });
  });
  await page.route('**/api/notifications/read-all', (route) => {
    const body = route.request().postDataJSON() || {};
    api.writes.push({ method: 'POST', url: '/api/notifications/read-all', body });
    api.unread = body.ids ? api.unread.filter((item) => !body.ids.includes(item.id)) : [];
    return route.fulfill({ json: { status: 'success', unreadCount: api.unread.length } });
  });
  return api;
}

async function openApp(page, options) {
  await page.clock.install({ time: NOW });
  await page.route('**/api/**', (route) => route.fulfill({ status: 404, json: {} }));
  await setupAuthenticatedSession(page);
  const api = await notificationsApi(page, options);
  await page.goto('/');
  return api;
}

const bubbles = (page) => page.locator('.notification-bubble');

test.describe('Notification bubbles', () => {
  test('a backlog from the time away shows up as one summary, not a queue of old bubbles', async ({ page }) => {
    const api = await openApp(page, {
      unread: [
        note('a', 60, 'Task waiting for approval', 'Ola waits for approval of "Dishes".'),
        note('b', 600, 'Task approved', 'You earn 20 points.', { event: 'task_approved' }),
        note('c', 3000, 'Pocket money waiting', 'You have 20.00 zł to collect.', { event: 'payout_offered' }),
      ],
    });

    await expect(bubbles(page)).toHaveCount(1);
    await expect(bubbles(page)).toContainText('3 notifications while you were away');
    await expect(bubbles(page)).toContainText('Task waiting for approval');

    await page.clock.runFor(30000);
    await expect(bubbles(page)).toHaveCount(1);
    expect(api.writes).toEqual([]);

    await bubbles(page).getByRole('button', { name: 'Dismiss and mark as read' }).click();
    await expect(bubbles(page)).toHaveCount(0);
    await expect.poll(() => api.writes).toEqual([{ method: 'POST', url: '/api/notifications/read-all', body: { ids: ['a', 'b', 'c'] } }]);
  });

  test('a single notification waiting from before is shown as it is', async ({ page }) => {
    await openApp(page, { unread: [note('a', 600, 'Task approved', 'You earn 20 points.', { event: 'task_approved' })] });

    await expect(bubbles(page)).toHaveCount(1);
    await expect(bubbles(page)).toContainText('Task approved');
    await expect(bubbles(page)).toContainText('You earn 20 points.');
  });

  test('news that arrives while the app is open pops up and hides by itself after a while', async ({ page }) => {
    const api = await openApp(page);
    await page.clock.runFor(500);
    await expect(bubbles(page)).toHaveCount(0);

    api.unread = [note('live', 0, 'Task assigned', 'Hoovering was assigned to you.', { event: 'task_assigned', createdAt: new Date(NOW.getTime() + 5000).toISOString() })];
    await page.clock.runFor(10000);
    await expect(bubbles(page)).toHaveCount(1);
    await expect(bubbles(page)).toContainText('Hoovering was assigned to you.');

    await page.clock.runFor(8500);
    await expect(bubbles(page)).toHaveCount(0);
    await expect.poll(() => api.writes).toEqual([{ method: 'POST', url: '/api/notifications/live/read' }]);
  });

  test('a swipe to either side dismisses it, a short drag snaps it back', async ({ page }) => {
    const api = await openApp(page, { unread: [note('a', 600, 'Task approved', 'You earn 20 points.', { event: 'task_approved' })] });
    const bubble = bubbles(page).first();
    await expect(bubble).toBeVisible();
    const box = await bubble.boundingBox();
    const y = box.y + box.height / 2;

    await page.mouse.move(box.x + box.width / 2, y);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + 40, y, { steps: 4 });
    await page.mouse.up();
    await expect(bubble).toBeVisible();
    expect(api.writes).toEqual([]);

    await page.mouse.move(box.x + box.width / 2, y);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 - box.width * 0.6, y, { steps: 8 });
    await page.mouse.up();
    await page.clock.runFor(400);
    await expect(bubbles(page)).toHaveCount(0);
    await expect.poll(() => api.writes).toEqual([{ method: 'POST', url: '/api/notifications/a/read' }]);
  });

  test('tapping a bubble opens what it is about', async ({ page }) => {
    const api = await openApp(page, { unread: [note('pay', 600, 'Pocket money waiting', 'You have 20.00 zł to collect.', { event: 'payout_offered', parameters: { url: '/allowance' } })] });

    await bubbles(page).getByRole('button', { name: /Pocket money waiting/ }).click();

    await expect(bubbles(page)).toHaveCount(0);
    await expect(page.getByRole('navigation').getByRole('button', { name: /Pocket money/ }).first()).toHaveAttribute('aria-current', 'page');
    await expect.poll(() => api.writes).toEqual([{ method: 'POST', url: '/api/notifications/pay/read' }]);
  });

  test('the bubble text is readable in the dark theme', async ({ page }) => {
    await page.addInitScript(() => window.localStorage.setItem('themeMode', 'dark'));
    await openApp(page, { unread: [note('a', 600, 'Task approved', 'You earn 20 points.', { event: 'task_approved' })] });
    await expect(bubbles(page)).toHaveCount(1);

    const contrast = await bubbles(page).first().evaluate((bubble) => {
      const rgb = (value) => value.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);
      const luminance = ([r, g, b]) => [r, g, b].map((channel) => {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
      }).reduce((sum, c, index) => sum + c * [0.2126, 0.7152, 0.0722][index], 0);
      const background = luminance(rgb(getComputedStyle(bubble).backgroundColor));
      return [...bubble.querySelectorAll('.notification-bubble__title, .notification-bubble__message')].map((text) => {
        const foreground = luminance(rgb(getComputedStyle(text).color));
        return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
      });
    });
    expect(contrast.length).toBe(2);
    contrast.forEach((ratio) => expect(ratio).toBeGreaterThan(7));
  });
});

test.describe('Notification inbox', () => {
  test('the bell counts what is unread and lists everything, handled and outdated ones marked', async ({ page }) => {
    const api = await openApp(page, {
      unread: [note('a', 60, 'Task waiting for approval', 'Ola waits for approval of "Dishes".')],
      history: [
        note('old', 3000, 'Task waiting for approval', 'Kuba waits for approval of "Rubbish".', { readAt: at(2000), resolvedAt: at(2000), active: false }),
        note('streak', 4000, 'Streak about to break', 'You have a 3 day streak.', { event: 'streak_at_risk', expiresAt: at(3500), active: false }),
      ],
    });

    const bell = page.getByRole('button', { name: 'Notifications: 1 unread' });
    await expect(bell).toBeVisible();
    await bell.click();

    const inbox = page.getByRole('dialog', { name: 'Notifications' });
    await expect(inbox.getByRole('listitem')).toHaveCount(3);
    await expect(inbox.getByRole('listitem').nth(1)).toContainText('Handled');
    await expect(inbox.getByRole('listitem').nth(2)).toContainText('Out of date');
    await expect(inbox.getByRole('img', { name: 'Unread' })).toHaveCount(1);

    await inbox.getByRole('button', { name: 'Mark all as read' }).click();
    await expect.poll(() => api.writes.map((write) => write.url)).toContain('/api/notifications/read-all');
    await expect(page.getByRole('button', { name: 'Notifications', exact: true })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(inbox).toHaveCount(0);
  });

  test('the inbox leads to the notification settings', async ({ page }) => {
    await openApp(page, { preferences: [] });

    await page.getByRole('button', { name: 'Notifications', exact: true }).click();
    await page.getByRole('button', { name: 'Notification settings' }).click();

    await expect(page.getByRole('heading', { name: 'Which notifications I want' })).toBeVisible();
  });
});

test.describe('Which notifications I want', () => {
  const preferences = [
    { event: 'task_assigned', group: 'tasks', enabled: true, channels: ['in_app', 'push'], relevant: true },
    { event: 'task_completed', group: 'tasks', enabled: true, channels: ['email', 'in_app', 'push'], relevant: false },
    { event: 'calendar_changed', group: 'calendar', enabled: true, channels: ['in_app', 'push'], relevant: true },
    { event: 'payout_offered', group: 'allowance', enabled: true, channels: [], relevant: true },
    { event: 'streak_at_risk', group: 'streaks', enabled: false, channels: ['push'], relevant: true },
  ];

  test('every kind has its own switch, grouped, and saving sends the choice', async ({ page }) => {
    const api = await openApp(page, { preferences });
    await page.route('**/api/user-settings/*', (route) => route.fulfill({ json: route.request().method() === 'GET' ? { preferences: [] } : { status: 'success' } }));

    await page.getByRole('navigation').getByRole('button', { name: 'Settings' }).first().click();

    const tasks = page.getByRole('group', { name: 'Tasks' });
    await expect(tasks.getByRole('switch')).toHaveCount(1);
    await expect(page.getByText('A task waits for my approval')).toHaveCount(0);
    await expect(page.getByText('Through: In app, Push').first()).toBeVisible();
    await expect(page.getByText('The application admin switched this kind off for everyone.')).toBeVisible();
    await expect(page.getByRole('switch', { name: 'My streak is about to break' })).toHaveAttribute('aria-checked', 'false');

    await page.getByRole('switch', { name: 'A task was assigned to me' }).click();
    await page.getByRole('switch', { name: 'My streak is about to break' }).click();
    await page.getByRole('button', { name: 'Save Settings' }).click();

    await expect.poll(() => api.writes.find((write) => write.url === '/api/notifications/preferences')?.body).toEqual({
      events: { task_assigned: false, task_completed: true, calendar_changed: true, payout_offered: true, streak_at_risk: true },
    });
  });
});

test.describe('Notification bubbles on a touch screen', () => {
  test.use({ hasTouch: true, viewport: { width: 390, height: 780 } });

  test('a finger swipe dismisses a bubble', async ({ page, browserName }) => {
    test.skip(browserName !== 'chromium', 'Touch input is driven through the Chromium DevTools protocol');
    const api = await openApp(page, { unread: [note('a', 600, 'Task approved', 'You earn 20 points.', { event: 'task_approved' })] });
    const bubble = bubbles(page).first();
    await expect(bubble).toBeVisible();
    const box = await bubble.boundingBox();
    const y = box.y + box.height / 2;
    const touch = await page.context().newCDPSession(page);
    const point = (x) => [{ x, y, id: 1 }];

    await touch.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point(box.x + 60) });
    for (let step = 1; step <= 8; step++) {
      await touch.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: point(box.x + 60 + step * 35) });
    }
    await touch.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await page.clock.runFor(400);

    await expect(bubbles(page)).toHaveCount(0);
    await expect.poll(() => api.writes).toEqual([{ method: 'POST', url: '/api/notifications/a/read' }]);
  });
});
