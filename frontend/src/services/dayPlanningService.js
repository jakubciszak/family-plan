import apiClient from './apiClient';

const base = '/api/day-planning';
const versionHeaders = (version) => ({ headers: { 'If-Match': `"${version}"` } });
const query = (values) => {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach((item) => params.append(`${key}[]`, item));
        else if (value !== undefined && value !== null && value !== '') params.set(key, value);
    });
    return params.toString();
};
const occurrencePath = (id, key) => `${base}/events/${id}/exceptions/${encodeURIComponent(key)}`;

export default {
    calendar: (params) => apiClient.get(`${base}/calendar?${query(params)}`),
    tags: (teamId) => apiClient.get(`${base}/tags?${query({ teamId })}`),
    definition: (id) => apiClient.get(`${base}/events/${id}`),
    occurrence: (id, key) => apiClient.get(`${base}/events/${id}/occurrences/${encodeURIComponent(key)}`),
    create: (event, key) => apiClient.post(`${base}/events`, event, { headers: { 'Idempotency-Key': key } }),
    update: (id, event, version) => apiClient.patch(`${base}/events/${id}`, event, versionHeaders(version)),
    remove: (id, version) => apiClient.delete(`${base}/events/${id}`, versionHeaders(version)),
    exception: (id, key, changes, version) => apiClient.put(occurrencePath(id, key), changes, versionHeaders(version)),
    restore: (id, key, version, extra = {}) => apiClient.delete(occurrencePath(id, key), { ...versionHeaders(version), body: JSON.stringify(extra) }),
    participate: (id, status, occurrenceKey, extra = {}) => apiClient.put(`${base}/events/${id}/participation/me`, { status, occurrenceKey, ...extra }),
    suggestions: (params) => apiClient.post(`${base}/planning/suggestions`, params),
    saveTag: (tag) => tag.id ? apiClient.patch(`${base}/tags/${tag.id}`, tag) : apiClient.post(`${base}/tags`, tag),
    archiveTag: (id) => apiClient.delete(`${base}/tags/${id}`),
};
