<template>
    <aside
        :class="[
            'sidebar-shell flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out',
            collapsed ? 'w-16' : 'w-64',
        ]"
    >
        <!-- Logo / Brand -->
        <div class="brand-bar flex h-16 items-center px-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="brand-mark flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-xl overflow-hidden">
                    <img
                        v-if="settingsStore.settings?.logo_url"
                        :src="settingsStore.settings.logo_url"
                        alt="logo"
                        class="w-full h-full object-contain p-0.5"
                    />
                    <svg v-else class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <span class="block text-white font-bold text-sm tracking-tight truncate">
                        {{ settingsStore.settings?.company_name ?? 'POSmeister' }}
                    </span>
                    <span class="block text-slate-500 text-[11px] tracking-wide">{{ t('app.management') }}</span>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-0.5">

            <!-- Dashboard — always visible -->
            <NavItem :collapsed="collapsed" :to="{ name: 'dashboard' }" :label="t('menu.dashboard')">
                <template #icon><Squares2X2Icon class="nav-icon" /></template>
            </NavItem>

            <!-- Permission-based section groups -->
            <template v-for="group in visibleGroups" :key="group.sectionKey">
                <SidebarSectionLabel v-if="!collapsed && group.items.length" :label="t(group.sectionKey)" />

                <template v-for="item in group.items" :key="item.labelKey">

                    <!-- ── Accordion group (e.g. Verkauf, Einkauf) ── -->
                    <NavGroup
                        v-if="item.isGroup"
                        :collapsed="collapsed"
                        :label="t(item.labelKey)"
                        :child-routes="item.childRoutes"
                    >
                        <template #icon>
                            <component :is="item.icon" class="nav-icon" />
                        </template>
                        <NavItem
                            v-for="child in item.children"
                            :key="child.labelKey"
                            :collapsed="collapsed"
                            :to="child.to"
                            :label="t(child.labelKey)"
                            :disabled="!child.implemented"
                        />
                    </NavGroup>

                    <!-- ── Regular nav item ── -->
                    <NavItem
                        v-else
                        :collapsed="collapsed"
                        :to="item.to"
                        :label="t(item.labelKey)"
                        :disabled="!item.implemented"
                    >
                        <template #icon>
                            <component :is="item.icon" class="nav-icon" />
                        </template>
                    </NavItem>

                </template>
            </template>

        </nav>

        <!-- Footer: user info + logout -->
        <div class="user-card px-3 py-3">
            <div class="flex items-center gap-3" :class="{ 'justify-center': collapsed }">
                <div class="user-avatar flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold">
                    {{ userInitial }}
                </div>
                <div v-if="!collapsed" class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-100 truncate">{{ auth.userName }}</p>
                    <p class="text-[11px] text-slate-500 capitalize truncate">{{ auth.userRole }}</p>
                </div>
                <button
                    v-if="!collapsed"
                    @click="handleLogout"
                    class="logout-btn flex-shrink-0 p-1.5 rounded-lg transition-colors"
                    :title="t('auth.signOut')"
                >
                    <ArrowRightStartOnRectangleIcon class="w-4 h-4" />
                </button>
            </div>
        </div>

    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore }     from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';

import {
    Squares2X2Icon, ShoppingCartIcon, DocumentTextIcon, TruckIcon,
    ClipboardDocumentListIcon, TagIcon, ArchiveBoxIcon, UsersIcon,
    BuildingStorefrontIcon, BanknotesIcon, UserGroupIcon, ChartBarIcon, ReceiptPercentIcon,
    CogIcon, ArrowRightStartOnRectangleIcon, BuildingOffice2Icon,
    UserCircleIcon, ShieldCheckIcon, BookOpenIcon,
} from '@heroicons/vue/24/outline';

import NavItem             from './NavItem.vue';
import NavGroup            from './NavGroup.vue';
import SidebarSectionLabel from './SidebarSectionLabel.vue';

defineProps({
    collapsed: { type: Boolean, default: false },
});

const { t }         = useI18n();
const auth          = useAuthStore();
const settingsStore = useSettingsStore();
const router        = useRouter();

const userInitial = computed(() =>
    auth.userName ? auth.userName.charAt(0).toUpperCase() : '?'
);

// ── Nav config ─────────────────────────────────────────────────────────────
// isGroup:true items render as collapsible accordion parents (NavGroup)
// Regular items render as flat NavItem links

const NAV_GROUPS = [
    {
        sectionKey: 'menu.sections.commerce',
        items: [
            {
                permKey: 'pos',
                labelKey: 'menu.pointOfSale',
                to:   { name: 'pos' },
                icon: ShoppingCartIcon,
                implemented: true,
            },
            {
                isGroup:     true,
                permKey:     'sales',
                labelKey:    'menu.sales',
                icon:        DocumentTextIcon,
                childRoutes: ['pos', 'sale-create', 'sales', 'sale-returns', 'sale-record', 'sale-return-record'],
                children: [
                    { labelKey: 'menu.cashTerminal',     to: { name: 'pos' },                implemented: true },
                    { labelKey: 'menu.newSale',          to: { name: 'sale-create' },        implemented: true },
                    { labelKey: 'menu.salesList',        to: { name: 'sales' },              implemented: true },
                    { labelKey: 'menu.saleReturns',      to: { name: 'sale-returns' },       implemented: true },
                    { labelKey: 'menu.saleRecord',       to: { name: 'sale-record' },        implemented: true },
                    { labelKey: 'menu.saleReturnRecord', to: { name: 'sale-return-record' }, implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'purchases',
                labelKey:    'menu.purchases',
                icon:        TruckIcon,
                childRoutes: ['purchases', 'purchase-returns', 'purchase-create', 'purchase-edit', 'purchase-record', 'purchase-return-record'],
                children: [
                    { labelKey: 'menu.newPurchase',         to: { name: 'purchase-create' },        implemented: true },
                    { labelKey: 'menu.purchaseList',        to: { name: 'purchases' },              implemented: true },
                    { labelKey: 'menu.purchaseReturns',     to: { name: 'purchase-returns' },       implemented: true },
                    { labelKey: 'menu.purchaseRecord',      to: { name: 'purchase-record' },        implemented: true },
                    { labelKey: 'menu.purchaseReturnRecord', to: { name: 'purchase-return-record' }, implemented: true },
                ],
            },
            {
                permKey: 'sales',
                labelKey: 'menu.quotations',
                to:   { name: 'quotations' },
                icon: ClipboardDocumentListIcon,
                implemented: true,
            },
        ],
    },
    {
        sectionKey: 'menu.sections.catalogue',
        items: [
            { permKey: 'products',  labelKey: 'menu.products',        to: { name: 'products' },         icon: TagIcon,        implemented: true  },
            { permKey: 'products',  labelKey: 'menu.productSettings', to: { name: 'product-settings' }, icon: CogIcon,        implemented: true  },
            {
                isGroup:     true,
                permKey:     'inventory',
                labelKey:    'menu.inventory',
                icon:        ArchiveBoxIcon,
                childRoutes: [
                    'inventory', 'inventory-intelligence', 'inventory-reorder',
                    'inventory-dead-stock', 'inventory-analytics',
                ],
                children: [
                    { labelKey: 'menu.stockOverview',          to: { name: 'inventory' },              implemented: true },
                    { labelKey: 'menu.inventoryIntelligence', to: { name: 'inventory-intelligence' }, implemented: true },
                    { labelKey: 'menu.reorderSuggestions',    to: { name: 'inventory-reorder' },      implemented: true },
                    { labelKey: 'menu.deadStock',             to: { name: 'inventory-dead-stock' },   implemented: true },
                    { labelKey: 'menu.inventoryAnalytics',    to: { name: 'inventory-analytics' },    implemented: true },
                ],
            },
        ],
    },
    {
        sectionKey: 'menu.sections.stakeholders',
        items: [
            {
                isGroup:     true,
                permKey:     'customers',
                labelKey:    'menu.customers',
                icon:        UsersIcon,
                childRoutes: ['customers', 'customer-payments', 'customer-due'],
                children: [
                    { labelKey: 'menu.customerList',     to: { name: 'customers' },         implemented: true },
                    { labelKey: 'menu.customerPayments', to: { name: 'customer-payments' }, implemented: true },
                    { labelKey: 'menu.customerDue',      to: { name: 'customer-due' },      implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'suppliers',
                labelKey:    'menu.suppliers',
                icon:        BuildingStorefrontIcon,
                childRoutes: ['suppliers', 'supplier-payments', 'supplier-due'],
                children: [
                    { labelKey: 'menu.supplierList',     to: { name: 'suppliers' },         implemented: true },
                    { labelKey: 'menu.supplierPayments', to: { name: 'supplier-payments' }, implemented: true },
                    { labelKey: 'menu.supplierDue',      to: { name: 'supplier-due' },      implemented: true },
                ],
            },
        ],
    },
    {
        sectionKey: 'menu.sections.financeHr',
        items: [
            {
                isGroup:     true,
                permKey:     'finance',
                labelKey:    'menu.finance',
                icon:        BanknotesIcon,
                childRoutes: [
                    'finance-dashboard', 'finance-cashflow', 'finance-budgets', 'finance-budget-analytics', 'finance-calendar',
                ],
                children: [
                    { labelKey: 'menu.financialDashboard', to: { name: 'finance-dashboard' }, implemented: true },
                    { labelKey: 'menu.cashflow',           to: { name: 'finance-cashflow' },  implemented: true },
                    { labelKey: 'menu.budgets',            to: { name: 'finance-budgets' },   implemented: true },
                    { labelKey: 'menu.financialCalendar',  to: { name: 'finance-calendar' },  implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'accounting',
                labelKey:    'menu.accounting',
                icon:        BookOpenIcon,
                childRoutes: [
                    'accounting-dashboard', 'accounting-coa', 'accounting-journal', 'accounting-ledger',
                    'accounting-trial-balance', 'accounting-profit-loss', 'accounting-balance-sheet',
                    'accounting-cashbook', 'accounting-banks',
                ],
                children: [
                    { labelKey: 'menu.accountingDashboard', to: { name: 'accounting-dashboard' },    implemented: true },
                    { labelKey: 'menu.coa',                 to: { name: 'accounting-coa' },          implemented: true },
                    { labelKey: 'menu.journal',             to: { name: 'accounting-journal' },      implemented: true },
                    { labelKey: 'menu.ledger',              to: { name: 'accounting-ledger' },       implemented: true },
                    { labelKey: 'menu.trialBalance',        to: { name: 'accounting-trial-balance' },implemented: true },
                    { labelKey: 'menu.profitLoss',          to: { name: 'accounting-profit-loss' },  implemented: true },
                    { labelKey: 'menu.balanceSheet',        to: { name: 'accounting-balance-sheet' },implemented: true },
                    { labelKey: 'menu.cashbook',            to: { name: 'accounting-cashbook' },     implemented: true },
                    { labelKey: 'menu.banks',               to: { name: 'accounting-banks' },        implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'crm',
                labelKey:    'menu.crm',
                icon:        UserGroupIcon,
                childRoutes: [
                    'crm-intelligence', 'crm-segments', 'crm-customer-profile',
                    'crm-wallets', 'crm-loyalty-settings',
                ],
                children: [
                    { labelKey: 'menu.crmIntelligence',  to: { name: 'crm-intelligence' },     implemented: true },
                    { labelKey: 'menu.crmSegments',      to: { name: 'crm-segments' },         implemented: true },
                    { labelKey: 'menu.crmWallets',       to: { name: 'crm-wallets' },          implemented: true },
                    { labelKey: 'menu.crmLoyalty',       to: { name: 'crm-loyalty-settings' }, implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'oms',
                labelKey:    'menu.oms',
                icon:        ShoppingCartIcon,
                childRoutes: [
                    'oms-orders', 'oms-shipments', 'oms-automation',
                    'oms-notifications', 'oms-ecommerce',
                ],
                children: [
                    { labelKey: 'menu.omsOrders',        to: { name: 'oms-orders' },        implemented: true },
                    { labelKey: 'menu.omsShipments',     to: { name: 'oms-shipments' },     implemented: true },
                    { labelKey: 'menu.omsAutomation',    to: { name: 'oms-automation' },    implemented: true },
                    { labelKey: 'menu.omsNotifications', to: { name: 'oms-notifications' }, implemented: true },
                    { labelKey: 'menu.omsEcommerce',     to: { name: 'oms-ecommerce' },     implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'hrm',
                labelKey:    'menu.hrm',
                icon:        UserGroupIcon,
                childRoutes: [
                    'hrm-employees', 'hrm-employee-create', 'hrm-employee-show', 'hrm-employee-edit',
                    'hrm-departments', 'hrm-designations', 'hrm-shifts',
                    'hrm-attendance-daily', 'hrm-attendance-monthly',
                    'hrm-payroll', 'hrm-payroll-period', 'hrm-payslip',
                    'hrm-reports',
                    'hrm-payroll-approvals', 'hrm-salary-advances',
                    'hrm-workforce', 'hrm-attendance-intel',
                ],
                children: [
                    { labelKey: 'menu.employees',          to: { name: 'hrm-employees' },           implemented: true },
                    { labelKey: 'menu.departments',        to: { name: 'hrm-departments' },         implemented: true },
                    { labelKey: 'menu.designations',       to: { name: 'hrm-designations' },        implemented: true },
                    { labelKey: 'menu.shifts',             to: { name: 'hrm-shifts' },              implemented: true },
                    { labelKey: 'menu.attendance',         to: { name: 'hrm-attendance-daily' },    implemented: true },
                    { labelKey: 'menu.attendanceMonthly',  to: { name: 'hrm-attendance-monthly' },  implemented: true },
                    { labelKey: 'menu.payroll',            to: { name: 'hrm-payroll' },             implemented: true },
                    { labelKey: 'menu.payrollApprovals',   to: { name: 'hrm-payroll-approvals' },   implemented: true },
                    { labelKey: 'menu.salaryAdvances',     to: { name: 'hrm-salary-advances' },     implemented: true },
                    { labelKey: 'menu.workforceIntel',     to: { name: 'hrm-workforce' },           implemented: true },
                    { labelKey: 'menu.attendanceIntel',    to: { name: 'hrm-attendance-intel' },    implemented: true },
                    { labelKey: 'menu.hrmReports',         to: { name: 'hrm-reports' },             implemented: true },
                ],
            },
            {
                isGroup:     true,
                permKey:     'expenses',
                labelKey:    'menu.expenses',
                icon:        ReceiptPercentIcon,
                childRoutes: ['expenses', 'expense-categories', 'expense-reports'],
                children: [
                    { labelKey: 'menu.expenseList',       to: { name: 'expenses' },           implemented: true },
                    { labelKey: 'menu.expenseCategories', to: { name: 'expense-categories' }, implemented: true },
                    { labelKey: 'menu.expenseReports',    to: { name: 'expense-reports' },    implemented: true },
                ],
            },
        ],
    },
    {
        sectionKey: 'menu.sections.reports',
        items: [
            {
                isGroup:     true,
                permKey:     'reports',
                labelKey:    'menu.ledgers',
                icon:        ChartBarIcon,
                childRoutes: ['report-customer-ledger', 'report-supplier-ledger', 'report-product-ledger'],
                children: [
                    { labelKey: 'menu.customerLedger', to: { name: 'report-customer-ledger' }, implemented: true },
                    { labelKey: 'menu.supplierLedger', to: { name: 'report-supplier-ledger' }, implemented: true },
                    { labelKey: 'menu.productLedger',  to: { name: 'report-product-ledger' },  implemented: true },
                ],
            },
        ],
    },
    {
        sectionKey: 'menu.sections.system',
        items: [
            { permKey: 'branches',         labelKey: 'menu.branches',        to: { name: 'branches' },         icon: BuildingOffice2Icon, implemented: true },
            { permKey: 'users',            labelKey: 'menu.users',           to: { name: 'users' },            icon: UserCircleIcon,      implemented: true },
            { permKey: 'role-permissions', labelKey: 'menu.rolePermissions', to: { name: 'role-permissions' }, icon: ShieldCheckIcon,     implemented: true, adminOnly: true },
            { permKey: null,               labelKey: 'menu.settings',        to: { name: 'settings' },         icon: CogIcon,             implemented: true, adminOnly: true },
        ],
    },
];

const visibleGroups = computed(() =>
    NAV_GROUPS.map(group => ({
        ...group,
        items: group.items.filter(item => {
            if (item.isGroup)   return auth.hasPermission(item.permKey);
            if (!item.permKey)  return true;
            if (item.adminOnly) return auth.isAdmin;
            return auth.hasPermission(item.permKey);
        }),
    })).filter(group => group.items.length > 0)
);

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<style scoped>
@reference '../../../css/app.css';

.nav-icon { @apply w-5 h-5 flex-shrink-0; }

/* Sidebar shell — subtle vertical gradient on a near-black slate */
.sidebar-shell {
    background: linear-gradient(180deg, #0b1220 0%, #0f172a 60%, #0b1220 100%);
    border-right: 1px solid rgb(30 41 59 / 0.7);
    position: relative;
}
/* Soft inner glow on the right edge */
.sidebar-shell::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0;
    width: 1px;
    background: linear-gradient(180deg, transparent, rgb(99 102 241 / 0.18), transparent);
    pointer-events: none;
}

/* Brand bar — subtle divider, no harsh line */
.brand-bar {
    border-bottom: 1px solid rgb(30 41 59 / 0.6);
    background: linear-gradient(180deg, rgb(15 23 42 / 0.4), transparent);
}
.brand-mark {
    background: linear-gradient(135deg, rgb(99 102 241), rgb(79 70 229));
    box-shadow: 0 6px 16px -4px rgb(79 70 229 / 0.4),
                inset 0 0 0 1px rgb(165 180 252 / 0.3);
}

/* Custom thin scrollbar — only visible on hover */
.sidebar-nav {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
    transition: scrollbar-color 200ms ease;
}
.sidebar-nav:hover { scrollbar-color: rgb(51 65 85 / 0.7) transparent; }

.sidebar-nav::-webkit-scrollbar { width: 6px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 3px;
    transition: background 200ms ease;
}
.sidebar-nav:hover::-webkit-scrollbar-thumb { background: rgb(51 65 85 / 0.7); }
.sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgb(71 85 105 / 0.9); }

/* Footer user card */
.user-card {
    border-top: 1px solid rgb(30 41 59 / 0.6);
    background: linear-gradient(180deg, transparent, rgb(15 23 42 / 0.5));
}
.user-avatar {
    background: linear-gradient(135deg, rgb(99 102 241), rgb(79 70 229));
    box-shadow: 0 4px 12px -2px rgb(79 70 229 / 0.4),
                inset 0 0 0 1px rgb(165 180 252 / 0.3);
}
.logout-btn {
    @apply text-slate-500;
}
.logout-btn:hover {
    background: rgb(220 38 38 / 0.1);
    color: rgb(248 113 113);
}
</style>
