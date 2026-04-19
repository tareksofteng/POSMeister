<template>
    <aside
        :class="[
            'flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out bg-slate-900',
            collapsed ? 'w-16' : 'w-64',
        ]"
    >
        <!-- Logo / Brand -->
        <div class="flex h-16 items-center px-4 border-b border-slate-800">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <span class="block text-white font-bold text-sm tracking-tight truncate">POSmeister</span>
                    <span class="block text-slate-500 text-xs tracking-wide">{{ t('app.management') }}</span>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-0.5">

            <!-- Dashboard — always visible -->
            <NavItem :collapsed="collapsed" :to="{ name: 'dashboard' }" :label="t('menu.dashboard')">
                <template #icon><Squares2X2Icon class="nav-icon" /></template>
            </NavItem>

            <!-- Dynamic permission-based groups -->
            <template v-for="group in visibleGroups" :key="group.sectionKey">
                <SidebarSectionLabel v-if="!collapsed && group.items.length" :label="t(group.sectionKey)" />

                <template v-for="item in group.items" :key="item.permKey">
                    <NavItem
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
        <div class="border-t border-slate-800 px-3 py-3">
            <div class="flex items-center gap-3" :class="{ 'justify-center': collapsed }">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow">
                    {{ userInitial }}
                </div>
                <div v-if="!collapsed" class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-200 truncate">{{ auth.userName }}</p>
                    <p class="text-xs text-slate-500 capitalize truncate">{{ auth.userRole }}</p>
                </div>
                <button
                    v-if="!collapsed"
                    @click="handleLogout"
                    class="flex-shrink-0 p-1.5 text-slate-500 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors"
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
import { useAuthStore } from '@/stores/auth';

import {
    Squares2X2Icon, ShoppingCartIcon, DocumentTextIcon, TruckIcon,
    ClipboardDocumentListIcon, TagIcon, ArchiveBoxIcon, UsersIcon,
    BuildingStorefrontIcon, BanknotesIcon, UserGroupIcon, ChartBarIcon,
    CogIcon, ArrowRightStartOnRectangleIcon, BuildingOffice2Icon,
    UserCircleIcon, ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

import NavItem             from './NavItem.vue';
import SidebarSectionLabel from './SidebarSectionLabel.vue';

defineProps({
    collapsed: { type: Boolean, default: false },
});

const { t }  = useI18n();
const auth   = useAuthStore();
const router = useRouter();

const userInitial = computed(() =>
    auth.userName ? auth.userName.charAt(0).toUpperCase() : '?'
);

// ── Nav config ─────────────────────────────────────────────────────────────
// sectionKey → i18n key for section label
// labelKey   → i18n key for item label
// permKey    → permission module key (null = always visible)
// adminOnly  → only show to admins

const NAV_GROUPS = [
    {
        sectionKey: 'menu.sections.commerce',
        items: [
            { permKey: 'pos',        labelKey: 'menu.pointOfSale', to: { name: 'pos' },        icon: ShoppingCartIcon,          implemented: false },
            { permKey: 'sales',      labelKey: 'menu.sales',       to: { name: 'sales' },       icon: DocumentTextIcon,          implemented: false },
            { permKey: 'purchases',  labelKey: 'menu.purchases',   to: { name: 'purchases' },   icon: TruckIcon,                 implemented: false },
            { permKey: 'quotations', labelKey: 'menu.quotations',  to: { name: 'quotations' },  icon: ClipboardDocumentListIcon, implemented: false },
        ],
    },
    {
        sectionKey: 'menu.sections.catalogue',
        items: [
            { permKey: 'products',  labelKey: 'menu.products',        to: { name: 'products' },         icon: TagIcon,        implemented: true  },
            { permKey: 'products',  labelKey: 'menu.productSettings', to: { name: 'product-settings' }, icon: CogIcon,        implemented: true  },
            { permKey: 'inventory', labelKey: 'menu.inventory',       to: { name: 'inventory' },        icon: ArchiveBoxIcon, implemented: false },
        ],
    },
    {
        sectionKey: 'menu.sections.stakeholders',
        items: [
            { permKey: 'customers', labelKey: 'menu.customers', to: { name: 'customers' }, icon: UsersIcon,              implemented: false },
            { permKey: 'suppliers', labelKey: 'menu.suppliers', to: { name: 'suppliers' }, icon: BuildingStorefrontIcon, implemented: false },
        ],
    },
    {
        sectionKey: 'menu.sections.financeHr',
        items: [
            { permKey: 'finance',   labelKey: 'menu.finance',   to: { name: 'finance' },   icon: BanknotesIcon, implemented: false },
            { permKey: 'employees', labelKey: 'menu.employees', to: { name: 'employees' }, icon: UserGroupIcon, implemented: false },
        ],
    },
    {
        sectionKey: 'menu.sections.reports',
        items: [
            { permKey: 'reports', labelKey: 'menu.reports', to: { name: 'reports' }, icon: ChartBarIcon, implemented: false },
        ],
    },
    {
        sectionKey: 'menu.sections.system',
        items: [
            { permKey: 'branches',         labelKey: 'menu.branches',         to: { name: 'branches' },         icon: BuildingOffice2Icon, implemented: true  },
            { permKey: 'users',            labelKey: 'menu.users',            to: { name: 'users' },             icon: UserCircleIcon,     implemented: true  },
            { permKey: 'role-permissions', labelKey: 'menu.rolePermissions',  to: { name: 'role-permissions' }, icon: ShieldCheckIcon,    implemented: true,  adminOnly: true },
            { permKey: null,               labelKey: 'menu.settings',         to: { name: 'settings' },          icon: CogIcon,            implemented: false },
        ],
    },
];

const visibleGroups = computed(() =>
    NAV_GROUPS.map(group => ({
        ...group,
        items: group.items.filter(item => {
            if (!item.permKey) return true;
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
</style>
