import apiClient from './apiClient';

const taskService = {
    async getTaskTypes() {
        return await apiClient.get('/api/task-templates');
    },

    async createTaskType(taskType) {
        return await apiClient.post('/api/task-templates', taskType);
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

    async getMyTasks() {
        return await apiClient.get('/api/task-executions/mine');
    },

    async getWeek(weekStart) {
        const query = weekStart ? `?weekStart=${weekStart}` : '';
        return await apiClient.get(`/api/points/week${query}`);
    },

    async getAwaitingApproval() {
        return await apiClient.get('/api/task-executions/awaiting-approval');
    },

    async complete(executionId) {
        return await apiClient.post(`/api/task-executions/${executionId}/complete`, {});
    },

    async approve(executionId) {
        return await apiClient.post(`/api/task-executions/${executionId}/approve`, {});
    },

    async abandon(executionId) {
        return await apiClient.post(`/api/task-executions/${executionId}/abandon`, {});
    },
};

export default taskService;
