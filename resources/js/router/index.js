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
            {
                path: 'customers/due',
                name: 'customer-due',
                component: () => import('@/views/customers/CustomerDueView.vue'),
                meta: { titleKey: 'menu.customerDue', permission: 'customers' },
            },

            // ── Supplier module ─────────────────────────────────────────────
            {
                path: 'suppliers',
                name: 'suppliers',
                component: () => import('@/views/suppliers/SupplierListView.vue'),
                meta: { titleKey: 'menu.suppliers', permission: 'suppliers' },
            },
            {
                path: 'suppliers/due',
                name: 'supplier-due',
                component: () => import('@/views/suppliers/SupplierDueView.vue'),
                meta: { titleKey: 'menu.supplierDue', permission: 'suppliers' },
            },

            // ── Payment module ──────────────────────────────────────────────
            {
                path: 'payments/customers',
                name: 'customer-payments',
                component: () => import('@/views/payments/CustomerPaymentView.vue'),
                meta: { titleKey: 'menu.customerPayments', permission: 'customers' },
            },
            {
                path: 'payments/suppliers',
                name: 'supplier-payments',
                component: () => import('@/views/payments/SupplierPaymentView.vue'),
                meta: { titleKey: 'menu.supplierPayments', permission: 'suppliers' },
            },

            {
                path: 'sales/create',
                name: 'sale-create',
                component: () => import('@/views/sales/SaleFormView.vue'),
                meta: { titleKey: 'sales.createTitle', permission: 'sales' },
            },

            // Ledger reports
            {
                path: 'reports/customer-ledger',
                name: 'report-customer-ledger',
                component: () => import('@/views/reports/CustomerLedgerView.vue'),
                meta: { titleKey: 'menu.customerLedger', permission: 'reports' },
            },
            {
                path: 'reports/supplier-ledger',
                name: 'report-supplier-ledger',
                component: () => import('@/views/reports/SupplierLedgerView.vue'),
                meta: { titleKey: 'menu.supplierLedger', permission: 'reports' },
            },
            {
                path: 'reports/product-ledger',
                name: 'report-product-ledger',
                component: () => import('@/views/reports/ProductLedgerView.vue'),
                meta: { titleKey: 'menu.productLedger', permission: 'reports' },
            },

            // ── Quotations / Angebote ──────────────────────────────────────
            {
                path: 'quotations',
                name: 'quotations',
                component: () => import('@/views/quotations/QuotationListView.vue'),
                meta: { titleKey: 'menu.quotations', permission: 'sales' },
            },
            {
                path: 'quotations/create',
                name: 'quotation-create',
                component: () => import('@/views/quotations/QuotationFormView.vue'),
                meta: { titleKey: 'quotations.createTitle', permission: 'sales' },
            },
            {
                path: 'quotations/:id/edit',
                name: 'quotation-edit',
                component: () => import('@/views/quotations/QuotationFormView.vue'),
                meta: { titleKey: 'quotations.editTitle', permission: 'sales' },
            },

            // HRM
            {
                path: 'hrm/employees',
                name: 'hrm-employees',
                component: () => import('@/views/hrm/EmployeeListView.vue'),
                meta: { titleKey: 'menu.employees', permission: 'hrm' },
            },
            {
                path: 'hrm/employees/create',
                name: 'hrm-employee-create',
                component: () => import('@/views/hrm/EmployeeFormView.vue'),
                meta: { titleKey: 'hrm.form.createTitle', permission: 'hrm' },
            },
            {
                path: 'hrm/employees/:id',
                name: 'hrm-employee-show',
                component: () => import('@/views/hrm/EmployeeProfileView.vue'),
                meta: { titleKey: 'hrm.profile.title', permission: 'hrm' },
            },
            {
                path: 'hrm/employees/:id/edit',
                name: 'hrm-employee-edit',
                component: () => import('@/views/hrm/EmployeeFormView.vue'),
                meta: { titleKey: 'hrm.form.editTitle', permission: 'hrm' },
            },
            {
                path: 'hrm/departments',
                name: 'hrm-departments',
                component: () => import('@/views/hrm/DepartmentListView.vue'),
                meta: { titleKey: 'menu.departments', permission: 'hrm' },
            },
            {
                path: 'hrm/designations',
                name: 'hrm-designations',
                component: () => import('@/views/hrm/DesignationListView.vue'),
                meta: { titleKey: 'menu.designations', permission: 'hrm' },
            },
            {
                path: 'hrm/shifts',
                name: 'hrm-shifts',
                component: () => import('@/views/hrm/ShiftListView.vue'),
                meta: { titleKey: 'menu.shifts', permission: 'hrm' },
            },
            {
                path: 'hrm/attendance',
                name: 'hrm-attendance-daily',
                component: () => import('@/views/hrm/AttendanceDailyView.vue'),
                meta: { titleKey: 'menu.attendance', permission: 'hrm' },
            },
            {
                path: 'hrm/attendance/monthly',
                name: 'hrm-attendance-monthly',
                component: () => import('@/views/hrm/AttendanceMonthlyView.vue'),
                meta: { titleKey: 'menu.attendanceMonthly', permission: 'hrm' },
            },
            {
                path: 'hrm/payroll',
                name: 'hrm-payroll',
                component: () => import('@/views/hrm/PayrollPeriodListView.vue'),
                meta: { titleKey: 'menu.payroll', permission: 'hrm' },
            },
            {
                path: 'hrm/payroll/:id',
                name: 'hrm-payroll-period',
                component: () => import('@/views/hrm/PayrollPeriodDetailView.vue'),
                meta: { titleKey: 'menu.payroll', permission: 'hrm' },
            },
            {
                path: 'hrm/payslips/:id',
                name: 'hrm-payslip',
                component: () => import('@/views/hrm/PayslipDetailView.vue'),
                meta: { titleKey: 'hrm.payroll.payslip', permission: 'hrm' },
            },
        ],
    },

    // ── Quotation invoice (standalone — no sidebar) ──────────────────────
    {
        path: '/quotations/:id/invoice',
        name: 'quotation-invoice',
        component: () => import('@/views/quotations/QuotationInvoiceView.vue'),
        meta: { requiresAuth: true, titleKey: 'quotations.docTitle' },
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

    // ── Product barcode generator (standalone — no sidebar) ──────────────
    {
        path: '/products/:id/barcode',
        name: 'product-barcode',
        component: () => import('@/views/products/ProductBarcodeView.vue'),
        meta: { requiresAuth: true, titleKey: 'barcode.title' },
    },

    // ── Customer payment receipt (standalone — no sidebar) ───────────────
    {
        path: '/payments/customers/:id/receipt',
        name: 'customer-payment-receipt',
        component: () => import('@/views/payments/CustomerPaymentReceiptView.vue'),
        meta: { requiresAuth: true, titleKey: 'customerPayments.receiptTitle' },
    },

    // ── Supplier payment receipt (standalone — no sidebar) ───────────────
    {
        path: '/payments/suppliers/:id/receipt',
        name: 'supplier-payment-receipt',
        component: () => import('@/views/payments/SupplierPaymentReceiptView.vue'),
        meta: { requiresAuth: true, titleKey: 'supplierPayments.receiptTitle' },
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
