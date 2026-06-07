<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Modules\Branch\Controllers\BranchController;
use App\Modules\Product\Controllers\BrandController;
use App\Modules\Purchase\Controllers\PurchaseReturnController;
use App\Modules\Purchase\Controllers\SupplierPaymentController;
use App\Modules\Expense\Controllers\ExpenseCategoryController;
use App\Modules\Expense\Controllers\ExpenseController as ExpenseModuleController;
use App\Modules\Expense\Controllers\ExpenseReportsController;
use App\Modules\Finance\Controllers\BudgetAnalyticsController;
use App\Modules\Finance\Controllers\BudgetController;
use App\Modules\Finance\Controllers\CashflowController;
use App\Modules\Finance\Controllers\FinancialAlertController;
use App\Modules\Finance\Controllers\FinancialCalendarController;
use App\Modules\Finance\Controllers\FinancialDashboardController;
use App\Modules\Accounting\Controllers\AccountingReportController;
use App\Modules\Accounting\Controllers\BankAccountController;
use App\Modules\Accounting\Controllers\CashbookController;
use App\Modules\Accounting\Controllers\ChartOfAccountController;
use App\Modules\Accounting\Controllers\JournalEntryController;
use App\Modules\Inventory\Controllers\InventoryAnalyticsController;
use App\Modules\Inventory\Controllers\InventoryIntelligenceController;
use App\Modules\Inventory\Controllers\ProcurementController;
use App\Modules\Inventory\Controllers\SupplierAnalyticsController;
use App\Modules\CRM\Controllers\CampaignController;
use App\Modules\CRM\Controllers\CustomerIntelligenceController;
use App\Modules\CRM\Controllers\LoyaltyController;
use App\Modules\CRM\Controllers\LoyaltySettingsController;
use App\Modules\CRM\Controllers\WalletController;
use App\Modules\OMS\Controllers\AutomationController;
use App\Modules\OMS\Controllers\CourierController;
use App\Modules\OMS\Controllers\EcommerceController;
use App\Modules\OMS\Controllers\NotificationController;
use App\Modules\OMS\Controllers\OrderController;
use App\Modules\Platform\Controllers\SystemHealthController;
use App\Modules\SystemOps\Controllers\SystemOpsController;
use App\Modules\SystemOps\Controllers\BackupController;
use App\Modules\SystemOps\Controllers\SyncController;
use App\Modules\SystemOps\Controllers\OfflineSyncController;
use App\Modules\NotificationCenter\Controllers\NotificationCenterController;
use App\Modules\HRM\Controllers\AttendanceController as HrmAttendanceController;
use App\Modules\HRM\Controllers\DepartmentController as HrmDepartmentController;
use App\Modules\HRM\Controllers\HrmReportsController;
use App\Modules\HRM\Controllers\PayrollPeriodController as HrmPayrollPeriodController;
use App\Modules\HRM\Controllers\PayslipController as HrmPayslipController;
use App\Modules\HRM\Controllers\AttendanceIntelligenceController as HrmAttendanceIntelController;
use App\Modules\HRM\Controllers\HrAuditController as HrmHrAuditController;
use App\Modules\HRM\Controllers\PayrollApprovalController as HrmPayrollApprovalController;
use App\Modules\HRM\Controllers\SalaryAdvanceController as HrmSalaryAdvanceController;
use App\Modules\HRM\Controllers\WorkforceAnalyticsController as HrmWorkforceAnalyticsController;
use App\Modules\HRM\Controllers\DesignationController as HrmDesignationController;
use App\Modules\HRM\Controllers\EmployeeController as HrmEmployeeController;
use App\Modules\HRM\Controllers\ShiftController as HrmShiftController;
use App\Modules\Reports\Controllers\LedgerController;
use App\Modules\Sales\Controllers\CustomerController;
use App\Modules\Sales\Controllers\CustomerPaymentController;
use App\Modules\Sales\Controllers\QuotationController;
use App\Modules\Sales\Controllers\SaleController;
use App\Modules\Sales\Controllers\SaleReturnController;
use App\Modules\Stock\Controllers\StockController;
use App\Modules\Product\Controllers\CategoryController;
use App\Modules\Product\Controllers\ProductController;
use App\Modules\Product\Controllers\UnitController;
use App\Modules\Purchase\Controllers\PurchaseController;
use App\Modules\Purchase\Controllers\SupplierController;
use App\Modules\RolePermission\Controllers\RolePermissionController;
use App\Modules\Settings\Controllers\SettingsController;
use App\Modules\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — POSmeister
|--------------------------------------------------------------------------
| Prefix:     /api
| Auth guard: sanctum
*/

// ── Public ────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// Public liveness probe for load balancers / uptime monitors.
Route::get('system/ping', [SystemHealthController::class, 'ping'])->middleware('throttle:60,1');

// ── Protected ─────────────────────────────────────────────────────────────
// `branch.current` runs BEFORE the legacy `branch` middleware so the
// X-Branch-Id header (sent by the SPA) wins over the query-string fallback
// already baked into BranchScopeMiddleware. Both push onto the same
// container key `pos.activeBranchId`, so downstream `BranchScoped` traits
// don't need to know which one set it.
Route::middleware(['auth:sanctum', 'branch.current', 'branch'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me',      [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // ── Branch context (Topbar workspace switcher) ──────────────────────
    Route::get ('branch-context/current',   [\App\Modules\Branch\Controllers\BranchContextController::class, 'current']);
    Route::get ('branch-context/available', [\App\Modules\Branch\Controllers\BranchContextController::class, 'available']);
    Route::post('branch-context/switch',    [\App\Modules\Branch\Controllers\BranchContextController::class, 'switch']);

    // Dashboard stats
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // ── Phase Ω+ — Smart Notification Center (any auth user) ────────────
    Route::get ('notifications',                   [NotificationCenterController::class, 'index']);
    Route::get ('notifications/digest',            [NotificationCenterController::class, 'digest']);
    Route::get ('notifications/preferences',       [NotificationCenterController::class, 'preferences']);
    Route::put ('notifications/preferences',       [NotificationCenterController::class, 'savePreferences']);
    Route::post('notifications/mark-all-read',     [NotificationCenterController::class, 'markAllRead']);
    Route::post('notifications/clear-read',        [NotificationCenterController::class, 'clearRead']);
    Route::post('notifications/clear-all',         [NotificationCenterController::class, 'clearAll']);
    Route::post('notifications/{notification}/read',    [NotificationCenterController::class, 'markRead']);
    Route::post('notifications/{notification}/ack',     [NotificationCenterController::class, 'ack']);
    Route::post('notifications/{notification}/archive', [NotificationCenterController::class, 'archive']);
    Route::middleware('role:admin')->group(function () {
        Route::get ('notifications/analytics',         [NotificationCenterController::class, 'analytics']);
        Route::post('notifications/detect',            [NotificationCenterController::class, 'runDetectors']);
        Route::post('notifications/build-digest',      [NotificationCenterController::class, 'buildDigest']);
    });

    // ── Phase Ω — Offline-first POS ─────────────────────────────────────
    //   /snapshot     bulk download of products+customers+settings (any auth user)
    //   /sync/sales   batch import of offline sales (any auth user)
    //   /devices      admin monitoring of registered terminals
    //   /sync/conflicts admin: open conflicts inspector
    Route::get ('system/snapshot',         [OfflineSyncController::class, 'snapshot']);
    Route::post('system/sync/sales',       [OfflineSyncController::class, 'syncSales'])->middleware('throttle:30,1');
    Route::middleware('role:admin')->group(function () {
        Route::get('system/devices',          [OfflineSyncController::class, 'devices']);
        Route::get('system/sync/conflicts',   [OfflineSyncController::class, 'conflicts']);
    });

    // Ledger reports
    Route::get('reports/customer-ledger', [LedgerController::class, 'customer']);
    Route::get('reports/supplier-ledger', [LedgerController::class, 'supplier']);
    Route::get('reports/product-ledger',  [LedgerController::class, 'product']);

    // HRM (admin + manager only)
    Route::middleware('role:admin,manager')->prefix('hrm')->group(function () {
        Route::get('departments/all',                  [HrmDepartmentController::class, 'all']);
        Route::get('departments',                      [HrmDepartmentController::class, 'index']);
        Route::post('departments',                     [HrmDepartmentController::class, 'store']);
        Route::put('departments/{department}',         [HrmDepartmentController::class, 'update']);
        Route::put('departments/{department}/status',  [HrmDepartmentController::class, 'toggleStatus']);
        Route::delete('departments/{department}',      [HrmDepartmentController::class, 'destroy']);

        Route::get('designations/all',                 [HrmDesignationController::class, 'all']);
        Route::get('designations',                     [HrmDesignationController::class, 'index']);
        Route::post('designations',                    [HrmDesignationController::class, 'store']);
        Route::put('designations/{designation}',       [HrmDesignationController::class, 'update']);
        Route::put('designations/{designation}/status',[HrmDesignationController::class, 'toggleStatus']);
        Route::delete('designations/{designation}',    [HrmDesignationController::class, 'destroy']);

        Route::get('shifts/all',                       [HrmShiftController::class, 'all']);
        Route::get('shifts',                           [HrmShiftController::class, 'index']);
        Route::post('shifts',                          [HrmShiftController::class, 'store']);
        Route::put('shifts/{shift}',                   [HrmShiftController::class, 'update']);
        Route::put('shifts/{shift}/status',            [HrmShiftController::class, 'toggleStatus']);
        Route::delete('shifts/{shift}',                [HrmShiftController::class, 'destroy']);

        // Attendance
        Route::get('attendance/daily',                [HrmAttendanceController::class, 'daily']);
        Route::get('attendance/monthly',              [HrmAttendanceController::class, 'monthly']);
        Route::post('attendance/bulk',                [HrmAttendanceController::class, 'bulkMark']);
        Route::delete('attendance/{attendance}',      [HrmAttendanceController::class, 'destroy']);

        // Payroll
        Route::get('payroll-periods',                       [HrmPayrollPeriodController::class, 'index']);
        Route::post('payroll-periods',                      [HrmPayrollPeriodController::class, 'store']);
        Route::get('payroll-periods/{period}',              [HrmPayrollPeriodController::class, 'show']);
        Route::put('payroll-periods/{period}',              [HrmPayrollPeriodController::class, 'update']);
        Route::delete('payroll-periods/{period}',           [HrmPayrollPeriodController::class, 'destroy']);
        Route::post('payroll-periods/{period}/generate',    [HrmPayrollPeriodController::class, 'generate']);
        Route::post('payroll-periods/{period}/finalize',    [HrmPayrollPeriodController::class, 'finalize']);

        Route::get('payslips',                              [HrmPayslipController::class, 'index']);
        Route::get('payslips/{payslip}',                    [HrmPayslipController::class, 'show']);
        Route::put('payslips/{payslip}',                    [HrmPayslipController::class, 'update']);
        Route::post('payslips/{payslip}/items',             [HrmPayslipController::class, 'addItem']);
        Route::delete('payslips/{payslip}/items/{itemId}',  [HrmPayslipController::class, 'removeItem']);
        Route::post('payslips/{payslip}/pay',               [HrmPayslipController::class, 'pay']);
        Route::delete('payslips/{payslip}',                 [HrmPayslipController::class, 'destroy']);

        // HR Reports
        Route::get('reports/dashboard',                     [HrmReportsController::class, 'dashboard']);
        Route::get('reports/attendance',                    [HrmReportsController::class, 'attendance']);
        Route::get('reports/payroll',                       [HrmReportsController::class, 'payroll']);

        // Payroll approval workflow
        Route::get('payroll-approvals/queue',               [HrmPayrollApprovalController::class, 'queue']);
        Route::get('payroll-approvals/counts',              [HrmPayrollApprovalController::class, 'counts']);
        Route::post('payslips/{payslip}/submit',            [HrmPayrollApprovalController::class, 'submit']);
        Route::post('payslips/{payslip}/approve',           [HrmPayrollApprovalController::class, 'approve']);
        Route::post('payslips/{payslip}/reject',            [HrmPayrollApprovalController::class, 'reject']);
        Route::post('payslips/{payslip}/reopen',            [HrmPayrollApprovalController::class, 'reopen']);

        // Salary advances
        Route::get('salary-advances',                       [HrmSalaryAdvanceController::class, 'index']);
        Route::post('salary-advances',                      [HrmSalaryAdvanceController::class, 'store']);
        Route::post('salary-advances/{advance}/cancel',     [HrmSalaryAdvanceController::class, 'cancel']);
        Route::get('employees/{employee}/outstanding-advance',
            [HrmSalaryAdvanceController::class, 'outstandingForEmployee']);

        // Workforce analytics
        Route::get('workforce/dashboard',                   [HrmWorkforceAnalyticsController::class, 'dashboard']);
        Route::get('workforce/branch-efficiency',           [HrmWorkforceAnalyticsController::class, 'branchEfficiency']);
        Route::get('workforce/utilisation',                 [HrmWorkforceAnalyticsController::class, 'utilisation']);

        // Attendance intelligence
        Route::get('attendance-intelligence/scores',        [HrmAttendanceIntelController::class, 'scores']);
        Route::get('attendance-intelligence/late-heatmap',  [HrmAttendanceIntelController::class, 'lateHeatmap']);
        Route::get('attendance-intelligence/overtime',      [HrmAttendanceIntelController::class, 'overtimeTrend']);
        Route::get('attendance-intelligence/breaks',        [HrmAttendanceIntelController::class, 'breaks']);
        Route::post('attendance/{attendance}/correct',      [HrmAttendanceIntelController::class, 'correct']);

        // HR audit log (read-only)
        Route::get('hr-audit',                              [HrmHrAuditController::class, 'index']);

        Route::get('employees/stats',                 [HrmEmployeeController::class, 'stats']);
        Route::get('employees',                       [HrmEmployeeController::class, 'index']);
        Route::post('employees',                      [HrmEmployeeController::class, 'store']);
        Route::get('employees/{employee}',            [HrmEmployeeController::class, 'show']);
        Route::put('employees/{employee}',            [HrmEmployeeController::class, 'update']);
        Route::put('employees/{employee}/status',     [HrmEmployeeController::class, 'setStatus']);
        Route::delete('employees/{employee}',         [HrmEmployeeController::class, 'destroy']);
        Route::post('employees/{employee}/photo',     [HrmEmployeeController::class, 'uploadPhoto']);
        Route::delete('employees/{employee}/photo',   [HrmEmployeeController::class, 'deletePhoto']);
    });

    // ── Admin-only ────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function ()
    {
        // Branches
        Route::get('branches/all',     [BranchController::class, 'all']);
        Route::apiResource('branches', BranchController::class);

        // Users
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::apiResource('users',       UserController::class);

        // Role permissions management
        Route::get('role-permissions',           [RolePermissionController::class, 'index']);
        Route::put('role-permissions/{role}',    [RolePermissionController::class, 'update']);
    });

    // ── Settings ──────────────────────────────────────────────────────────
    Route::get('settings', [SettingsController::class, 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::put('settings',              [SettingsController::class, 'update']);
        Route::post('settings/logo',        [SettingsController::class, 'uploadLogo']);
        Route::delete('settings/logo',      [SettingsController::class, 'deleteLogo']);

        // Platform health + audit (admin-only operations)
        Route::get('system/health',  [SystemHealthController::class, 'health']);
        Route::get('system/info',    [SystemHealthController::class, 'info']);
        Route::get('system/audit',   [SystemHealthController::class, 'audit']);

        // Phase Z — SystemOps: production diagnostics, backup, sync.
        Route::get ('system/dashboard',         [SystemOpsController::class, 'dashboard']);
        Route::get ('system/environment-check', [SystemOpsController::class, 'environment']);
        Route::get ('system/queue-status',      [SystemOpsController::class, 'queue']);
        Route::get ('system/scheduler-status',  [SystemOpsController::class, 'scheduler']);
        Route::get ('system/deployment',        [SystemOpsController::class, 'deployment']);
        Route::get ('system/version',           [SystemOpsController::class, 'version']);
        Route::get ('system/pwa/status',        [SystemOpsController::class, 'pwa']);

        Route::get ('system/backup/status',     [BackupController::class, 'status']);
        Route::post('system/backup/run',        [BackupController::class, 'run'])->middleware('throttle:6,1');
        Route::post('system/backup/prune',      [BackupController::class, 'prune']);

        Route::get ('system/sync/pending',      [SyncController::class, 'pending']);
        Route::post('system/sync/prune',        [SyncController::class, 'prune']);
    });

    // Expense module (admin + manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('expense-categories/all',                  [ExpenseCategoryController::class, 'all']);
        Route::get('expense-categories',                      [ExpenseCategoryController::class, 'index']);
        Route::post('expense-categories',                     [ExpenseCategoryController::class, 'store']);
        Route::put('expense-categories/{category}',           [ExpenseCategoryController::class, 'update']);
        Route::put('expense-categories/{category}/status',    [ExpenseCategoryController::class, 'toggleStatus']);
        Route::delete('expense-categories/{category}',        [ExpenseCategoryController::class, 'destroy']);

        Route::get('expenses/summary',                        [ExpenseModuleController::class, 'summary']);
        Route::get('expenses/export.csv',                     [ExpenseModuleController::class, 'exportCsv']);
        Route::get('expenses',                                [ExpenseModuleController::class, 'index']);
        Route::post('expenses',                               [ExpenseModuleController::class, 'store']);
        Route::get('expenses/{expense}',                      [ExpenseModuleController::class, 'show']);
        Route::put('expenses/{expense}',                      [ExpenseModuleController::class, 'update']);
        Route::delete('expenses/{expense}',                   [ExpenseModuleController::class, 'destroy']);

        Route::post('expenses/{expense}/approve',             [ExpenseModuleController::class, 'approve']);
        Route::post('expenses/{expense}/reject',              [ExpenseModuleController::class, 'reject']);
        Route::post('expenses/{expense}/mark-paid',           [ExpenseModuleController::class, 'markPaid']);
        Route::post('expenses/{expense}/reopen',              [ExpenseModuleController::class, 'reopen']);
        Route::get('expenses/{expense}/audit-log',            [ExpenseModuleController::class, 'auditLog']);

        // Expense reports
        Route::get('expense-reports/dashboard',               [ExpenseReportsController::class, 'dashboard']);
        Route::get('expense-reports/category-breakdown',      [ExpenseReportsController::class, 'categoryBreakdown']);
        Route::get('expense-reports/monthly-trend',           [ExpenseReportsController::class, 'monthlyTrend']);
        Route::get('expense-reports/branch-breakdown',        [ExpenseReportsController::class, 'branchBreakdown']);
    });

    // Finance module (admin + manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('budgets',                          [BudgetController::class, 'index']);
        Route::post('budgets',                         [BudgetController::class, 'store']);
        Route::get('budgets/{budget}',                 [BudgetController::class, 'show']);
        Route::put('budgets/{budget}',                 [BudgetController::class, 'update']);
        Route::put('budgets/{budget}/status',          [BudgetController::class, 'setStatus']);
        Route::post('budgets/{budget}/duplicate',      [BudgetController::class, 'duplicate']);
        Route::delete('budgets/{budget}',              [BudgetController::class, 'destroy']);

        Route::get('budgets/{budget}/analytics',       [BudgetAnalyticsController::class, 'show']);

        Route::get('cashflow/dashboard',               [CashflowController::class, 'dashboard']);
        Route::get('cashflow/forecast',                [CashflowController::class, 'forecast']);

        Route::get('finance/alerts',                   [FinancialAlertController::class, 'index']);
        Route::get('finance/calendar',                 [FinancialCalendarController::class, 'month']);

        Route::get('finance/dashboard',                [FinancialDashboardController::class, 'dashboard']);
        Route::get('finance/sales-trend',              [FinancialDashboardController::class, 'salesTrend']);
        Route::get('finance/profit-analysis',          [FinancialDashboardController::class, 'profitAnalysis']);
        Route::get('finance/branch-performance',       [FinancialDashboardController::class, 'branchPerformance']);
        Route::get('finance/top-products',             [FinancialDashboardController::class, 'topProducts']);
        Route::get('finance/top-customers',            [FinancialDashboardController::class, 'topCustomers']);
        Route::get('finance/expense-breakdown',        [FinancialDashboardController::class, 'expenseBreakdown']);
        Route::get('finance/inventory-insights',       [FinancialDashboardController::class, 'inventoryInsights']);
    });

    // Accounting module (admin + manager). Read-only operations open to managers,
    // write operations gated further in controllers where it matters.
    Route::middleware('role:admin,manager')->prefix('accounting')->group(function () {
        // Chart of accounts
        Route::get('coa',                  [ChartOfAccountController::class, 'index']);
        Route::post('coa',                 [ChartOfAccountController::class, 'store']);
        Route::get('coa/{account}',        [ChartOfAccountController::class, 'show']);
        Route::put('coa/{account}',        [ChartOfAccountController::class, 'update']);
        Route::delete('coa/{account}',     [ChartOfAccountController::class, 'destroy']);

        // Journal entries
        Route::get('journal',              [JournalEntryController::class, 'index']);
        Route::post('journal',             [JournalEntryController::class, 'store']);
        Route::get('journal/{entry}',      [JournalEntryController::class, 'show']);
        Route::post('journal/{entry}/reverse', [JournalEntryController::class, 'reverse']);
        Route::delete('journal/{entry}',   [JournalEntryController::class, 'destroy']);

        // Reports
        Route::get('dashboard',                [AccountingReportController::class, 'dashboard']);
        Route::get('ledger/{accountId}',       [AccountingReportController::class, 'ledger']);
        Route::get('trial-balance',            [AccountingReportController::class, 'trialBalance']);
        Route::get('profit-loss',              [AccountingReportController::class, 'profitLoss']);
        Route::get('balance-sheet',            [AccountingReportController::class, 'balanceSheet']);
        Route::get('cashbook/{accountId}',     [AccountingReportController::class, 'cashbook']);

        // Bank accounts
        Route::get('banks',                [BankAccountController::class, 'index']);
        Route::post('banks',               [BankAccountController::class, 'store']);
        Route::put('banks/{bank}',         [BankAccountController::class, 'update']);
        Route::delete('banks/{bank}',      [BankAccountController::class, 'destroy']);

        // Cashbook registry (per-branch cash registers)
        Route::get('cashbooks',                  [CashbookController::class, 'index']);
        Route::post('cashbooks',                 [CashbookController::class, 'store']);
        Route::put('cashbooks/{cashbook}',       [CashbookController::class, 'update']);
        Route::delete('cashbooks/{cashbook}',    [CashbookController::class, 'destroy']);
    });

    // Inventory Intelligence + Procurement (admin + manager)
    Route::middleware('role:admin,manager')->group(function () {

        Route::prefix('inventory-intelligence')->group(function () {
            Route::get('dashboard',     [InventoryIntelligenceController::class, 'dashboard']);
            Route::get('movement',      [InventoryIntelligenceController::class, 'movement']);
            Route::get('dead-stock',    [InventoryIntelligenceController::class, 'deadStock']);
            Route::get('aging',         [InventoryIntelligenceController::class, 'aging']);
            Route::get('branch-health', [InventoryIntelligenceController::class, 'branchHealth']);
        });

        Route::prefix('procurement')->group(function () {
            Route::get('suggestions',             [ProcurementController::class, 'suggestions']);
            Route::get('suggestions-by-supplier', [ProcurementController::class, 'suggestionsBySupplier']);
        });

        Route::prefix('inventory-reports')->group(function () {
            Route::get('valuation',     [InventoryAnalyticsController::class, 'valuation']);
            Route::get('profitability', [InventoryAnalyticsController::class, 'profitability']);
            Route::get('movement',      [InventoryAnalyticsController::class, 'movement']);
        });

        Route::prefix('supplier-analytics')->group(function () {
            Route::get('leaderboard',     [SupplierAnalyticsController::class, 'leaderboard']);
            Route::get('{supplierId}',    [SupplierAnalyticsController::class, 'show']);
        });
    });

    // CRM: Loyalty, Wallets, Customer Intelligence, Campaigns
    Route::middleware('role:admin,manager')->group(function () {

        // Customer intelligence + segments + per-customer profile
        Route::prefix('customer-intelligence')->group(function () {
            Route::get('dashboard',                  [CustomerIntelligenceController::class, 'dashboard']);
            Route::get('segments',                   [CustomerIntelligenceController::class, 'segments']);
            Route::get('segments/{name}',            [CustomerIntelligenceController::class, 'segmentList']);
            Route::get('customers/{customer}/profile',  [CustomerIntelligenceController::class, 'profile']);
            Route::get('customers/{customer}/behavior', [CustomerIntelligenceController::class, 'behavior']);
        });

        // Loyalty programme
        Route::prefix('loyalty')->group(function () {
            Route::get('settings',                              [LoyaltySettingsController::class, 'show']);
            Route::put('settings',                              [LoyaltySettingsController::class, 'update']);
            Route::get('customers/{customer}/summary',          [LoyaltyController::class, 'summary']);
            Route::get('customers/{customer}/transactions',     [LoyaltyController::class, 'transactions']);
            Route::post('customers/{customer}/adjust',          [LoyaltyController::class, 'adjust']);
            Route::post('customers/{customer}/redeem',          [LoyaltyController::class, 'redeem']);
        });

        // Customer wallets
        Route::prefix('customer-wallets')->group(function () {
            Route::get('recent',                                [WalletController::class, 'recentAll']);
            Route::get('customers/{customer}/summary',          [WalletController::class, 'summary']);
            Route::put('customers/{customer}/settings',         [WalletController::class, 'settings']);
            Route::get('customers/{customer}/transactions',     [WalletController::class, 'transactions']);
            Route::post('customers/{customer}/credit',          [WalletController::class, 'credit']);
            Route::post('customers/{customer}/debit',           [WalletController::class, 'debit']);
            Route::post('customers/{customer}/adjust',          [WalletController::class, 'adjust']);
        });

        // Campaign foundation (channels not yet wired — queue-ready only)
        Route::prefix('crm/campaigns')->group(function () {
            Route::get('/',                       [CampaignController::class, 'index']);
            Route::post('/',                      [CampaignController::class, 'store']);
            Route::put('{campaign}',              [CampaignController::class, 'update']);
            Route::post('{campaign}/schedule',    [CampaignController::class, 'schedule']);
            Route::post('{campaign}/queue',       [CampaignController::class, 'queueDispatch']);
            Route::post('{campaign}/cancel',      [CampaignController::class, 'cancel']);
            Route::get('{campaign}/preview',      [CampaignController::class, 'preview']);
        });
    });

    // OMS, couriers, notifications, automation, ecommerce sync
    Route::middleware('role:admin,manager')->group(function () {

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('dashboard',                [OrderController::class, 'dashboard']);
            Route::get('/',                        [OrderController::class, 'index']);
            Route::post('/',                       [OrderController::class, 'store']);
            Route::get('{order}',                  [OrderController::class, 'show']);
            Route::post('{order}/transition',      [OrderController::class, 'transition']);
            Route::post('{order}/fulfil',          [OrderController::class, 'fulfilPartial']);
            Route::post('{order}/payment',         [OrderController::class, 'markPaid']);
        });

        // Couriers + shipments
        Route::prefix('couriers')->group(function () {
            Route::get('/',                        [CourierController::class, 'index']);
            Route::post('/',                       [CourierController::class, 'store']);
            Route::put('{courier}',                [CourierController::class, 'update']);
            Route::delete('{courier}',             [CourierController::class, 'destroy']);
        });
        Route::prefix('shipments')->group(function () {
            Route::get('/',                                [CourierController::class, 'shipments']);
            Route::post('orders/{order}/couriers/{courier}', [CourierController::class, 'ship']);
            Route::post('{shipment}/refresh',              [CourierController::class, 'refresh']);
            Route::post('{shipment}/cancel',               [CourierController::class, 'cancel']);
        });

        // OMS outbound notifications (customer SMS/Email/WhatsApp queue).
        // Moved under /oms/notifications to avoid colliding with the
        // Phase Ω+ internal smart-alert center at /notifications.
        Route::prefix('oms/notifications')->group(function () {
            Route::get('/',                        [NotificationController::class, 'index']);
            Route::post('/',                       [NotificationController::class, 'store']);
            Route::post('{notification}/read',     [NotificationController::class, 'markRead']);
            Route::get('unread-count',             [NotificationController::class, 'unreadCount']);
        });
        Route::prefix('oms/notification-templates')->group(function () {
            Route::get('/',                        [NotificationController::class, 'templates']);
            Route::post('/',                       [NotificationController::class, 'saveTemplate']);
            Route::put('{template}',               [NotificationController::class, 'saveTemplate']);
            Route::delete('{template}',            [NotificationController::class, 'deleteTemplate']);
        });

        // Automation
        Route::prefix('automation')->group(function () {
            Route::get('rules',                    [AutomationController::class, 'index']);
            Route::post('rules',                   [AutomationController::class, 'store']);
            Route::get('rules/{rule}',             [AutomationController::class, 'show']);
            Route::put('rules/{rule}',             [AutomationController::class, 'update']);
            Route::delete('rules/{rule}',          [AutomationController::class, 'destroy']);
            Route::post('rules/{rule}/run',        [AutomationController::class, 'run']);
            Route::post('run-all',                 [AutomationController::class, 'runAll']);
            Route::get('logs',                     [AutomationController::class, 'logs']);
        });

        // E-commerce sync
        Route::prefix('ecommerce')->group(function () {
            Route::get('connectors',                   [EcommerceController::class, 'connectors']);
            Route::post('connectors',                  [EcommerceController::class, 'storeConnector']);
            Route::put('connectors/{connector}',       [EcommerceController::class, 'updateConnector']);
            Route::delete('connectors/{connector}',    [EcommerceController::class, 'destroyConnector']);
            Route::post('connectors/{connector}/sync', [EcommerceController::class, 'startSync']);
            Route::get('jobs',                         [EcommerceController::class, 'jobs']);
        });
    });

    // ── Product Module ────────────────────────────────────────────────────
    // Units — read by all authenticated users (needed in POS terminal)
    Route::get('units',     [UnitController::class, 'index']);
    Route::get('units/all', [UnitController::class, 'all']);

    // Categories & Brands — read by all, write by admin+manager
    Route::get('categories',     [CategoryController::class, 'index']);
    Route::get('categories/all', [CategoryController::class, 'all']);
    Route::get('brands',         [BrandController::class, 'index']);
    Route::get('brands/all',     [BrandController::class, 'all']);

    // Products — read by all, write by admin+manager
    Route::get('products/all',              [ProductController::class, 'all']);
    Route::get('products/search',           [ProductController::class, 'search']);
    Route::get('products',                  [ProductController::class, 'index']);
    Route::get('products/{product}/barcode',[ProductController::class, 'barcodeData']); // before {product}
    Route::get('products/{product}',        [ProductController::class, 'show']);

    Route::middleware('role:admin,manager')->group(function () {
        // Categories
        Route::post('categories',              [CategoryController::class, 'store']);
        Route::put('categories/{category}',    [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // Brands
        Route::post('brands',          [BrandController::class, 'store']);
        Route::put('brands/{brand}',   [BrandController::class, 'update']);
        Route::delete('brands/{brand}',[BrandController::class, 'destroy']);

        // Units
        Route::post('units',         [UnitController::class, 'store']);
        Route::put('units/{unit}',   [UnitController::class, 'update']);
        Route::delete('units/{unit}',[UnitController::class, 'destroy']);

        // Products
        Route::post('products',                        [ProductController::class, 'store']);
        Route::put('products/{product}',               [ProductController::class, 'update']);
        Route::put('products/{product}/status',        [ProductController::class, 'toggleStatus']);
        Route::delete('products/{product}',            [ProductController::class, 'destroy']);
        Route::post('products/{product}/image',        [ProductController::class, 'uploadImage']);
        Route::delete('products/{product}/image',      [ProductController::class, 'deleteImage']);
    });

    // ── Phase Y — Serial / IMEI / Warranty tracking ─────────────────────
    // Read endpoints are open to any authenticated user (cashiers need
    // to see the available-for-sale list at POS). Write endpoints stay
    // open here because they're always called from inside an already-
    // permission-gated workflow (PurchaseService::receive, SaleService
    // ::confirm, etc.) and additional guards live in those services.
    Route::get   ('products/{product}/serials',                 [\App\Modules\Serials\Controllers\SerialController::class, 'indexForProduct']);
    Route::get   ('products/{product}/serials/available',       [\App\Modules\Serials\Controllers\SerialController::class, 'availableForSale']);
    Route::get   ('products/{product}/serials/in-stock-count',  [\App\Modules\Serials\Controllers\SerialController::class, 'inStockCount']);
    Route::get   ('serials/warranty-expiring',                  [\App\Modules\Serials\Controllers\SerialController::class, 'warrantyExpiringSoon']);
    Route::get   ('serials/{serial}',                           [\App\Modules\Serials\Controllers\SerialController::class, 'show']);
    Route::post  ('serials/attach-purchase',                    [\App\Modules\Serials\Controllers\SerialController::class, 'attachToPurchase']);
    Route::post  ('serials/attach-sale',                        [\App\Modules\Serials\Controllers\SerialController::class, 'attachToSale']);
    Route::get   ('customers/{customer}/owned-devices',         [\App\Modules\Serials\Controllers\SerialController::class, 'ownedByCustomer']);

    // ── Sales / POS ──────────────────────────────────────────────────────
    // POS product search (all authenticated users)
    Route::get('pos/products',            [SaleController::class, 'posSearch']);

    // Customers — read by all, write by admin+manager+cashier
    Route::get('customers/all',                              [CustomerController::class, 'all']);
    Route::get('customers/due-report',                       [CustomerController::class, 'dueReport']); // must be before {customer}
    Route::get('customers',                                  [CustomerController::class, 'index']);
    Route::get('customers/{customer}',                       [CustomerController::class, 'show']);
    Route::post('customers',                                 [CustomerController::class, 'store']);
    Route::put('customers/{customer}',                       [CustomerController::class, 'update']);
    Route::get('customers/{customer}/payments',              [CustomerController::class, 'payments']);
    Route::post('customers/{customer}/payments',             [CustomerController::class, 'storePayment']);

    // ── Customer Payments (standalone — global list) ──────────────────────
    Route::get('customer-payments',        [CustomerPaymentController::class, 'index']);
    Route::post('customer-payments',       [CustomerPaymentController::class, 'store']);
    Route::get('customer-payments/{id}',   [CustomerPaymentController::class, 'show']);

    // ── Quotations / Angebote ─────────────────────────────────────────────
    Route::get('quotations',                            [QuotationController::class, 'index']);
    Route::get('quotations/{quotation}',                [QuotationController::class, 'show']);
    Route::post('quotations',                           [QuotationController::class, 'store']);
    Route::put('quotations/{quotation}',                [QuotationController::class, 'update']);
    Route::put('quotations/{quotation}/status',         [QuotationController::class, 'updateStatus']);
    Route::delete('quotations/{quotation}',             [QuotationController::class, 'destroy']);

    // Sales — list/show by all, create by cashier+, cancel by manager+
    Route::get('sales/record',            [SaleController::class, 'record']); // must be before {sale}
    Route::get('sales',                   [SaleController::class, 'index']);
    Route::get('sales/{sale}',            [SaleController::class, 'show']);
    Route::post('sales',                  [SaleController::class, 'store']);
    Route::put('sales/{sale}/cancel',     [SaleController::class, 'cancel']);

    // ── Stock / Inventory ─────────────────────────────────────────────────
    Route::get('stock/filter-options', [StockController::class, 'filterOptions']);
    Route::get('stock/current',        [StockController::class, 'current']);

    // ── Purchase Module ───────────────────────────────────────────────────
    // Suppliers — read by all authenticated, write by admin+manager
    Route::get('suppliers/all',        [SupplierController::class, 'all']);
    Route::get('suppliers/due-report', [SupplierController::class, 'dueReport']); // must be before {supplier}
    Route::get('suppliers',            [SupplierController::class, 'index']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('suppliers',                         [SupplierController::class, 'store']);
        Route::put('suppliers/{supplier}',               [SupplierController::class, 'update']);
        Route::put('suppliers/{supplier}/status',        [SupplierController::class, 'toggleStatus']);
        Route::delete('suppliers/{supplier}',            [SupplierController::class, 'destroy']);

        // Purchases
        Route::get('purchases',                       [PurchaseController::class, 'index']);
        Route::get('purchases/record',                [PurchaseController::class, 'record']); // must be before {purchase}
        Route::get('purchases/{purchase}',            [PurchaseController::class, 'show']);
        Route::post('purchases',                      [PurchaseController::class, 'store']);
        Route::put('purchases/{purchase}',            [PurchaseController::class, 'update']);
        Route::put('purchases/{purchase}/receive',    [PurchaseController::class, 'receive']);
        Route::delete('purchases/{purchase}',         [PurchaseController::class, 'destroy']);

        // ── Supplier Payments ─────────────────────────────────────────────
        Route::get('supplier-payments',        [SupplierPaymentController::class, 'index']);
        Route::post('supplier-payments',       [SupplierPaymentController::class, 'store']);
        Route::get('supplier-payments/{id}',   [SupplierPaymentController::class, 'show']);

        // Purchase Returns
        Route::get('purchase-returns/record',                         [PurchaseReturnController::class, 'record']); // must be before {id}
        Route::get('purchase-returns',                                [PurchaseReturnController::class, 'index']);
        Route::get('purchase-returns/{id}',                          [PurchaseReturnController::class, 'show']);
        Route::get('purchases/{purchaseId}/return-details',           [PurchaseReturnController::class, 'returnDetails']);
        Route::post('purchase-returns',                               [PurchaseReturnController::class, 'store']);

        // Sale Returns
        Route::get('sale-returns/record',                             [SaleReturnController::class, 'record']); // must be before {id}
        Route::get('sale-returns',                                    [SaleReturnController::class, 'index']);
        Route::get('sale-returns/{id}',                              [SaleReturnController::class, 'show']);
        Route::get('sales/{saleId}/return-details',                   [SaleReturnController::class, 'returnDetails']);
        Route::post('sale-returns',                                   [SaleReturnController::class, 'store']);
    });
});
