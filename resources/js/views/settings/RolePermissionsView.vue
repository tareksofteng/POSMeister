<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Zugriffsrechte</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Konfigurieren Sie, welche Module für jede Benutzerrolle sichtbar sind.
                    Administratoren haben immer vollständigen Zugriff.
                </p>
            </div>
            <button
                @click="saveAll"
                :disabled="saving || loading"
                class="flex-shrink-0 flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors shadow-sm"
            >
                <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <CheckIcon v-else class="w-4 h-4" />
                {{ saving ? 'Speichert…' : 'Änderungen speichern' }}
            </button>
        </div>

        <!-- Success banner -->
        <Transition name="fade">
            <div v-if="saved" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                <CheckCircleIcon class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                <p class="text-sm font-medium text-emerald-700">Zugriffsrechte wurden erfolgreich gespeichert.</p>
            </div>
        </Transition>

        <!-- Error banner -->
        <div v-if="error" class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            <ExclamationCircleIcon class="w-5 h-5 text-red-500 flex-shrink-0" />
            <p class="text-sm text-red-700">{{ error }}</p>
        </div>

        <!-- Permission Matrix -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

            <!-- Loading -->
            <div v-if="loading" class="p-8 space-y-4 animate-pulse">
                <div v-for="i in 6" :key="i" class="flex items-center gap-4">
                    <div class="h-4 bg-gray-100 rounded w-32"></div>
                    <div class="flex-1"></div>
                    <div class="h-6 w-6 bg-gray-100 rounded"></div>
                    <div class="h-6 w-6 bg-gray-100 rounded"></div>
                    <div class="h-6 w-6 bg-gray-100 rounded"></div>
                </div>
            </div>

            <template v-else>
                <!-- Table header -->
                <div class="grid grid-cols-[1fr_100px_140px_140px] border-b border-gray-200 bg-gray-50">
                    <div class="px-6 py-3.5">
                        <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Modul</span>
                    </div>
                    <!-- Admin column -->
                    <div class="flex flex-col items-center justify-center px-4 py-3.5 border-l border-gray-200">
                        <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center mb-1">
                            <ShieldCheckIcon class="w-4 h-4 text-white" />
                        </div>
                        <span class="text-xs font-semibold text-gray-700">Admin</span>
                    </div>
                    <!-- Manager column -->
                    <div class="flex flex-col items-center justify-center px-4 py-3.5 border-l border-gray-200">
                        <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center mb-1">
                            <UserGroupIcon class="w-4 h-4 text-violet-600" />
                        </div>
                        <span class="text-xs font-semibold text-gray-700">Manager</span>
                        <button
                            @click="toggleAll('manager')"
                            class="mt-1.5 text-xs text-indigo-500 hover:text-indigo-700 font-medium"
                        >
                            {{ allSelected('manager') ? 'Alle abwählen' : 'Alle wählen' }}
                        </button>
                    </div>
                    <!-- Cashier column -->
                    <div class="flex flex-col items-center justify-center px-4 py-3.5 border-l border-gray-200">
                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mb-1">
                            <UserIcon class="w-4 h-4 text-amber-600" />
                        </div>
                        <span class="text-xs font-semibold text-gray-700">Kassierer</span>
                        <button
                            @click="toggleAll('cashier')"
                            class="mt-1.5 text-xs text-indigo-500 hover:text-indigo-700 font-medium"
                        >
                            {{ allSelected('cashier') ? 'Alle abwählen' : 'Alle wählen' }}
                        </button>
                    </div>
                </div>

                <!-- Module groups -->
                <template v-for="group in MODULE_GROUPS" :key="group.section">

                    <!-- Section header -->
                    <div class="grid grid-cols-[1fr_100px_140px_140px] bg-gray-50 border-t border-gray-100">
                        <div class="px-6 py-2 col-span-4">
                            <span class="text-xs font-bold uppercase tracking-widest text-gray-400">
                                {{ group.section }}
                            </span>
                        </div>
                    </div>

                    <!-- Module rows -->
                    <div
                        v-for="mod in group.items"
                        :key="mod.key"
                        class="grid grid-cols-[1fr_100px_140px_140px] border-t border-gray-100 hover:bg-gray-50 transition-colors"
                    >
                        <!-- Module info -->
                        <div class="flex items-center gap-3 px-6 py-3.5">
                            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', mod.iconBg]">
                                <component :is="mod.icon" :class="['w-4 h-4', mod.iconColor]" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ mod.label }}</p>
                                <p class="text-xs text-gray-400">{{ mod.description }}</p>
                            </div>
                        </div>

                        <!-- Admin: always granted -->
                        <div class="flex items-center justify-center border-l border-gray-100">
                            <div class="w-5 h-5 rounded bg-indigo-100 flex items-center justify-center">
                                <CheckIcon class="w-3.5 h-3.5 text-indigo-600" />
                            </div>
                        </div>

                        <!-- Manager toggle -->
                        <div class="flex items-center justify-center border-l border-gray-100">
                            <button
                                type="button"
                                @click="toggle('manager', mod.key)"
                                :class="[
                                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                    has('manager', mod.key) ? 'bg-indigo-600' : 'bg-gray-200',
                                ]"
                            >
                                <span :class="[
                                    'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                                    has('manager', mod.key) ? 'translate-x-6' : 'translate-x-1',
                                ]" />
                            </button>
                        </div>

                        <!-- Cashier toggle -->
                        <div class="flex items-center justify-center border-l border-gray-100">
                            <button
                                type="button"
                                @click="toggle('cashier', mod.key)"
                                :class="[
                                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2',
                                    has('cashier', mod.key) ? 'bg-amber-500' : 'bg-gray-200',
                                ]"
                            >
                                <span :class="[
                                    'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                                    has('cashier', mod.key) ? 'translate-x-6' : 'translate-x-1',
                                ]" />
                            </button>
                        </div>
                    </div>

                </template>

                <!-- Footer summary -->
                <div class="grid grid-cols-[1fr_100px_140px_140px] border-t border-gray-200 bg-gray-50">
                    <div class="px-6 py-3 flex items-center">
                        <span class="text-xs text-gray-400">{{ totalModules }} Module gesamt</span>
                    </div>
                    <div class="flex items-center justify-center border-l border-gray-200 py-3">
                        <span class="text-xs font-semibold text-indigo-600">{{ totalModules }}/{{ totalModules }}</span>
                    </div>
                    <div class="flex items-center justify-center border-l border-gray-200 py-3">
                        <span class="text-xs font-semibold text-violet-600">
                            {{ localPerms.manager.length }}/{{ totalModules }}
                        </span>
                    </div>
                    <div class="flex items-center justify-center border-l border-gray-200 py-3">
                        <span class="text-xs font-semibold text-amber-600">
                            {{ localPerms.cashier.length }}/{{ totalModules }}
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap items-center gap-6 text-xs text-gray-400">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded bg-indigo-100 flex items-center justify-center">
                    <CheckIcon class="w-3 h-3 text-indigo-600" />
                </div>
                Admin — immer vollständiger Zugriff
            </div>
            <div class="flex items-center gap-2">
                <div class="h-3 w-5 rounded-full bg-indigo-600"></div>
                Manager — konfigurierbar
            </div>
            <div class="flex items-center gap-2">
                <div class="h-3 w-5 rounded-full bg-amber-500"></div>
                Kassierer — konfigurierbar
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { rolePermissionService } from '@/services/rolePermissionService';
import {
    CheckIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    UserIcon,
    ShoppingCartIcon,
    DocumentTextIcon,
    TruckIcon,
    ClipboardDocumentListIcon,
    TagIcon,
    ArchiveBoxIcon,
    UsersIcon,
    BuildingStorefrontIcon,
    BanknotesIcon,
    UserCircleIcon,
    ChartBarIcon,
    BuildingOffice2Icon,
} from '@heroicons/vue/24/outline';

// ── Module groups definition ───────────────────────────────────────────────
const MODULE_GROUPS = [
    {
        section: 'Commerce',
        items: [
            { key: 'pos',        label: 'Point of Sale', description: 'Kassensystem', icon: ShoppingCartIcon,         iconBg: 'bg-indigo-50',  iconColor: 'text-indigo-600' },
            { key: 'sales',      label: 'Verkauf',       description: 'Belege & Rechnungen', icon: DocumentTextIcon,  iconBg: 'bg-blue-50',    iconColor: 'text-blue-600' },
            { key: 'purchases',  label: 'Einkauf',       description: 'Bestellwesen', icon: TruckIcon,                iconBg: 'bg-amber-50',   iconColor: 'text-amber-600' },
            { key: 'quotations', label: 'Angebote',      description: 'Kundenangebote', icon: ClipboardDocumentListIcon, iconBg: 'bg-teal-50', iconColor: 'text-teal-600' },
        ],
    },
    {
        section: 'Catalogue',
        items: [
            { key: 'products',  label: 'Produkte',   description: 'Artikelverwaltung', icon: TagIcon,       iconBg: 'bg-violet-50',  iconColor: 'text-violet-600' },
            { key: 'inventory', label: 'Inventar',   description: 'Lagerbestand',      icon: ArchiveBoxIcon, iconBg: 'bg-orange-50', iconColor: 'text-orange-600' },
        ],
    },
    {
        section: 'Stakeholders',
        items: [
            { key: 'customers', label: 'Kunden',     description: 'Kundenstamm',      icon: UsersIcon,              iconBg: 'bg-green-50',  iconColor: 'text-green-600' },
            { key: 'suppliers', label: 'Lieferanten', description: 'Lieferantenpflege', icon: BuildingStorefrontIcon, iconBg: 'bg-slate-100', iconColor: 'text-slate-600' },
        ],
    },
    {
        section: 'Finance & HR',
        items: [
            { key: 'finance',   label: 'Finanzen',    description: 'Buchhaltung & Zahlungen', icon: BanknotesIcon, iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600' },
            { key: 'employees', label: 'Mitarbeiter', description: 'Personalverwaltung',      icon: UserGroupIcon, iconBg: 'bg-pink-50',    iconColor: 'text-pink-600' },
        ],
    },
    {
        section: 'Reports',
        items: [
            { key: 'reports', label: 'Berichte', description: 'Auswertungen & Statistiken', icon: ChartBarIcon, iconBg: 'bg-cyan-50', iconColor: 'text-cyan-600' },
        ],
    },
    {
        section: 'System',
        items: [
            { key: 'branches', label: 'Filialen', description: 'Standortverwaltung', icon: BuildingOffice2Icon, iconBg: 'bg-indigo-50', iconColor: 'text-indigo-600' },
            { key: 'users',    label: 'Benutzer', description: 'Zugangsverwaltung',  icon: UserCircleIcon,     iconBg: 'bg-violet-50', iconColor: 'text-violet-600' },
        ],
    },
];

const totalModules = MODULE_GROUPS.reduce((n, g) => n + g.items.length, 0);

// ── State ──────────────────────────────────────────────────────────────────
const loading = ref(true);
const saving  = ref(false);
const saved   = ref(false);
const error   = ref('');

const localPerms = reactive({
    manager: [],
    cashier: [],
});

onMounted(async () => {
    try {
        const { data } = await rolePermissionService.get();
        localPerms.manager = [...(data.data.manager ?? [])];
        localPerms.cashier = [...(data.data.cashier ?? [])];
    } catch {
        error.value = 'Zugriffsrechte konnten nicht geladen werden.';
    } finally {
        loading.value = false;
    }
});

// ── Toggle helpers ─────────────────────────────────────────────────────────
function has(role, key) {
    return localPerms[role].includes(key);
}

function toggle(role, key) {
    const idx = localPerms[role].indexOf(key);
    if (idx === -1) {
        localPerms[role].push(key);
    } else {
        localPerms[role].splice(idx, 1);
    }
    saved.value = false;
}

function allSelected(role) {
    return localPerms[role].length === totalModules;
}

function toggleAll(role) {
    if (allSelected(role)) {
        localPerms[role] = [];
    } else {
        const allKeys = MODULE_GROUPS.flatMap(g => g.items.map(m => m.key));
        localPerms[role] = [...allKeys];
    }
    saved.value = false;
}

// ── Save ───────────────────────────────────────────────────────────────────
async function saveAll() {
    saving.value = true;
    error.value  = '';
    saved.value  = false;

    try {
        await Promise.all([
            rolePermissionService.updateRole('manager', localPerms.manager),
            rolePermissionService.updateRole('cashier', localPerms.cashier),
        ]);
        saved.value = true;
        setTimeout(() => (saved.value = false), 4000);
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Speichern fehlgeschlagen.';
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from,
.fade-leave-to     { opacity: 0; }
</style>
