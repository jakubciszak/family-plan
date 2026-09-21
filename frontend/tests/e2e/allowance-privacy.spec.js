const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

test('administrator sees only payout information for a member', async ({ page }) => {
    await setupAuthenticatedSession(page, 'admin');
    const privateRequests = [];
    page.on('request', (request) => {
        if (/\/api\/allowance\/(goals|ledger)/.test(request.url())) {
            privateRequests.push(request.url());
        }
    });
    await page.route('**/api/teams/team-1/members', (route) => route.fulfill({
        json: { members: [{ userId: 'child-1', userName: 'Child', role: 'member' }] },
    }));
    await page.route('**/api/allowance/rules*', (route) => route.fulfill({ json: { rules: [] } }));
    await page.route('**/api/allowance/weeks*', (route) => route.fulfill({ json: null }));
    await page.route('**/api/allowance/wallet*', (route) => route.fulfill({
        json: {
            currency: 'PLN', pending: 600, paid: 400,
            available: 99999, putAside: 99999, earned: 99999, otherIncome: 99999, spent: 99999,
            awaitingConfirmation: [{ id: 'payout-1', amount: 200, note: 'Friday', offeredAt: '2026-09-21' }],
        },
    }));
    await page.goto('/');
    await page.locator('.app-nav').getByRole('button', { name: /money|kieszonkowe|kasa/i }).click();
    const summary = page.getByTestId('payout-summary');
    await expect(summary).toBeVisible();
    await expect(summary.locator('.wallet__card')).toHaveCount(2);
    await expect(summary).toContainText(/Paid out|Wypłacone/);
    await expect(summary).toContainText('Friday');
    await expect(page.getByTestId('wallet')).toHaveCount(0);
    await expect(page.getByTestId('panel-member-goals')).toHaveCount(0);
    await expect(page.locator('#payout-amount')).toBeVisible();
    expect(privateRequests).toEqual([]);
});
