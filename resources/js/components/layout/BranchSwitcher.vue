<template>
    <!--
        Topbar branch workspace pill.
        Desktop: anchored dropdown beside the language switcher.
        Mobile  (< sm): full-width bottom sheet so the cashier can switch
                       branches with a thumb tap.
    -->
    <div class="relative" ref="anchorRef">
        <!-- Trigger pill -->
        <button
            type="button"
            @click="toggle"
            :class="['pill-trigger', open ? 'is-open' : '', auth.isAdmin && isMainBranch ? 'is-main' : '']"
            :aria-label="t('branchSwitcher.label')"
        >
            <BuildingStorefrontIcon class="w-4 h-4" />
            <span class="hidden sm:inline truncate max-w-[120px]">
                {{ buttonLabel }}
            </span>
            <ChevronDownIcon :class="['w-3 h-3 transition-transform', open ? 'rotate-180' : '']" />
        </button>

        <!-- Desktop dropdown -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
                <div
                    v-if="open && !isMobile"
                    class="bs-dropdown"
                    :style="dropdownStyle"
                    @click.stop
                >
                    <div class="bs-search">
                        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
                        <input
                            v-model="search"
                            ref="searchInputRef"
                            type="text"
                            :placeholder="t('branchSwitcher.searchPlaceholder')"
                            class="flex-1 bg-transparent text-sm focus:outline-none placeholder:text-slate-400"
                        />
                    </div>
                    <ul class="bs-list">
                        <li v-if="auth.isAdmin">
                            <button type="button" @click="onPick(null)" :class="['bs-row', isAllBranches ? 'is-active' : '']">
                                <span class="bs-row-icon"><GlobeAltIcon class="w-4 h-4" /></span>
                                <span class="flex-1 text-left min-w-0">
                                    <span class="block text-sm font-semibold truncate">{{ t('branchSwitcher.allBranches') }}</span>
                                    <span class="block text-[10px] text-slate-500">{{ t('branchSwitcher.allBranchesHint') }}</span>
                                </span>
                                <CheckIcon v-if="isAllBranches" class="w-4 h-4 text-emerald-600" />
                            </button>
                        </li>
                        <li v-for="b in filtered" :key="b.id">
                            <button type="button" @click="onPick(b.id)" :class="['bs-row', branchStore.branchId === b.id ? 'is-active' : '']">
                                <span class="bs-row-icon">
                                    <BuildingStorefrontIcon v-if="!b.is_main" class="w-4 h-4" />
                                    <StarIcon v-else class="w-4 h-4" />
                                </span>
                                <span class="flex-1 text-left min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 truncate">{{ b.name }}</span>
                                    <span class="block text-[10px] text-slate-500 font-mono">{{ b.code }}</span>
                                </span>
                                <span v-if="b.is_main" class="mr-1 text-[9px] uppercase tracking-wider font-bold text-indigo-600">{{ t('branchSwitcher.mainTag') }}</span>
                                <CheckIcon v-if="branchStore.branchId === b.id" class="w-4 h-4 text-emerald-600" />
                            </button>
                        </li>
                        <li v-if="!filtered.length && !branchStore.loading" class="px-3 py-6 text-center text-xs text-slate-400">
                            {{ t('branchSwitcher.empty') }}
                        </li>
                    </ul>
                </div>
            </Transition>

            <!-- Mobile bottom sheet -->
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="open && isMobile"
                     class="fixed inset-0 z-[70] bg-slate-900/55 backdrop-blur-[1px]"
                     @click="close" />
            </Transition>
            <Transition
                enter-active-class="transition-transform duration-250 ease-out"
                enter-from-class="translate-y-full"
                enter-to-class="translate-y-0"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-from-class="translate-y-0"
                leave-to-class="translate-y-full"
            >
                <div v-if="open && isMobile" class="bs-sheet pb-safe">
                    <div class="bs-sheet-grab" />
                    <div class="bs-sheet-head">
                        <BuildingStorefrontIcon class="w-5 h-5 text-indigo-600" />
                        <h3 class="text-sm font-semibold text-slate-900 flex-1">{{ t('branchSwitcher.title') }}</h3>
                        <button @click="close" class="text-slate-400 hover:text-slate-700">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="bs-search">
                        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('branchSwitcher.searchPlaceholder')"
                            class="flex-1 bg-transparent text-sm focus:outline-none placeholder:text-slate-400"
                        />
                    </div>
                    <ul class="bs-list max-h-[55vh] overflow-y-auto">
                        <li v-if="auth.isAdmin">
                            <button type="button" @click="onPick(null)" :class="['bs-row', isAllBranches ? 'is-active' : '']">
                                <span class="bs-row-icon"><GlobeAltIcon class="w-4 h-4" /></span>
                                <span class="flex-1 text-left min-w-0">
                                    <span class="block text-sm font-semibold">{{ t('branchSwitcher.allBranches') }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ t('branchSwitcher.allBranchesHint') }}</span>
                                </span>
                                <CheckIcon v-if="isAllBranches" class="w-4 h-4 text-emerald-600" />
                            </button>
                        </li>
                        <li v-for="b in filtered" :key="b.id">
                            <button type="button" @click="onPick(b.id)" :class="['bs-row', branchStore.branchId === b.id ? 'is-active' : '']">
                                <span class="bs-row-icon">
                                    <StarIcon v-if="b.is_main" class="w-4 h-4" />
                                    <BuildingStorefrontIcon v-else class="w-4 h-4" />
                                </span>
                                <span class="flex-1 text-left min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 truncate">{{ b.name }}</span>
                                    <span class="block text-[11px] text-slate-500 font-mono">{{ b.code }}</span>
                                </span>
                                <span v-if="b.is_main" class="mr-1 text-[9px] uppercase tracking-wider font-bold text-indigo-600">{{ t('branchSwitcher.mainTag') }}</span>
                                <CheckIcon v-if="branchStore.branchId === b.id" class="w-4 h-4 text-emerald-600" />
                            </button>
                        </li>
                    </ul>
                </div>
            </Transition>

            <!-- Confirmation dialog -->
            <Transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="confirmTarget !== undefined" class="bs-confirm-bdr" @click.self="confirmTarget = undefined">
                    <div class="bs-confirm">
                        <h4 class="text-base font-semibold text-slate-900">
                            {{ t('branchSwitcher.confirmTitle', { branch: confirmTargetName }) }}
                        </h4>
                        <p class="mt-1 text-sm text-slate-500">{{ t('branchSwitcher.confirmBody') }}</p>
                        <div class="mt-4 flex items-center justify-end gap-2">
                            <button @click="confirmTarget = undefined" class="bs-btn-ghost" :disabled="branchStore.switching">
                                {{ t('common.cancel') }}
                            </button>
                            <button @click="confirmSwitch" class="bs-btn-primary" :disabled="branchStore.switching">
                                <ArrowPathIcon v-if="branchStore.switching" class="w-3.5 h-3.5 animate-spin" />
                                {{ t('branchSwitcher.confirmAction') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    BuildingStorefrontIcon, ChevronDownIcon, CheckIcon, MagnifyingGlassIcon,
    GlobeAltIcon, XMarkIcon, ArrowPathIcon, StarIcon,
} from '@heroicons/vue/24/outline';
import { useBranchContextStore } from '@/stores/branchContext';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';

const { t } = useI18n();
const { toast } = useAlert();
const branchStore = useBranchContextStore();
const auth        = useAuthStore();

const open    = ref(false);
const search  = ref('');
const searchInputRef = ref(null);
const anchorRef      = ref(null);
const dropdownStyle  = ref({});
const confirmTarget  = ref(undefined);              // undefined = closed; null = "All branches"; number = branch id

const MOBILE_BREAKPOINT = 640;
const isMobile = ref(typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT);
function onResize() { isMobile.value = window.innerWidth < MOBILE_BREAKPOINT; }

const isMainBranch   = computed(() => branchStore.isMainBranch);
const isAllBranches  = computed(() => branchStore.isAllBranches);

const buttonLabel = computed(() => {
    if (isAllBranches.value && auth.isAdmin) return t('branchSwitcher.allBranchesShort');
    return branchStore.displayLabel ?? t('branchSwitcher.label');
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    const list = branchStore.availableList;
    if (!q) return list;
    return list.filter(b =>
        (b.name || '').toLowerCase().includes(q)
        || (b.code || '').toLowerCase().includes(q)
    );
});

const confirmTargetName = computed(() => {
    if (confirmTarget.value === null) return t('branchSwitcher.allBranches');
    const hit = branchStore.availableList.find(b => b.id === confirmTarget.value);
    return hit?.name ?? `Branch #${confirmTarget.value}`;
});

// ── Open / close ───────────────────────────────────────────────────────────

async function toggle() {
    if (open.value) {
        close();
    } else {
        // Fetch available branches lazily so the topbar boot stays cheap.
        await branchStore.loadAvailable();
        positionDropdown();
        open.value = true;
        nextTick(() => searchInputRef.value?.focus());
    }
}

function close() {
    open.value = false;
    search.value = '';
}

function positionDropdown() {
    const el = anchorRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    dropdownStyle.value = {
        top:   `${rect.bottom + window.scrollY + 6}px`,
        right: `${window.innerWidth - rect.right - window.scrollX}px`,
        position: 'absolute',
    };
}

function onOutsideClick(e) {
    if (!open.value) return;
    if (anchorRef.value && anchorRef.value.contains(e.target)) return;
    // Clicks on the dropdown itself stop-propagate via the @click.stop above.
    close();
}

onMounted(() => {
    window.addEventListener('resize', onResize);
    document.addEventListener('click', onOutsideClick);
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    document.removeEventListener('click', onOutsideClick);
});

// ── Pick → confirm → switch ────────────────────────────────────────────────

function onPick(branchId) {
    if (branchId === branchStore.branchId) {
        close();
        return;
    }
    confirmTarget.value = branchId;
}

async function confirmSwitch() {
    const target = confirmTarget.value;
    try {
        await branchStore.switchTo(target);
        toast('success', t('branchSwitcher.switched', { branch: confirmTargetName.value }));
        confirmTarget.value = undefined;
        close();
        // A workspace switch invalidates almost every loaded list/dashboard;
        // a hard reload is the safest path (matches Odoo's "switch company"
        // behaviour). Components opt out by simply re-reading branchStore
        // in their watchers if they want a softer reload later.
        setTimeout(() => window.location.reload(), 280);
    } catch (err) {
        const status = err?.response?.status;
        if (status === 403) {
            toast('error', t('branchSwitcher.denied'));
        } else {
            toast('error', t('common.unexpectedError'));
        }
        confirmTarget.value = undefined;
    }
}

watch(() => auth.isAuthenticated, (isAuthed) => {
    if (isAuthed) branchStore.refreshCurrent();
    else          branchStore.reset();
}, { immediate: true });
</script>

<style scoped>
@reference '../../../css/app.css';

.pill-trigger {
    @apply inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg
           border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200
           bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors;
}
.pill-trigger.is-open  { @apply border-indigo-300 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-200; }
.pill-trigger.is-main  { @apply border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300; }

/* Desktop dropdown */
.bs-dropdown {
    z-index: 60;
    width: 280px;
    max-width: calc(100vw - 24px);
    @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl overflow-hidden;
}
.bs-search {
    @apply flex items-center gap-2 px-3 py-2 border-b border-slate-100 dark:border-slate-800;
}
.bs-list {
    @apply max-h-[360px] overflow-y-auto py-1;
}
.bs-row {
    @apply w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors;
}
.bs-row.is-active {
    @apply bg-indigo-50/60 dark:bg-indigo-900/30;
}
.bs-row-icon {
    @apply w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 grid place-items-center flex-shrink-0;
}
.bs-row.is-active .bs-row-icon {
    @apply bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-200;
}

/* Mobile bottom sheet */
.bs-sheet {
    position: fixed;
    inset: auto 0 0 0;
    z-index: 71;
    @apply bg-white dark:bg-slate-900 rounded-t-2xl shadow-2xl;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}
.bs-sheet-grab {
    @apply w-10 h-1 rounded-full bg-slate-300 dark:bg-slate-700 mx-auto my-2 flex-shrink-0;
}
.bs-sheet-head {
    @apply flex items-center gap-2 px-4 py-2 border-b border-slate-100 dark:border-slate-800;
}

/* Confirm dialog */
.bs-confirm-bdr {
    position: fixed; inset: 0; z-index: 80;
    @apply bg-slate-900/55 backdrop-blur-[2px] grid place-items-center p-4;
}
.bs-confirm {
    @apply bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-sm p-5;
}
.bs-btn-ghost {
    @apply inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200
           bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-50;
}
.bs-btn-primary {
    @apply inline-flex items-center gap-1.5 px-5 py-1.5 text-sm font-semibold text-white
           bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors disabled:opacity-50;
}
</style>
