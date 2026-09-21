const API_BASE_URL = process.env.REACT_APP_API_URL ?? '';

const fullUrlFor = (url) => (url.startsWith('http') ? url : `${API_BASE_URL}${url}`);

const readBody = async (response) => {
    if (response.status === 204 || response.headers.get('content-length') === '0') {
        return null;
    }

    const text = await response.text();

    if (!text) {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
};

const failOn = async (response) => {
    const error = new Error(`HTTP error! status: ${response.status}`);
    error.response = { status: response.status, data: await readBody(response) };
    throw error;
};

const request = async (url, options) => {
    const response = await fetch(fullUrlFor(url), {
        credentials: 'include',
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {}),
        },
    });

    if (!response.ok) {
        await failOn(response);
    }

    return readBody(response);
};

const apiClient = {
    get(url, options = {}) {
        return request(url, { ...options, method: 'GET' });
    },

    post(url, data, options = {}) {
        return request(url, { ...options, method: 'POST', body: JSON.stringify(data ?? {}) });
    },

    put(url, data, options = {}) {
        return request(url, { ...options, method: 'PUT', body: JSON.stringify(data ?? {}) });
    },

    patch(url, data, options = {}) {
        return request(url, { ...options, method: 'PATCH', body: JSON.stringify(data ?? {}) });
    },

    delete(url, options = {}) {
        return request(url, { ...options, method: 'DELETE' });
    },
};

export default apiClient;
