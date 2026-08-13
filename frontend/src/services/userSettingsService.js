const API_BASE_URL = process.env.REACT_APP_API_URL ?? 'http://localhost:8080';

export const userSettingsService = {
    async getUserSettings(userId) {
        const response = await fetch(`${API_BASE_URL}/api/user-settings/${userId}`, {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    async updateUserSettings(userId, preferenceType, options) {
        const response = await fetch(`${API_BASE_URL}/api/user-settings/${userId}`, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                preference_type: preferenceType,
                options: options,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },
};

export default userSettingsService;
