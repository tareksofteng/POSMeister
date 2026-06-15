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

            // ── Platform: system health (admin-only) ───────────────────────
            {
                path: 'system/health',
                name: 'system-health',
                component: () => import('@/views/system/SystemHealthView.vue'),
                meta: { titleKey: 'system.health.title', adminOnly: true },
            },

            // ── Phase Ω+ Notification Center ──────────────────────────────
            {
                path: 'notifications',
                name: 'notifications',
                component: () => import('@/views/notifications/NotificationCenterView.vue'),
                meta: { titleKey: 'notifications.centerTitle' },
            },
            {
                path: 'notifications/preferences',
                name: 'notification-preferences',
                component: () => import('@/views/notifications/NotificationPreferencesView.vue'),
                meta: { titleKey: 'notifications.preferences' },
            },
            {
                path: 'notifications/analytics',
                name: 'notification-analytics',
                component: () => import('@/views/notifications/NotificationAnalyticsView.vue'),
                meta: { titleKey: 'notifications.analytics.title', adminOnly: true },
            },
            {
                path: 'notifications/rules',
                name: 'notification-rules',
                component: () => import('@/views/notifications/NotificationRulesView.vue'),
                meta: { titleKey: 'notifications.rules.title', adminOnly: true },
            },
            {
                path: 'notifications/digest',
                name: 'notification-digest',
                component: () => import('@/views/notifications/NotificationDigestView.vue'),
                meta: { titleKey: 'notifications.digest.title' },
            },
            {
                path: 'notifications/devices',
                name: 'notification-devices',
                component: () => import('@/views/notifications/DeviceManagerView.vue'),
                meta: { titleKey: 'push.devices.title' },
            },

            // ── Phase Z: SystemOps (admin-only) ───────────────────────────
            {
                path: 'system/dashboard',
                name: 'system-dashboard',
                component: () => import('@/views/system/SystemDashboardView.vue'),
                meta: { titleKey: 'systemOps.dashboard.title', adminOnly: true },
            },
            {
                path: 'system/monitor',
                name: 'system-monitor',
                component: () => import('@/views/system/HealthMonitorView.vue'),
                meta: { titleKey: 'systemOps.monitor.title', adminOnly: true },
            },
            {
                path: 'system/queue',
                name: 'system-queue',
                component: () => import('@/views/system/QueueDiagnosticsView.vue'),
                meta: { titleKey: 'systemOps.queue.title', adminOnly: true },
            },
            {
                path: 'system/backup',
                name: 'system-backup',
                component: () => import('@/views/system/BackupManagerView.vue'),
                meta: { titleKey: 'systemOps.backup.title', adminOnly: true },
            },
            {
                path: 'system/environment',
                name: 'system-environment',
                component: () => import('@/views/system/EnvironmentCheckView.vue'),
                meta: { titleKey: 'systemOps.environment.title', adminOnly: true },
            },
            {
                path: 'system/sync',
                name: 'system-sync',
                component: () => import('@/views/system/SyncRecoveryView.vue'),
                meta: { titleKey: 'systemOps.sync.title', adminOnly: true },
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
            {
                path: 'inventory/intelligence',
                name: 'inventory-intelligence',
                component: () => import('@/views/inventory/InventoryIntelligenceView.vue'),
                meta: { titleKey: 'inventory.intelligence.title', permission: 'inventory' },
            },
            {
                path: 'inventory/reorder',
                name: 'inventory-reorder',
                component: () => import('@/views/inventory/ReorderSuggestionsView.vue'),
                meta: { titleKey: 'inventory.reorder.title', permission: 'inventory' },
            },
            {
                path: 'inventory/dead-stock',
                name: 'inventory-dead-stock',
                component: () => import('@/views/inventory/DeadStockReportView.vue'),
                meta: { titleKey: 'inventory.deadStock.title', permission: 'inventory' },
            },
            {
                path: 'inventory/analytics',
                name: 'inventory-analytics',
                component: () => import('@/views/inventory/InventoryAnalyticsView.vue'),
                meta: { titleKey: 'inventory.analytics.title', permission: 'inventory' },
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
            {
                path: 'hrm/reports',
                name: 'hrm-reports',
                component: () => import('@/views/hrm/HrmReportsView.vue'),
                meta: { titleKey: 'menu.hrmReports', permission: 'hrm' },
            },

            // Phase G — Workforce intelligence
            {
                path: 'hrm/payroll-approvals',
                name: 'hrm-payroll-approvals',
                component: () => import('@/views/hrm/PayrollApprovalsView.vue'),
                meta: { titleKey: 'hrm.approval.title', permission: 'hrm' },
            },
            {
                path: 'hrm/salary-advances',
                name: 'hrm-salary-advances',
                component: () => import('@/views/hrm/SalaryAdvancesView.vue'),
                meta: { titleKey: 'hrm.advance.title', permission: 'hrm' },
            },
            {
                path: 'hrm/workforce-intelligence',
                name: 'hrm-workforce',
                component: () => import('@/views/hrm/WorkforceIntelligenceView.vue'),
                meta: { titleKey: 'hrm.workforce.title', permission: 'hrm' },
            },
            {
                path: 'hrm/attendance-intelligence',
                name: 'hrm-attendance-intel',
                component: () => import('@/views/hrm/AttendanceIntelligenceView.vue'),
                meta: { titleKey: 'hrm.attIntel.title', permission: 'hrm' },
            },

            // Expense module
            {
                path: 'expenses/categories',
                name: 'expense-categories',
                component: () => import('@/views/expenses/ExpenseCategoryListView.vue'),
                meta: { titleKey: 'menu.expenseCategories', permission: 'expenses' },
            },
            {
                path: 'expenses',
                name: 'expenses',
                component: () => import('@/views/expenses/ExpenseListView.vue'),
                meta: { titleKey: 'menu.expenses', permission: 'expenses' },
            },
            {
                path: 'expenses/reports',
                name: 'expense-reports',
                component: () => import('@/views/expenses/ExpenseReportsView.vue'),
                meta: { titleKey: 'menu.expenseReports', permission: 'expenses' },
            },

            // Finance module
            {
                path: 'finance/dashboard',
                name: 'finance-dashboard',
                component: () => import('@/views/finance/FinancialDashboardView.vue'),
                meta: { titleKey: 'menu.financialDashboard', permission: 'finance' },
            },
            {
                path: 'finance/budgets',
                name: 'finance-budgets',
                component: () => import('@/views/finance/BudgetListView.vue'),
                meta: { titleKey: 'menu.budgets', permission: 'finance' },
            },
            {
                path: 'finance/budgets/:id/analytics',
                name: 'finance-budget-analytics',
                component: () => import('@/views/finance/BudgetAnalyticsView.vue'),
                meta: { titleKey: 'finance.analytics.title', permission: 'finance' },
            },
            {
                path: 'finance/cashflow',
                name: 'finance-cashflow',
                component: () => import('@/views/finance/CashflowDashboardView.vue'),
                meta: { titleKey: 'menu.cashflow', permission: 'finance' },
            },
            {
                path: 'finance/calendar',
                name: 'finance-calendar',
                component: () => import('@/views/finance/FinancialCalendarView.vue'),
                meta: { titleKey: 'menu.financialCalendar', permission: 'finance' },
            },

            // Accounting module
            {
                path: 'accounting',
                name: 'accounting-dashboard',
                component: () => import('@/views/accounting/AccountingDashboardView.vue'),
                meta: { titleKey: 'accounting.dashboard.title', permission: 'accounting' },
            },
            {
                path: 'accounting/coa',
                name: 'accounting-coa',
                component: () => import('@/views/accounting/ChartOfAccountsView.vue'),
                meta: { titleKey: 'accounting.coa.title', permission: 'accounting' },
            },
            {
                path: 'accounting/journal',
                name: 'accounting-journal',
                component: () => import('@/views/accounting/JournalEntryListView.vue'),
                meta: { titleKey: 'accounting.journal.title', permission: 'accounting' },
            },
            {
                path: 'accounting/ledger',
                name: 'accounting-ledger',
                component: () => import('@/views/accounting/LedgerView.vue'),
                meta: { titleKey: 'accounting.ledger.title', permission: 'accounting' },
            },
            {
                path: 'accounting/trial-balance',
                name: 'accounting-trial-balance',
                component: () => import('@/views/accounting/TrialBalanceView.vue'),
                meta: { titleKey: 'accounting.trialBalance.title', permission: 'accounting' },
            },
            {
                path: 'accounting/profit-loss',
                name: 'accounting-profit-loss',
                component: () => import('@/views/accounting/ProfitLossView.vue'),
                meta: { titleKey: 'accounting.pl.title', permission: 'accounting' },
            },
            {
                path: 'accounting/balance-sheet',
                name: 'accounting-balance-sheet',
                component: () => import('@/views/accounting/BalanceSheetView.vue'),
                meta: { titleKey: 'accounting.balanceSheet.title', permission: 'accounting' },
            },
            {
                path: 'accounting/cashbook',
                name: 'accounting-cashbook',
                component: () => import('@/views/accounting/CashbookView.vue'),
                meta: { titleKey: 'accounting.cashbook.title', permission: 'accounting' },
            },
            {
                path: 'accounting/banks',
                name: 'accounting-banks',
                component: () => import('@/views/accounting/BankAccountView.vue'),
                meta: { titleKey: 'accounting.bank.title', permission: 'accounting' },
            },

            // CRM module
            {
                path: 'crm',
                name: 'crm-intelligence',
                component: () => import('@/views/crm/CustomerIntelligenceView.vue'),
                meta: { titleKey: 'crm.intelligence.title', permission: 'crm' },
            },
            {
                path: 'crm/segments',
                name: 'crm-segments',
                component: () => import('@/views/crm/CustomerSegmentsView.vue'),
                meta: { titleKey: 'crm.segments.pageTitle', permission: 'crm' },
            },
            {
                path: 'crm/customers/:id',
                name: 'crm-customer-profile',
                component: () => import('@/views/crm/CustomerProfileView.vue'),
                meta: { titleKey: 'crm.profile.title', permission: 'crm' },
            },
            {
                path: 'crm/wallets',
                name: 'crm-wallets',
                component: () => import('@/views/crm/WalletTransactionsView.vue'),
                meta: { titleKey: 'crm.wallet.recentTitle', permission: 'crm' },
            },
            {
                path: 'crm/loyalty-settings',
                name: 'crm-loyalty-settings',
                component: () => import('@/views/crm/LoyaltySettingsView.vue'),
                meta: { titleKey: 'crm.loyaltySettings.title', permission: 'crm' },
            },

            // OMS / Omnichannel
            {
                path: 'orders',
                name: 'oms-orders',
                component: () => import('@/views/oms/OrderDashboardView.vue'),
                meta: { titleKey: 'oms.dashboard.title', permission: 'oms' },
            },
            {
                path: 'shipments',
                name: 'oms-shipments',
                component: () => import('@/views/oms/ShipmentTrackingView.vue'),
                meta: { titleKey: 'oms.shipment.title', permission: 'oms' },
            },
            {
                path: 'automation',
                name: 'oms-automation',
                component: () => import('@/views/oms/AutomationCenterView.vue'),
                meta: { titleKey: 'oms.automation.title', permission: 'oms' },
            },
            {
                path: 'notifications',
                name: 'oms-notifications',
                component: () => import('@/views/oms/NotificationCenterView.vue'),
                meta: { titleKey: 'oms.notify.title', permission: 'oms' },
            },
            {
                path: 'ecommerce',
                name: 'oms-ecommerce',
                component: () => import('@/views/oms/EcommerceSyncView.vue'),
                meta: { titleKey: 'oms.sync.title', permission: 'oms' },
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
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Phase Ω — if the in-memory session is empty but we have an offline
    // snapshot, rehydrate from IndexedDB so a reload while offline does
    // not log the cashier out.
    //
    // We try the snapshot for EVERY auth-required route, including the
    // first navigation after a cold PWA boot — that's the only moment
    // the production tab is empty AND offline, and missing this restore
    // is what was kicking users to /login on production reloads.
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        try { await auth.restoreFromOfflineSnapshot(); } catch { /* no snapshot */ }
    }

    // Guest-only routes (login, register) — if we have a valid offline
    // snapshot, restore it so a reload doesn't dump the user back here.
    if (to.meta.requiresGuest && !auth.isAuthenticated) {
        try { await auth.restoreFromOfflineSnapshot(); } catch { /* no snapshot */ }
    }

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

// ── Lazy-chunk failure recovery ───────────────────────────────────────────
// "Failed to fetch dynamically imported module" fires when:
//   (a) the user is offline AND the SW doesn't have that chunk cached, or
//   (b) a deploy bumped chunk hashes between page-load and navigation.
// In case (b) a hard reload picks up the new HTML+chunks. In case (a)
// the SW serves the cached shell so the user sees the dashboard instead
// of a frozen blank screen. We guard against reload loops with a flag.
let chunkReloadAttempted = false;
router.onError((err, to) => {
    const msg = String(err?.message || '');
    const isChunkError =
        msg.includes('Failed to fetch dynamically imported module') ||
        msg.includes('Importing a module script failed') ||
        msg.includes('Unable to preload CSS');
    if (!isChunkError || chunkReloadAttempted) return;
    chunkReloadAttempted = true;
    window.location.replace(to?.fullPath || window.location.pathname);
});

export default router;
