import apiClient from './apiClient';

const notificationService = {
    getUnread(limit = 5) {
        return apiClient.get(`/api/notifications?unread=1&limit=${limit}`);
    },

    getRecent(limit = 20) {
        return apiClient.get(`/api/notifications?limit=${limit}`);
    },

    markAsRead(notificationId) {
        return apiClient.post(`/api/notifications/${notificationId}/read`);
    },

    markAllAsRead() {
        return apiClient.post('/api/notifications/read-all');
    },
};

export default notificationService;
