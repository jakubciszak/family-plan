import apiClient from './apiClient';

const base = '/api/school-timetable';

const mondayOf = (date) => {
    const monday = new Date(date);
    monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));

    return [
        monday.getFullYear(),
        String(monday.getMonth() + 1).padStart(2, '0'),
        String(monday.getDate()).padStart(2, '0'),
    ].join('-');
};

const weekAfter = (weekStart) => {
    const next = new Date(`${weekStart}T00:00:00`);
    next.setDate(next.getDate() + 7);

    return mondayOf(next);
};

export default {
    mondayOf,
    weekAfter,
    config: (teamId) => apiClient.get(`${base}/config?teamId=${encodeURIComponent(teamId)}`),
    saveConfig: (signIn) => apiClient.put(`${base}/config`, signIn),
    forgetConfig: (teamId) => apiClient.delete(`${base}/config?teamId=${encodeURIComponent(teamId)}`),
    refreshStudents: (teamId, weekStart) => apiClient.post(`${base}/students/refresh`, { teamId, weekStart }),
    linkStudents: (teamId, links) => apiClient.post(`${base}/students/links`, { teamId, links }),
    importWeek: (teamId, weekStart) => apiClient.post(`${base}/import`, { teamId, weekStart }),
};
