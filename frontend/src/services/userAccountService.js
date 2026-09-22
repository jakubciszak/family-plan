import apiClient from './apiClient';

const userAccountService = {
    async list() {
        const data = await apiClient.get('/api/users');
        return data?.users ?? [];
    },

    async resetPassword(userId, newPassword) {
        return apiClient.post(`/api/users/${userId}/reset-password`, { newPassword });
    },
};

export default userAccountService;
