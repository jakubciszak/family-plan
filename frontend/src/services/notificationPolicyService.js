const API_BASE_URL = process.env.REACT_APP_API_URL ?? '';

async function request(path, options = {}) {
    const response = await fetch(`${API_BASE_URL}${path}`, {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        ...options,
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
}

export const notificationPolicyService = {
    async getPolicies() {
        return request('/api/notification-policies');
    },

    async updatePolicy(event, channels) {
        return request(`/api/notification-policies/${event}`, {
            method: 'PUT',
            body: JSON.stringify({ channels }),
        });
    },
};

export default notificationPolicyService;
