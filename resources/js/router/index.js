import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { i18n } from '@/plugins/i18n';

const routes = [
    // ── Auth ─────────────────────────────────────────────────────────────
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/auth/LoginView.vue'),
        meta: { requiresGuest: true },
    },

    // ── App shell (protected) ─────────────────────────────────────────────
    {
        path: '/',
        component: () => import('@/components/layout/AppShell.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: { name: 'dashboard' },
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('@/views/dashboard/DashboardView.vue'),
                meta: { titleKey: 'menu.dashboard' },
            },

            // ── Branch management ──────────────────────────────────────────
            {
                path: 'branches',
                name: 'branches',
                component: () => import('@/views/branches/BranchListView.vue'),
                meta: { titleKey: 'menu.branches', permission: 'branches' },
            },

            // ── User management ────────────────────────────────────────────
            {
                path: 'users',
                name: 'users',
                component: () => import('@/views/users/UserListView.vue'),
                meta: { titleKey: 'menu.users', permission: 'users' },
            },

            // ── Application settings ───────────────────────────────────────
            {
                path: 'settings',
                name: 'settings',
                component: () => import('@/views/settings/AppSettingsView.vue'),
                meta: { titleKey: 'menu.settings', adminOnly: true },
            },

            // ── Role permissions ───────────────────────────────────────────
            {
                path: 'settings/roles',
                name: 'role-permissions',
                component: () => import('@/views/settings/RolePermissionsView.vue'),
                meta: { titleKey: 'menu.rolePermissions', adminOnly: true },
            },

            // ── Product module ─────────────────────────────────────────────
            {
                path: 'products',
                name: 'products',
                component: () => import('@/views/products/ProductListView.vue'),
                meta: { titleKey: 'menu.products', permission: 'products' },
            },
            {
                path: 'products/settings',
                name: 'product-settings',
                component: () => import('@/views/products/ProductSettingsView.vue'),
                meta: { titleKey: 'menu.productSettings', permission: 'products' },
            },
            {
                path: 'products/:id',
                name: 'product-detail',
                component: () => import('@/views/products/ProductDetailView.vue'),
                meta: { titleKey: 'menu.products', permission: 'products' },
            },
        ],
    },

    // ── Fallback ──────────────────────────────────────────────────────────
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// ── Global navigation guard ───────────────────────────────────────────────
router.beforeEach((to) => {
    const auth = useAuthStore();

    // Not logged in — redirect to login
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    // Already logged in — redirect away from login
    if (to.meta.requiresGuest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    // Admin-only routes
    if (to.meta.adminOnly && !auth.isAdmin) {
        return { name: 'dashboard' };
    }

    // Permission-gated routes
    if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
        return { name: 'dashboard' };
    }
});

// ── Page title (translated) ───────────────────────────────────────────────
router.afterEach((to) => {
    const key   = to.meta?.titleKey;
    const label = key ? i18n.global.t(key) : null;
    document.title = label ? `${label} — POSmeister` : 'POSmeister';
});

export default router;
