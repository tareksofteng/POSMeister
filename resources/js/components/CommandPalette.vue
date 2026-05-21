<template>
    <Teleport to="body">
        <Transition name="cp-fade">
            <div v-if="open" class="cp-backdrop" @click="close" @keydown.esc="close">
                <div class="cp-panel" @click.stop>
                    <div class="cp-search">
                        <MagnifyingGlassIcon class="w-5 h-5 text-slate-400 flex-shrink-0" />
                        <input
                            ref="inputEl"
                            v-model="query"
                            @keydown="onKey"
                            type="text"
                            :placeholder="t('palette.placeholder')"
                            class="cp-input"
                        />
                        <kbd class="cp-kbd">ESC</kbd>
                    </div>

                    <div class="cp-results" ref="listEl">
                        <template v-for="(group, gi) in grouped" :key="group.section">
                            <p class="cp-group-label">{{ group.section }}</p>
                            <button
                                v-for="(cmd, ci) in group.items"
                                :key="cmd.id"
                                @click="run(cmd)"
                                @mouseenter="highlight = flatIndex(gi, ci)"
                                :class="['cp-item', flatIndex(gi, ci) === highlight ? 'is-active' : '']"
                            >
                                <component :is="cmd.icon" v-if="cmd.icon" class="w-4 h-4 flex-shrink-0 text-slate-400" />
                                <span class="flex-1 text-left truncate">{{ cmd.label }}</span>
                                <span v-if="cmd.hint" class="text-[10px] text-slate-400 font-mono">{{ cmd.hint }}</span>
                            </button>
                        </template>
                        <p v-if="filtered.length === 0" class="cp-empty">{{ t('palette.empty') }}</p>
                    </div>

                    <div class="cp-footer">
                        <span class="cp-hint"><kbd class="cp-kbd-sm">↑↓</kbd> {{ t('palette.navigate') }}</span>
                        <span class="cp-hint"><kbd class="cp-kbd-sm">↵</kbd> {{ t('palette.select') }}</span>
                        <span class="cp-hint"><kbd class="cp-kbd-sm">ESC</kbd> {{ t('palette.close') }}</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, markRaw } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import {
    MagnifyingGlassIcon, Squares2X2Icon, ShoppingCartIcon, ChartBarIcon,
    UsersIcon, ArchiveBoxIcon, BanknotesIcon, BookOpenIcon, UserGroupIcon,
    ReceiptPercentIcon, TruckIcon, ClipboardDocumentListIcon, Cog6ToothIcon,
    SunIcon, ArrowRightOnRectangleIcon, BuildingStorefrontIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const router = useRouter();
const auth   = useAuthStore();
const theme  = useThemeStore();

const open      = ref(false);
const query     = ref('');
const highlight = ref(0);
const inputEl   = ref(null);
const listEl    = ref(null);

function show()  { open.value = true; query.value = ''; highlight.value = 0; nextTick(() => inputEl.value?.focus()); }
function close() { open.value = false; }
defineExpose({ show, close });

const commands = computed(() => {
    const has = (perm) => auth.canAccess?.(perm) ?? true;
    return [
        { id: 'go.dashboard',    section: t('palette.sections.navigation'), label: t('menu.dashboard'),         icon: markRaw(Squares2X2Icon),         to: 'dashboard' },
        { id: 'go.pos',          section: t('palette.sections.navigation'), label: t('menu.pos'),                icon: markRaw(ShoppingCartIcon),       to: 'pos',          requires: 'pos' },
        { id: 'go.sales',        section: t('palette.sections.navigation'), label: t('menu.sales'),              icon: markRaw(ShoppingCartIcon),       to: 'sales',        requires: 'sales' },
        { id: 'go.purchases',    section: t('palette.sections.navigation'), label: t('menu.purchases'),          icon: markRaw(TruckIcon),              to: 'purchases',    requires: 'purchases' },
        { id: 'go.customers',    section: t('palette.sections.navigation'), label: t('menu.customers'),          icon: markRaw(UsersIcon),              to: 'customers',    requires: 'customers' },
        { id: 'go.inventory',    section: t('palette.sections.navigation'), label: t('menu.inventory'),          icon: markRaw(ArchiveBoxIcon),         to: 'inventory',    requires: 'inventory' },
        { id: 'go.finance',      section: t('palette.sections.navigation'), label: t('menu.financialDashboard'), icon: markRaw(ChartBarIcon),           to: 'finance-dashboard', requires: 'finance' },
        { id: 'go.accounting',   section: t('palette.sections.navigation'), label: t('menu.accountingDashboard'),icon: markRaw(BookOpenIcon),           to: 'accounting-dashboard', requires: 'accounting' },
        { id: 'go.hrm',          section: t('palette.sections.navigation'), label: t('menu.workforceIntel'),     icon: markRaw(UserGroupIcon),          to: 'hrm-workforce', requires: 'hrm' },
        { id: 'go.crm',          section: t('palette.sections.navigation'), label: t('menu.crmIntelligence'),    icon: markRaw(UserGroupIcon),          to: 'crm-intelligence', requires: 'crm' },
        { id: 'go.oms',          section: t('palette.sections.navigation'), label: t('menu.omsOrders'),          icon: markRaw(BuildingStorefrontIcon), to: 'oms-orders',   requires: 'oms' },
        { id: 'go.expenses',     section: t('palette.sections.navigation'), label: t('menu.expenseList'),        icon: markRaw(ReceiptPercentIcon),     to: 'expense-list', requires: 'expenses' },

        { id: 'act.newSale',     section: t('palette.sections.actions'),    label: t('palette.actions.newSale'),     icon: markRaw(ShoppingCartIcon),      to: 'sales-new',       requires: 'sales' },
        { id: 'act.newPurchase', section: t('palette.sections.actions'),    label: t('palette.actions.newPurchase'), icon: markRaw(TruckIcon),             to: 'purchase-create', requires: 'purchases' },
        { id: 'act.reorder',     section: t('palette.sections.actions'),    label: t('palette.actions.reorder'),     icon: markRaw(ClipboardDocumentListIcon), to: 'inventory-reorder', requires: 'inventory' },

        { id: 'sys.theme',       section: t('palette.sections.system'),     label: t('palette.actions.toggleTheme'), icon: markRaw(SunIcon),               run: () => theme.toggle() },
        { id: 'sys.settings',    section: t('palette.sections.system'),     label: t('menu.settings'),               icon: markRaw(Cog6ToothIcon),         to: 'settings',     requires: 'admin' },
        { id: 'sys.health',      section: t('palette.sections.system'),     label: t('palette.actions.health'),      icon: markRaw(BanknotesIcon),         to: 'system-health' },
        { id: 'sys.logout',      section: t('palette.sections.system'),     label: t('common.logout'),               icon: markRaw(ArrowRightOnRectangleIcon), run: async () => { await auth.logout(); router.push({ name: 'login' }); } },
    ].filter(c => !c.requires || c.requires === 'admin' ? (c.requires !== 'admin' || auth.userRole === 'admin') : has(c.requires));
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return commands.value;
    return commands.value.filter(c => c.label.toLowerCase().includes(q));
});

const grouped = computed(() => {
    const map = new Map();
    for (const c of filtered.value) {
        if (!map.has(c.section)) map.set(c.section, []);
        map.get(c.section).push(c);
    }
    return Array.from(map.entries()).map(([section, items]) => ({ section, items }));
});

function flatIndex(gi, ci) {
    let n = 0;
    for (let i = 0; i < gi; i++) n += grouped.value[i].items.length;
    return n + ci;
}

function flatList() { return grouped.value.flatMap(g => g.items); }

function onKey(e) {
    if (e.key === 'ArrowDown')      { e.preventDefault(); highlight.value = Math.min(highlight.value + 1, flatList().length - 1); scrollHighlightIntoView(); }
    else if (e.key === 'ArrowUp')   { e.preventDefault(); highlight.value = Math.max(highlight.value - 1, 0); scrollHighlightIntoView(); }
    else if (e.key === 'Enter')     { e.preventDefault(); const cmd = flatList()[highlight.value]; if (cmd) run(cmd); }
    else if (e.key === 'Escape')    { close(); }
}

function scrollHighlightIntoView() {
    nextTick(() => listEl.value?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
}

async function run(cmd) {
    close();
    if (cmd.to) router.push({ name: cmd.to });
    else if (cmd.run) await cmd.run();
}

watch(query, () => { highlight.value = 0; });

// Global Ctrl+K / Cmd+K + custom open event from the topbar trigger.
function onGlobalKey(e) {
    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        open.value ? close() : show();
    }
}
function onOpenEvent() { show(); }

onMounted(() => {
    document.addEventListener('keydown', onGlobalKey);
    window.addEventListener('posmeister:palette:open', onOpenEvent);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onGlobalKey);
    window.removeEventListener('posmeister:palette:open', onOpenEvent);
});
</script>

<style scoped>
@reference '../../css/app.css';

.cp-backdrop {
    @apply fixed inset-0 z-[100] flex items-start justify-center pt-[12vh] px-4;
    background: rgb(15 23 42 / 0.55);
    backdrop-filter: blur(4px);
}
.cp-panel {
    @apply w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden;
    border: 1px solid rgb(226 232 240);
}
.cp-search {
    @apply flex items-center gap-3 px-4 py-3 border-b border-slate-100;
}
.cp-input {
    @apply flex-1 text-base bg-transparent outline-none placeholder:text-slate-400;
}
.cp-results {
    @apply py-2 max-h-[50vh] overflow-y-auto;
}
.cp-group-label {
    @apply px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400;
}
.cp-item {
    @apply w-full flex items-center gap-3 px-4 py-2 text-sm text-slate-700 cursor-pointer transition-colors;
}
.cp-item.is-active {
    @apply bg-indigo-50 text-indigo-700;
}
.cp-empty {
    @apply px-4 py-12 text-center text-sm text-slate-400 italic;
}
.cp-footer {
    @apply flex items-center gap-4 px-4 py-2 border-t border-slate-100 bg-slate-50/60;
}
.cp-hint {
    @apply flex items-center gap-1 text-[10px] text-slate-500 font-medium;
}
.cp-kbd {
    @apply text-[10px] font-mono font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded px-1.5 py-0.5;
}
.cp-kbd-sm {
    @apply text-[10px] font-mono font-semibold text-slate-500 bg-white border border-slate-200 rounded px-1 py-0.5;
}

.cp-fade-enter-active, .cp-fade-leave-active { transition: opacity 150ms ease; }
.cp-fade-enter-from, .cp-fade-leave-to { opacity: 0; }
.cp-fade-enter-active .cp-panel { animation: cpSlideIn 200ms cubic-bezier(0.22, 1, 0.36, 1); }
@keyframes cpSlideIn {
    from { opacity: 0; transform: translateY(-12px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
