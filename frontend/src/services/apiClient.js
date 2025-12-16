const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8080';

const apiClient = {
    async get(url) {
        const fullUrl = url.startsWith('http') ? url : `${API_BASE_URL}${url}`;
        const response = await fetch(fullUrl, {
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

    async post(url, data) {
        const fullUrl = url.startsWith('http') ? url : `${API_BASE_URL}${url}`;
        const response = await fetch(fullUrl, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    async put(url, data) {
        const fullUrl = url.startsWith('http') ? url : `${API_BASE_URL}${url}`;
        const response = await fetch(fullUrl, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    async delete(url) {
        const fullUrl = url.startsWith('http') ? url : `${API_BASE_URL}${url}`;
        const response = await fetch(fullUrl, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Handle empty response body (204 No Content)
        if (response.status === 204 || response.headers.get('content-length') === '0') {
            return null;
        }

        return await response.json();
    },
};

export default apiClient;
