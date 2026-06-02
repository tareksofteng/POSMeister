import axios from 'axios';

/**
 * Configured Axios instance for all API calls.
 * - Automatically attaches Bearer token from localStorage
 * - Redirects to /login on 401 (token expired / invalid)
 */
const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept':          'application/json',
        'Content-Type':    'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// ── Request interceptor — inject auth token + app locale ──────────────────
// Sending the app's i18n locale as Accept-Language lets the backend
// localise validation errors and business-rule exceptions (e.g. "Insufficient
// stock") via Laravel's __() helper instead of returning hardcoded German.
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('pos_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    const locale = localStorage.getItem('pos_locale');
    if (locale) {
        config.headers['Accept-Language'] = locale;
    }
    return config;
});

// ── Response interceptor — handle auth errors ─────────────────────────────
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('pos_token');
            localStorage.removeItem('pos_user');
            // Let the router guard handle the redirect
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }
        return Promise.reject(error);
    }
);

export default api;
