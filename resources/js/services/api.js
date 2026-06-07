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
    // Branch workspace context — the BranchContextService on the server
    // reads this header, validates the user can access it, and pins it
    // for the lifetime of the request. Empty string ("All branches"
    // super workspace for admins) is a legal value, so we send the
    // header whenever the key exists in localStorage.
    const branchId = localStorage.getItem('pos_branch_id');
    if (branchId !== null) {
        config.headers['X-Branch-Id'] = branchId;
    }
    return config;
});

// ── Response interceptor — handle auth errors ─────────────────────────────
// On a confirmed 401 (server says the token is invalid), wipe BOTH layers of
// auth state: localStorage and the IndexedDB offline snapshot. Skipping the
// snapshot was creating an infinite logout↔auto-restore loop on production —
// the router guard would call restoreFromOfflineSnapshot(), pull the same
// expired token back from IDB, and the next request would 401 again. The
// user's symptom was "I click Sales List, the page redirects back to
// Dashboard" because the half-cleared auth left them in a permission-denied
// state mid-navigation.
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('pos_token');
            localStorage.removeItem('pos_user');
            localStorage.removeItem('pos_permissions');
            // Drop the IndexedDB snapshot too — otherwise the next nav
            // guard pulls the same stale token back and we 401-loop.
            try {
                const { clearAuthSnapshot } = await import('@/offline/authCache');
                await clearAuthSnapshot();
            } catch { /* IDB might be unavailable */ }
            // Router guard listens for this and bounces to /login.
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }
        return Promise.reject(error);
    }
);

export default api;
