import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authService } from '@/services/authService';

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
            return true;

        } catch (err) {
            error.value = _extractError(err);
            return false;

        } finally {
            loading.value = false;
        }
    }

    async function logout() {
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
        } catch {
            _clear();
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────

    function _persist(newToken, newUser, newPermissions) {
        token.value       = newToken;
        user.value        = newUser;
        permissions.value = newPermissions;
        localStorage.setItem('pos_token',       newToken);
        localStorage.setItem('pos_user',        JSON.stringify(newUser));
        localStorage.setItem('pos_permissions', JSON.stringify(newPermissions));
    }

    function _clear() {
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
        login, logout, fetchMe,
    };
});
