import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authService } from '@/services/authService';
import { saveAuthSnapshot, loadAuthSnapshot, clearAuthSnapshot } from '@/offline/authCache';

export const useAuthStore = defineStore('auth', () => {

    // ── State ──────────────────────────────────────────────────────────────
    const token       = ref(localStorage.getItem('pos_token') || null);
    const user        = ref(JSON.parse(localStorage.getItem('pos_user') || 'null'));
    const permissions = ref(JSON.parse(localStorage.getItem('pos_permissions') || '[]'));
    const loading     = ref(false);
    const error       = ref(null);

    // ── Getters ────────────────────────────────────────────────────────────
    const isAuthenticated = computed(() => !!token.value);
    const userName        = computed(() => user.value?.name      || '');
    const userRole        = computed(() => user.value?.role      || null);
    const branchId        = computed(() => user.value?.branch_id || null);
    const isAdmin         = computed(() => user.value?.role === 'admin');

    /** Check if the current user has access to a module key */
    function hasPermission(key) {
        if (isAdmin.value) return true;
        return permissions.value.includes(key);
    }

    // ── Actions ────────────────────────────────────────────────────────────

    async function login(credentials) {
        loading.value = true;
        error.value   = null;

        try {
            const { data } = await authService.login(credentials);
            _persist(data.token, data.user, data.permissions ?? []);
            // Warm the IndexedDB snapshot in the background so the cashier
            // immediately has products + customers available offline.
            import('@/offline/snapshotPreloader')
                .then((m) => m.downloadSnapshot?.())
                .catch(() => {});
            return true;

        } catch (err) {
            // OFFLINE LOGIN — three signals can mean "the network died, not
            // a bad password":
            //   1. axios never got a response object at all
            //   2. navigator.onLine is explicitly false
            //   3. our service worker returned its 503 offline envelope
            //      (X-Posmeister-Offline: 1 header)
            // In any of those cases, if we have a recent snapshot for THIS
            // email, restore the session locally so the cashier can keep
            // working. A real 4xx from the server falls through as a normal
            // credential failure.
            const noResponse  = !err?.response;
            const swOffline   = err?.response?.headers?.['x-posmeister-offline'] === '1';
            const navOffline  = typeof navigator !== 'undefined' && navigator.onLine === false;
            const networkFailed = noResponse || swOffline || navOffline;
            if (networkFailed) {
                const snap = await loadAuthSnapshot().catch(() => null);
                const cachedEmail = snap?.user?.email?.toLowerCase();
                const tryingEmail = (credentials.email || '').toLowerCase();
                if (snap?.token && cachedEmail && cachedEmail === tryingEmail) {
                    _persist(snap.token, snap.user, snap.permissions || []);
                    return true;
                }
            }
            error.value = _extractError(err);
            return false;

        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        // If the cashier is offline we can't talk to the server, AND we don't
        // want to wipe the IndexedDB snapshot — otherwise re-login while still
        // offline would be impossible. Clear in-memory + localStorage state
        // only; the snapshot stays so login() can restore it. Also set the
        // `pos_logged_out` flag so the router guard's auto-restore does not
        // immediately bring the session back the next time we touch a
        // protected route (which would otherwise create a logout → restore
        // → still-on-dashboard loop).
        const offline = typeof navigator !== 'undefined' && navigator.onLine === false;
        if (offline) {
            localStorage.setItem('pos_logged_out', '1');
            _clearLocalOnly();
            return;
        }

        try {
            await authService.logout();
        } catch {
            // Silently ignore — token may already be invalid
        } finally {
            _clear();
        }
    }

    async function fetchMe() {
        if (!token.value) return;

        try {
            const { data } = await authService.me();
            user.value = data.user;
            localStorage.setItem('pos_user', JSON.stringify(data.user));

            if (data.permissions) {
                permissions.value = data.permissions;
                localStorage.setItem('pos_permissions', JSON.stringify(data.permissions));
            }

            // Phase Ω — persist a verified snapshot for offline recovery.
            saveAuthSnapshot({
                token: token.value,
                user: user.value,
                permissions: permissions.value,
            }).catch(() => {});
        } catch (err) {
            // If the request failed because the user is offline (or the
            // server is briefly unreachable) keep the existing session
            // so the cashier can continue working. Only a confirmed 401
            // from the server actually means "log out".
            const status = err?.response?.status;
            if (status === 401 || status === 419) {
                _clear();
            }
            // Otherwise swallow — offline grace window handles the rest.
        }
    }

    /** Restore session from the IndexedDB snapshot when localStorage is empty. */
    async function restoreFromOfflineSnapshot() {
        if (token.value && user.value) return true;
        // Honour an explicit offline logout — the snapshot stays in
        // IndexedDB (so re-login still works) but we do NOT auto-restore
        // until the user types credentials again.
        if (localStorage.getItem('pos_logged_out') === '1') return false;
        const snap = await loadAuthSnapshot().catch(() => null);
        if (!snap?.token || !snap?.user) return false;
        _persist(snap.token, snap.user, snap.permissions || []);
        return true;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    function _persist(newToken, newUser, newPermissions) {
        token.value       = newToken;
        user.value        = newUser;
        permissions.value = newPermissions;
        localStorage.setItem('pos_token',       newToken);
        localStorage.setItem('pos_user',        JSON.stringify(newUser));
        localStorage.setItem('pos_permissions', JSON.stringify(newPermissions));
        // A successful login (online OR offline-fallback) clears any prior
        // "explicitly logged out" sentinel so future page reloads restore.
        localStorage.removeItem('pos_logged_out');

        // Phase Ω — mirror into IndexedDB so a reload while offline can
        // still rehydrate the session without hitting the server.
        saveAuthSnapshot({
            token: newToken,
            user: newUser,
            permissions: newPermissions,
        }).catch(() => {});
    }

    function _clear() {
        _clearLocalOnly();
        clearAuthSnapshot().catch(() => {});
    }

    function _clearLocalOnly() {
        token.value       = null;
        user.value        = null;
        permissions.value = [];
        localStorage.removeItem('pos_token');
        localStorage.removeItem('pos_user');
        localStorage.removeItem('pos_permissions');
    }

    function _extractError(err) {
        return err.response?.data?.errors?.email?.[0]
            ?? err.response?.data?.message
            ?? 'Login failed. Please check your credentials.';
    }

    return {
        token, user, permissions, loading, error,
        isAuthenticated, userName, userRole, branchId, isAdmin,
        hasPermission,
        login, logout, fetchMe, restoreFromOfflineSnapshot,
    };
});
