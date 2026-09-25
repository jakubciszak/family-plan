import apiClient from './apiClient';

const notificationService = {
    /** Unread notifications that are still true, newest first. */
    getUnread(limit = 20) {
        return apiClient.get(`/api/notifications?unread=1&limit=${limit}`);
    },

    getRecent(limit = 50) {
        return apiClient.get(`/api/notifications?limit=${limit}`);
    },

    markAsRead(notificationId) {
        return apiClient.post(`/api/notifications/${notificationId}/read`);
    },

    markManyAsRead(ids) {
        return apiClient.post('/api/notifications/read-all', { ids });
    },

    markAllAsRead() {
        return apiClient.post('/api/notifications/read-all');
    },

    getPreferences() {
        return apiClient.get('/api/notifications/preferences');
    },

    updatePreferences(events) {
        return apiClient.put('/api/notifications/preferences', { events });
    },
};

/** Page of the app a notification leads to; null keeps the user where they are. */
export const pageOf = (url) => ({
    '/tasks': 'tasks',
    '/allowance': 'allowance',
    '/day-planning': 'day-planning',
}[url] ?? null);

export default notificationService;
