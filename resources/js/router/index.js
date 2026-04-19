import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

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
                meta: { title: 'Dashboard' },
            },

            // ── Branch management ──────────────────────────────────────────
            {
                path: 'branches',
                name: 'branches',
                component: () => import('@/views/branches/BranchListView.vue'),
                meta: { title: 'Branches' },
            },

            // ── User management ────────────────────────────────────────────
            {
                path: 'users',
                name: 'users',
                component: () => import('@/views/users/UserListView.vue'),
                meta: { title: 'Users' },
            },

            // ── Future routes (added per module) ──────────────────────────
            // { path: 'pos',         name: 'pos',       component: () => import('@/views/pos/PosView.vue') },
            // { path: 'products',    name: 'products',  component: () => import('@/views/products/ProductListView.vue') },
            // { path: 'customers',   name: 'customers', component: () => import('@/views/customers/CustomerListView.vue') },
            // { path: 'sales',       name: 'sales',     component: () => import('@/views/sales/SaleListView.vue') },
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
    // Auth store cannot be used before pinia is initialised — access lazily
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresGuest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

// ── Page title ────────────────────────────────────────────────────────────
router.afterEach((to) => {
    const title = to.meta?.title ? `${to.meta.title} — POSmeister` : 'POSmeister';
    document.title = title;
});

export default router;
