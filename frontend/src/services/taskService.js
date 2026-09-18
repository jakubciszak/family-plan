import apiClient from './apiClient';

const taskService = {
    async moveExecution(id, doneOn) {
        return await apiClient.put(`/api/task-executions/${id}`, { doneOn });
    },

    async deleteExecution(id) {
        return await apiClient.delete(`/api/task-executions/${id}`);
    },

    async getTaskTypes() {
        return await apiClient.get('/api/task-templates');
    },

    async createTaskType(taskType) {
        return await apiClient.post('/api/task-templates', taskType);
    },

    async updateTaskType(id, taskType) {
        return await apiClient.put(`/api/task-templates/${id}`, taskType);
    },

    async activateTaskType(id) {
        return await apiClient.post(`/api/task-templates/${id}/activate`, {});
    },

    async deactivateTaskType(id) {
        return await apiClient.post(`/api/task-templates/${id}/deactivate`, {});
    },

    async deleteTaskType(id) {
        return await apiClient.delete(`/api/task-templates/${id}`);
    },

    async take(taskTypeId) {
        return await apiClient.post(`/api/task-templates/${taskTypeId}/take`, {});
    },

    async assignTo(taskTypeId, userId) {
        return await apiClient.post(`/api/task-templates/${taskTypeId}/assign`, { userId });
    },

    async bookFor(taskTypeId, userId, doneOn = null) {
        return await apiClient.post(`/api/task-templates/${taskTypeId}/book`, { userId, doneOn });
    },

    async getMyTasks() {
        return await apiClient.get('/api/task-executions/mine');
    },

    async getLeaderboard(teamId, weekStart) {
        const params = new URLSearchParams({ teamId });
        if (weekStart) {
            params.set('weekStart', weekStart);
        }
        return await apiClient.get(`/api/points/leaderboard?${params}`);
    },

    async getWeek(weekStart, userId) {
        const params = new URLSearchParams();
        if (weekStart) {
            params.set('weekStart', weekStart);
        }
        if (userId) {
            params.set('userId', userId);
        }
        const query = params.toString();
        return await apiClient.get(`/api/points/week${query ? `?${query}` : ''}`);
    },

    async takeBackBonus(entryId, userId) {
        return await apiClient.delete(`/api/points/bonuses/${entryId}?userId=${userId}`);
    },

    async getDay(date, userId) {
        const params = new URLSearchParams({ date });
        if (userId) {
            params.set('userId', userId);
        }
        return await apiClient.get(`/api/points/day?${params}`);
    },

    async executionsOf(userId) {
        return await apiClient.get(`/api/task-executions/of/${userId}`);
    },

    async rejectExecution(executionId, reason) {
        return await apiClient.post(`/api/task-executions/${executionId}/reject`, { reason });
    },

    async getAwaitingApproval() {
        return await apiClient.get('/api/task-executions/awaiting-approval');
    },

    async complete(executionId, doneOn = null) {
        return await apiClient.post(
            `/api/task-executions/${executionId}/complete`,
            doneOn ? { doneOn } : {}
        );
    },

    async approve(executionId) {
        return await apiClient.post(`/api/task-executions/${executionId}/approve`, {});
    },

    async abandon(executionId) {
        return await apiClient.post(`/api/task-executions/${executionId}/abandon`, {});
    },
};

export default taskService;
