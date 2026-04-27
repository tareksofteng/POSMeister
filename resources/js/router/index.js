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


            // ── Purchase module ────────────────────────────────────────────
            {
                path: 'purchases',
                name: 'purchases',
                component: () => import('@/views/purchases/PurchaseListView.vue'),
                meta: { titleKey: 'menu.purchases', permission: 'purchases' },
            },
            {
                path: 'purchases/new',
                name: 'purchase-create',
                component: () => import('@/views/purchases/PurchaseFormView.vue'),
                meta: { titleKey: 'menu.purchases', permission: 'purchases' },
            },
            {
                path: 'purchases/:id/edit',
                name: 'purchase-edit',
                component: () => import('@/views/purchases/PurchaseFormView.vue'),
                meta: { titleKey: 'menu.purchases', permission: 'purchases' },
            },

            // ── Sales module ───────────────────────────────────────────────
            {
                path: 'sales',
                name: 'sales',
                component: () => import('@/views/sales/SaleListView.vue'),
                meta: { titleKey: 'menu.sales', permission: 'sales' },
            },
            {
                path: 'sale-returns',
                name: 'sale-returns',
                component: () => import('@/views/sales/SaleReturnView.vue'),
                meta: { titleKey: 'menu.saleReturns', permission: 'sales' },
            },

            // ── Purchase returns ───────────────────────────────────────────
            {
                path: 'purchase-returns',
                name: 'purchase-returns',
                component: () => import('@/views/purchases/PurchaseReturnView.vue'),
                meta: { titleKey: 'menu.purchaseReturns', permission: 'purchases' },
            },

            // ── Purchase record report ─────────────────────────────────────
            {
                path: 'purchase-record',
                name: 'purchase-record',
                component: () => import('@/views/purchases/PurchaseRecordView.vue'),
                meta: { titleKey: 'menu.purchaseRecord', permission: 'purchases' },
            },

            // ── Sale record report ─────────────────────────────────────────
            {
                path: 'sale-record',
                name: 'sale-record',
                component: () => import('@/views/sales/SaleRecordView.vue'),
                meta: { titleKey: 'menu.saleRecord', permission: 'sales' },
            },

            // ── Purchase return record report ──────────────────────────────
            {
                path: 'purchase-return-record',
                name: 'purchase-return-record',
                component: () => import('@/views/purchases/PurchaseReturnRecordView.vue'),
                meta: { titleKey: 'menu.purchaseReturnRecord', permission: 'purchases' },
            },

            // ── Sale return record report ──────────────────────────────────
            {
                path: 'sale-return-record',
                name: 'sale-return-record',
                component: () => import('@/views/sales/SaleReturnRecordView.vue'),
                meta: { titleKey: 'menu.saleReturnRecord', permission: 'sales' },
            },

            // ── Stock / Inventory ──────────────────────────────────────────
            {
                path: 'inventory',
                name: 'inventory',
                component: () => import('@/views/stock/StockView.vue'),
                meta: { titleKey: 'menu.inventory', permission: 'inventory' },
            },

            // ── Customer module ────────────────────────────────────────────
            {
                path: 'customers',
                name: 'customers',
                component: () => import('@/views/customers/CustomerListView.vue'),
                meta: { titleKey: 'menu.customers', permission: 'customers' },
            },

            // ── Supplier module ─────────────────────────────────────────────
            {
                path: 'suppliers',
                name: 'suppliers',
                component: () => import('@/views/suppliers/SupplierListView.vue'),
                meta: { titleKey: 'menu.suppliers', permission: 'suppliers' },
            },
        ],
    },

    // ── POS terminal (standalone — full screen, no sidebar) ──────────────
    {
        path: '/pos',
        name: 'pos',
        component: () => import('@/views/pos/PosView.vue'),
        meta: { requiresAuth: true, titleKey: 'menu.pointOfSale' },
    },

    // ── Sale invoice (standalone — no sidebar) ────────────────────────────
    {
        path: '/sales/:id/invoice',
        name: 'sale-invoice',
        component: () => import('@/views/sales/SaleInvoiceView.vue'),
        meta: { requiresAuth: true, titleKey: 'sales.invoiceTitle' },
    },

    // ── Sale return invoice (standalone — no sidebar) ─────────────────────
    {
        path: '/sale-returns/:id/invoice',
        name: 'sale-return-invoice',
        component: () => import('@/views/sales/SaleReturnInvoiceView.vue'),
        meta: { requiresAuth: true, titleKey: 'saleReturns.invoiceTitle' },
    },

    // ── Purchase invoice (standalone — no sidebar) ────────────────────────
    {
        path: '/purchases/:id/invoice',
        name: 'purchase-invoice',
        component: () => import('@/views/purchases/PurchaseInvoiceView.vue'),
        meta: { requiresAuth: true, titleKey: 'purchases.invoiceTitle' },
    },

    // ── Purchase return invoice (standalone — no sidebar) ─────────────────
    {
        path: '/purchase-returns/:id/invoice',
        name: 'purchase-return-invoice',
        component: () => import('@/views/purchases/PurchaseReturnInvoiceView.vue'),
        meta: { requiresAuth: true, titleKey: 'purchaseReturns.invoiceTitle' },
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
