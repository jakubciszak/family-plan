import apiClient from './apiClient';

const query = (params) => {
    const search = new URLSearchParams(
        Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
    ).toString();

    return search ? `?${search}` : '';
};

const allowanceService = {
    getRules(teamId) {
        return apiClient.get(`/api/allowance/rules${query({ teamId })}`);
    },

    setRule(rule) {
        return apiClient.put('/api/allowance/rules', rule);
    },

    removeRule(teamId, pointsAccount) {
        return apiClient.delete(`/api/allowance/rules/${pointsAccount}${query({ teamId })}`);
    },

    getWeek(weekStart, userId) {
        return apiClient.get(`/api/allowance/weeks${query({ weekStart, userId })}`);
    },

    closeWeek(userId, weekStart) {
        return apiClient.post('/api/allowance/weeks/close', { userId, weekStart });
    },

    reopenWeek(userId, weekStart) {
        return apiClient.post('/api/allowance/weeks/reopen', { userId, weekStart });
    },

    getWallet(userId) {
        return apiClient.get(`/api/allowance/wallet${query({ userId })}`);
    },

    getLedger(userId, params = {}) {
        return apiClient.get(`/api/allowance/ledger${query({ userId, ...params })}`);
    },

    addIncome(booking) {
        return apiClient.post('/api/allowance/income', booking);
    },

    addExpense(booking) {
        return apiClient.post('/api/allowance/expenses', booking);
    },

    getPayouts(userId) {
        return apiClient.get(`/api/allowance/payouts${query({ userId })}`);
    },

    offerPayout(payout) {
        return apiClient.post('/api/allowance/payouts', payout);
    },

    confirmPayout(payoutId) {
        return apiClient.post(`/api/allowance/payouts/${payoutId}/confirm`, {});
    },

    cancelPayout(payoutId) {
        return apiClient.post(`/api/allowance/payouts/${payoutId}/cancel`, {});
    },

    getGoals(userId, all = false) {
        return apiClient.get(`/api/allowance/goals${query({ userId, all: all ? '1' : '' })}`);
    },

    planGoal(goal) {
        return apiClient.post('/api/allowance/goals', goal);
    },

    adjustGoal(goalId, goal) {
        return apiClient.put(`/api/allowance/goals/${goalId}`, goal);
    },

    putAside(goalId, amount) {
        return apiClient.post(`/api/allowance/goals/${goalId}/put-aside`, { amount });
    },

    takeBack(goalId, amount) {
        return apiClient.post(`/api/allowance/goals/${goalId}/take-back`, { amount });
    },

    spendGoal(goalId, amount, description) {
        return apiClient.post(`/api/allowance/goals/${goalId}/spend`, { amount, description });
    },

    closeGoal(goalId) {
        return apiClient.delete(`/api/allowance/goals/${goalId}`);
    },
};

export default allowanceService;
