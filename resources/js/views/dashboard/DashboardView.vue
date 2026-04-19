<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- ── Page Header ─────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-500 mb-1">{{ dateString }}</p>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    {{ greeting }}, <span class="text-indigo-600">{{ auth.userName }}</span>.
                </h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ t('dashboard.subtitle') }}</p>
            </div>

            <div class="flex items-center gap-3">
                <span :class="roleBadgeClass">{{ roleBadgeLabel }}</span>
                <div class="flex items-center gap-1.5 text-xs text-gray-400 bg-white border border-gray-200 rounded-lg px-3 py-2">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    {{ t('dashboard.systemActive') }}
                </div>
            </div>
        </div>

        <!-- ── KPI Row ─────────────────────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <StatCard
                :label="t('dashboard.kpi.totalBranches')"
                :value="kpi.branches.total"
                :sub="t('dashboard.activeSub', { active: kpi.branches.active, inactive: kpi.branches.inactive })"
                :icon="BuildingOffice2Icon"
                icon-bg="bg-indigo-50"
                icon-color="text-indigo-600"
                :loading="kpi.loading"
            />
            <StatCard
                :label="t('dashboard.kpi.totalUsers')"
                :value="kpi.users.total"
                :sub="t('dashboard.activeSub', { active: kpi.users.active, inactive: kpi.users.inactive })"
                :icon="UsersIcon"
                icon-bg="bg-violet-50"
                icon-color="text-violet-600"
                :loading="kpi.loading"
            />
            <StatCard
                :label="t('dashboard.kpi.revenueToday')"
                value="€ 0,00"
                :sub="t('dashboard.kpi.revenueEmpty')"
                :icon="BanknotesIcon"
                icon-bg="bg-emerald-50"
                icon-color="text-emerald-600"
                :loading="false"
            />
            <StatCard
                :label="t('dashboard.kpi.lowStock')"
                value="—"
                :sub="t('dashboard.kpi.stockEmpty')"
                :icon="ExclamationTriangleIcon"
                icon-bg="bg-amber-50"
                icon-color="text-amber-600"
                :loading="false"
            />
        </div>

        <!-- ── Main Content Grid ───────────────────────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Branch Overview (2/3) -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ t('dashboard.branchOverview.title') }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">{{ t('dashboard.branchOverview.subtitle') }}</p>
                    </div>
                    <RouterLink
                        :to="{ name: 'branches' }"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors flex items-center gap-1"
                    >
                        {{ t('dashboard.branchOverview.showAll') }}
                        <ChevronRightIcon class="w-3.5 h-3.5" />
                    </RouterLink>
                </div>

                <!-- Loading -->
                <div v-if="kpi.loading" class="p-4 space-y-3">
                    <div v-for="i in 3" :key="i" class="animate-pulse flex items-center gap-4 px-2 py-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3.5 bg-gray-100 rounded w-32"></div>
                            <div class="h-3 bg-gray-100 rounded w-20"></div>
                        </div>
                        <div class="h-5 bg-gray-100 rounded-full w-14"></div>
                    </div>
                </div>

                <!-- Branch list -->
                <div v-else-if="recentBranches.length" class="divide-y divide-gray-50">
                    <div
                        v-for="branch in recentBranches"
                        :key="branch.id"
                        class="flex items-center gap-4 px-6 py-3.5 hover:bg-gray-50 transition-colors"
                    >
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <BuildingOffice2Icon class="w-4 h-4 text-indigo-500" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ branch.name }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                <span class="font-mono bg-gray-100 px-1 rounded text-gray-500">{{ branch.code }}</span>
                                <span v-if="branch.phone" class="ml-2">{{ branch.phone }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-400 flex-shrink-0">
                            <UsersIcon class="w-3.5 h-3.5" />
                            <span>{{ branch.user_count ?? 0 }}</span>
                        </div>
                        <span :class="[
                            'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0',
                            branch.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500',
                        ]">
                            <span :class="['w-1.5 h-1.5 rounded-full', branch.is_active ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                            {{ branch.is_active ? t('common.active') : t('common.inactive') }}
                        </span>
                    </div>
                </div>

                <!-- Empty -->
                <div v-else class="flex flex-col items-center justify-center py-14 text-center">
                    <BuildingOffice2Icon class="w-10 h-10 text-gray-200 mb-3" />
                    <p class="text-sm font-medium text-gray-400">{{ t('dashboard.branchOverview.noBranches') }}</p>
                    <RouterLink :to="{ name: 'branches' }" class="mt-3 text-xs font-semibold text-indigo-600 hover:underline">
                        {{ t('dashboard.branchOverview.createFirst') }}
                    </RouterLink>
                </div>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col gap-5">

                <!-- Quick Navigation -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">{{ t('dashboard.quickAccess.title') }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">{{ t('dashboard.quickAccess.subtitle') }}</p>
                    </div>
                    <div class="p-3 space-y-1.5">
                        <RouterLink
                            v-for="link in quickLinks"
                            :key="link.name"
                            :to="link.to"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-indigo-50 transition-colors group"
                        >
                            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', link.iconBg]">
                                <component :is="link.icon" :class="['w-4 h-4', link.iconColor]" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700">{{ link.label }}</p>
                                <p class="text-xs text-gray-400">{{ link.description }}</p>
                            </div>
                            <ChevronRightIcon class="w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors" />
                        </RouterLink>
                    </div>
                </div>

                <!-- System Info -->
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ t('dashboard.systemInfo.title') }}</h2>
                    <dl class="space-y-3">
                        <div v-for="item in systemInfo" :key="item.label" class="flex items-center justify-between">
                            <dt class="text-xs text-gray-400">{{ item.label }}</dt>
                            <dd class="text-xs font-semibold text-gray-700">{{ item.value }}</dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>

        <!-- ── Module Status ───────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">{{ t('dashboard.moduleStatus.title') }}</h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ t('dashboard.moduleStatus.subtitle') }}</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y divide-gray-100">
                <div
                    v-for="mod in moduleStatusList"
                    :key="mod.key"
                    class="flex flex-col items-center gap-2 px-4 py-5 text-center"
                >
                    <div :class="['w-2.5 h-2.5 rounded-full', mod.active ? 'bg-emerald-500' : 'bg-gray-200']"></div>
                    <p class="text-xs font-semibold text-gray-700">{{ t(`dashboard.moduleStatus.modules.${mod.key}`) }}</p>
                    <span :class="[
                        'inline-block text-xs px-2 py-0.5 rounded-full font-medium',
                        mod.active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400',
                    ]">
                        {{ mod.active ? t('dashboard.moduleStatus.active') : t(`dashboard.moduleStatus.phases.${mod.phase}`) }}
                    </span>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useLocale } from '@/composables/useLocale';
import { branchService } from '@/services/branchService';
import { userService } from '@/services/userService';
import { RouterLink } from 'vue-router';

import {
    BuildingOffice2Icon, UsersIcon, BanknotesIcon, ExclamationTriangleIcon,
    UserCircleIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline';

import StatCard from './StatCard.vue';

const { t }          = useI18n();
const { intlLocale } = useLocale();
const auth           = useAuthStore();

// ── Greeting + date ────────────────────────────────────────────────────────
const now  = new Date();
const hour = now.getHours();
const greetingKey = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';

const greeting  = computed(() => t(`dashboard.greeting.${greetingKey}`));
const dateString = computed(() =>
    new Intl.DateTimeFormat(intlLocale.value, {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    }).format(now)
);

// ── Role badge ─────────────────────────────────────────────────────────────
const ROLE_CLASSES = {
    admin:   'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow-sm',
    manager: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-violet-100 text-violet-700',
    cashier: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600',
};

const roleBadgeClass = computed(() => ROLE_CLASSES[auth.userRole] ?? ROLE_CLASSES.cashier);
const roleBadgeLabel = computed(() => t(`dashboard.roleBadges.${auth.userRole ?? 'cashier'}`));

// ── KPI Data ───────────────────────────────────────────────────────────────
const kpi = reactive({
    loading: true,
    branches: { total: 0, active: 0, inactive: 0 },
    users:    { total: 0, active: 0, inactive: 0 },
});
const recentBranches = ref([]);

onMounted(async () => {
    try {
        const [brAll, brActive, usAll, usActive] = await Promise.all([
            branchService.index({ per_page: 6, page: 1 }),
            branchService.index({ is_active: 1, per_page: 1 }),
            userService.index({ per_page: 1 }),
            userService.index({ is_active: 1, per_page: 1 }),
        ]);
        kpi.branches.total    = brAll.data.meta.total;
        kpi.branches.active   = brActive.data.meta.total;
        kpi.branches.inactive = kpi.branches.total - kpi.branches.active;
        recentBranches.value  = brAll.data.data;

        kpi.users.total    = usAll.data.meta.total;
        kpi.users.active   = usActive.data.meta.total;
        kpi.users.inactive = kpi.users.total - kpi.users.active;
    } catch { /* non-critical */ } finally {
        kpi.loading = false;
    }
});

// ── Quick links ────────────────────────────────────────────────────────────
const quickLinks = computed(() => [
    {
        label: t('dashboard.quickAccess.manageBranches'),
        description: t('dashboard.quickAccess.manageBranchesDesc'),
        to: { name: 'branches' },
        icon: BuildingOffice2Icon,
        iconBg: 'bg-indigo-50',
        iconColor: 'text-indigo-600',
    },
    {
        label: t('dashboard.quickAccess.manageUsers'),
        description: t('dashboard.quickAccess.manageUsersDesc'),
        to: { name: 'users' },
        icon: UserCircleIcon,
        iconBg: 'bg-violet-50',
        iconColor: 'text-violet-600',
    },
]);

// ── System Info ────────────────────────────────────────────────────────────
const systemInfo = computed(() => [
    { label: t('dashboard.systemInfo.version'),     value: 'v0.3.0 — Phase 1' },
    { label: t('dashboard.systemInfo.framework'),   value: 'Laravel 13 + Vue 3' },
    { label: t('dashboard.systemInfo.auth'),        value: 'Sanctum (Token)' },
    { label: t('dashboard.systemInfo.environment'), value: 'Development' },
]);

// ── Module status ──────────────────────────────────────────────────────────
const moduleStatusList = [
    { key: 'auth',     active: true,  phase: '' },
    { key: 'branches', active: true,  phase: '' },
    { key: 'users',    active: true,  phase: '' },
    { key: 'products', active: false, phase: 'phase2' },
    { key: 'pos',      active: false, phase: 'phase3' },
    { key: 'reports',  active: false, phase: 'phase4' },
];
</script>
