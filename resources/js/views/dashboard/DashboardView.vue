<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- ── Page Header ─────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-500 mb-1">{{ dateString }}</p>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    {{ greeting }}, <span class="text-indigo-600">{{ auth.userName }}</span>.
                </h1>
                <p class="mt-0.5 text-sm text-gray-500">Hier ist die aktuelle Systemübersicht.</p>
            </div>

            <div class="flex items-center gap-3">
                <span :class="roleBadge.class">
                    {{ roleBadge.label }}
                </span>
                <div class="flex items-center gap-1.5 text-xs text-gray-400 bg-white border border-gray-200 rounded-lg px-3 py-2">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    System aktiv
                </div>
            </div>
        </div>

        <!-- ── KPI Row ─────────────────────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <StatCard
                label="Filialen gesamt"
                :value="kpi.branches.total"
                :sub="kpi.branches.active + ' aktiv · ' + kpi.branches.inactive + ' inaktiv'"
                :icon="BuildingOffice2Icon"
                icon-bg="bg-indigo-50"
                icon-color="text-indigo-600"
                :loading="kpi.loading"
            />
            <StatCard
                label="Benutzer gesamt"
                :value="kpi.users.total"
                :sub="kpi.users.active + ' aktiv · ' + kpi.users.inactive + ' inaktiv'"
                :icon="UsersIcon"
                icon-bg="bg-violet-50"
                icon-color="text-violet-600"
                :loading="kpi.loading"
            />
            <StatCard
                label="Umsatz heute"
                value="€ 0,00"
                sub="Verkaufsmodul noch nicht aktiv"
                :icon="BanknotesIcon"
                icon-bg="bg-emerald-50"
                icon-color="text-emerald-600"
                :loading="false"
            />
            <StatCard
                label="Niedriger Lagerstand"
                value="—"
                sub="Inventarmodul noch nicht aktiv"
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
                        <h2 class="text-sm font-semibold text-gray-900">Filialen-Übersicht</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Alle registrierten Standorte</p>
                    </div>
                    <RouterLink
                        :to="{ name: 'branches' }"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors flex items-center gap-1"
                    >
                        Alle anzeigen
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
                        <!-- Icon -->
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <BuildingOffice2Icon class="w-4 h-4 text-indigo-500" />
                        </div>
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ branch.name }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                <span class="font-mono bg-gray-100 px-1 rounded text-gray-500">{{ branch.code }}</span>
                                <span v-if="branch.phone" class="ml-2">{{ branch.phone }}</span>
                            </p>
                        </div>
                        <!-- User count -->
                        <div class="flex items-center gap-1.5 text-xs text-gray-400 flex-shrink-0">
                            <UsersIcon class="w-3.5 h-3.5" />
                            <span>{{ branch.user_count ?? 0 }}</span>
                        </div>
                        <!-- Status badge -->
                        <span :class="[
                            'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0',
                            branch.is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-gray-100 text-gray-500',
                        ]">
                            <span :class="['w-1.5 h-1.5 rounded-full', branch.is_active ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                            {{ branch.is_active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </div>
                </div>

                <!-- Empty -->
                <div v-else class="flex flex-col items-center justify-center py-14 text-center">
                    <BuildingOffice2Icon class="w-10 h-10 text-gray-200 mb-3" />
                    <p class="text-sm font-medium text-gray-400">Keine Filialen vorhanden</p>
                    <RouterLink
                        :to="{ name: 'branches' }"
                        class="mt-3 text-xs font-semibold text-indigo-600 hover:underline"
                    >
                        Erste Filiale anlegen →
                    </RouterLink>
                </div>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col gap-5">

                <!-- Quick Navigation -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Schnellzugriff</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Aktive Module</p>
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
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Systeminformation</h2>
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
                <h2 class="text-sm font-semibold text-gray-900">Modul-Status</h2>
                <p class="text-xs text-gray-400 mt-0.5">Implementierungsfortschritt</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y divide-gray-100">
                <ModuleStatusCell
                    v-for="mod in modules"
                    :key="mod.label"
                    v-bind="mod"
                />
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { branchService } from '@/services/branchService';
import { userService } from '@/services/userService';
import { RouterLink } from 'vue-router';

import {
    BuildingOffice2Icon,
    UsersIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    UserCircleIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';

import StatCard from './StatCard.vue';

// ── Sub-components defined inline ──────────────────────────────────────────

const ModuleStatusCell = {
    props: {
        label:   { type: String, required: true },
        active:  { type: Boolean, default: false },
        phase:   { type: String, default: '' },
    },
    template: `
        <div class="flex flex-col items-center gap-2 px-4 py-5 text-center">
            <div :class="[
                'w-2.5 h-2.5 rounded-full',
                active ? 'bg-emerald-500' : 'bg-gray-200'
            ]"></div>
            <p class="text-xs font-semibold text-gray-700">{{ label }}</p>
            <span :class="[
                'inline-block text-xs px-2 py-0.5 rounded-full font-medium',
                active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400'
            ]">{{ active ? 'Aktiv' : phase }}</span>
        </div>
    `,
};

// ── Auth ───────────────────────────────────────────────────────────────────
const auth = useAuthStore();

// ── Date / Greeting ────────────────────────────────────────────────────────
const now  = new Date();
const hour = now.getHours();

const greeting = hour < 12 ? 'Guten Morgen' : hour < 17 ? 'Guten Tag' : 'Guten Abend';

const dateString = new Intl.DateTimeFormat('de-DE', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}).format(now);

// ── Role badge ─────────────────────────────────────────────────────────────
const roleBadge = computed(() => {
    const map = {
        admin:   { label: 'Administrator', class: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow-sm' },
        manager: { label: 'Manager',       class: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-violet-100 text-violet-700' },
        cashier: { label: 'Kassierer',     class: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600' },
    };
    return map[auth.userRole] ?? map.cashier;
});

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
    } catch {
        // Non-critical — dashboard shows empty state
    } finally {
        kpi.loading = false;
    }
});

// ── Quick Links ────────────────────────────────────────────────────────────
const quickLinks = [
    {
        label: 'Filialen verwalten',
        description: 'Standorte anlegen & bearbeiten',
        to: { name: 'branches' },
        icon: BuildingOffice2Icon,
        iconBg: 'bg-indigo-50',
        iconColor: 'text-indigo-600',
    },
    {
        label: 'Benutzer verwalten',
        description: 'Zugänge & Rollen konfigurieren',
        to: { name: 'users' },
        icon: UserCircleIcon,
        iconBg: 'bg-violet-50',
        iconColor: 'text-violet-600',
    },
];

// ── System Info ────────────────────────────────────────────────────────────
const systemInfo = [
    { label: 'Version',    value: 'v0.2.0 — Phase 1' },
    { label: 'Framework',  value: 'Laravel 13 + Vue 3' },
    { label: 'Auth',       value: 'Sanctum (Token)' },
    { label: 'Umgebung',   value: 'Development' },
];

// ── Module Status ──────────────────────────────────────────────────────────
const modules = [
    { label: 'Auth & Login',  active: true,  phase: '' },
    { label: 'Filialen',      active: true,  phase: '' },
    { label: 'Benutzer',      active: true,  phase: '' },
    { label: 'Produkte',      active: false, phase: 'Phase 2' },
    { label: 'Verkauf / POS', active: false, phase: 'Phase 3' },
    { label: 'Berichte',      active: false, phase: 'Phase 4' },
];
</script>
