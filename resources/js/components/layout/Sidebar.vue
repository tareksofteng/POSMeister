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
                <!-- Icon mark -->
                <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <!-- Wordmark (hidden when collapsed) -->
                <span v-if="!collapsed" class="text-white font-semibold text-base tracking-tight truncate">
                    POSmeister
                </span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 space-y-0.5">

            <!-- Primary group -->
            <NavItem :collapsed="collapsed" :to="{ name: 'dashboard' }" label="Dashboard">
                <template #icon>
                    <Squares2X2Icon class="nav-icon" />
                </template>
            </NavItem>

            <NavItem :collapsed="collapsed" :to="{ name: 'pos' }" label="Point of Sale">
                <template #icon>
                    <ShoppingCartIcon class="nav-icon" />
                </template>
            </NavItem>

            <!-- Section: Commerce -->
            <SidebarSectionLabel v-if="!collapsed" label="Commerce" />

            <NavItem :collapsed="collapsed" :to="{ name: 'sales' }" label="Sales">
                <template #icon>
                    <DocumentTextIcon class="nav-icon" />
                </template>
            </NavItem>

            <NavItem :collapsed="collapsed" :to="{ name: 'purchases' }" label="Purchases">
                <template #icon>
                    <TruckIcon class="nav-icon" />
                </template>
            </NavItem>

            <NavItem :collapsed="collapsed" :to="{ name: 'quotations' }" label="Quotations">
                <template #icon>
                    <ClipboardDocumentListIcon class="nav-icon" />
                </template>
            </NavItem>

            <!-- Section: Catalogue -->
            <SidebarSectionLabel v-if="!collapsed" label="Catalogue" />

            <NavItem :collapsed="collapsed" :to="{ name: 'products' }" label="Products">
                <template #icon>
                    <TagIcon class="nav-icon" />
                </template>
            </NavItem>

            <NavItem :collapsed="collapsed" :to="{ name: 'inventory' }" label="Inventory">
                <template #icon>
                    <ArchiveBoxIcon class="nav-icon" />
                </template>
            </NavItem>

            <!-- Section: Stakeholders -->
            <SidebarSectionLabel v-if="!collapsed" label="Stakeholders" />

            <NavItem :collapsed="collapsed" :to="{ name: 'customers' }" label="Customers">
                <template #icon>
                    <UsersIcon class="nav-icon" />
                </template>
            </NavItem>

            <NavItem :collapsed="collapsed" :to="{ name: 'suppliers' }" label="Suppliers">
                <template #icon>
                    <BuildingStorefrontIcon class="nav-icon" />
                </template>
            </NavItem>

            <!-- Section: Finance & HR -->
            <SidebarSectionLabel v-if="!collapsed" label="Finance & HR" />

            <NavItem :collapsed="collapsed" :to="{ name: 'finance' }" label="Finance">
                <template #icon>
                    <BanknotesIcon class="nav-icon" />
                </template>
            </NavItem>

            <NavItem :collapsed="collapsed" :to="{ name: 'employees' }" label="Employees">
                <template #icon>
                    <UserGroupIcon class="nav-icon" />
                </template>
            </NavItem>

            <!-- Section: Reports -->
            <SidebarSectionLabel v-if="!collapsed" label="Reports" />

            <NavItem :collapsed="collapsed" :to="{ name: 'reports' }" label="Reports">
                <template #icon>
                    <ChartBarIcon class="nav-icon" />
                </template>
            </NavItem>

            <!-- Section: Settings -->
            <SidebarSectionLabel v-if="!collapsed" label="System" />

            <NavItem :collapsed="collapsed" :to="{ name: 'settings' }" label="Settings">
                <template #icon>
                    <CogIcon class="nav-icon" />
                </template>
            </NavItem>

        </nav>

        <!-- Footer: user info + logout -->
        <div class="border-t border-slate-800 px-3 py-3">
            <div class="flex items-center gap-3" :class="{ 'justify-center': collapsed }">
                <!-- Avatar -->
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-semibold">
                    {{ userInitial }}
                </div>
                <!-- Name + role (hidden when collapsed) -->
                <div v-if="!collapsed" class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-200 truncate">{{ auth.userName }}</p>
                    <p class="text-xs text-slate-500 capitalize truncate">{{ auth.userRole }}</p>
                </div>
                <!-- Logout button -->
                <button
                    v-if="!collapsed"
                    @click="handleLogout"
                    class="flex-shrink-0 p-1 text-slate-500 hover:text-slate-300 transition-colors"
                    title="Logout"
                >
                    <ArrowRightStartOnRectangleIcon class="w-4 h-4" />
                </button>
            </div>
        </div>

    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

import {
    Squares2X2Icon,
    ShoppingCartIcon,
    DocumentTextIcon,
    TruckIcon,
    ClipboardDocumentListIcon,
    TagIcon,
    ArchiveBoxIcon,
    UsersIcon,
    BuildingStorefrontIcon,
    BanknotesIcon,
    UserGroupIcon,
    ChartBarIcon,
    CogIcon,
    ArrowRightStartOnRectangleIcon,
} from '@heroicons/vue/24/outline';

import NavItem           from './NavItem.vue';
import SidebarSectionLabel from './SidebarSectionLabel.vue';

defineProps({
    collapsed: { type: Boolean, default: false },
});

const auth   = useAuthStore();
const router = useRouter();

const userInitial = computed(() =>
    auth.userName ? auth.userName.charAt(0).toUpperCase() : '?'
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
