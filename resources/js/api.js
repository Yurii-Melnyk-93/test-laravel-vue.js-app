import axios from 'axios';

const TOKEN_KEY = 'promo_api_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

// The player is always resolved from this token server side.
// No endpoint accepts a player id from the request body or the URL.
api.interceptors.request.use((config) => {
    const token = getToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

// A stale or revoked token must not leave the UI half-authenticated.
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            clearToken();
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }

        return Promise.reject(error);
    },
);

export default api;
