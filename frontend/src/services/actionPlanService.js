import apiClient from './apiClient';

export default {
    list: () => apiClient.get('/api/action-plans'),
    save: (plan) => plan.id
        ? apiClient.put(`/api/action-plans/${plan.id}`, plan)
        : apiClient.post('/api/action-plans', plan),
    remove: (id) => apiClient.delete(`/api/action-plans/${id}`),
};
