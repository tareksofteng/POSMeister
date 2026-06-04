<template>
    <!--
        Mobile bottom tab bar — the single biggest UX cue that turns a
        web app into a "native-feeling" app. Five anchors that cover 90%
        of cashier traffic, plus a "More" pill that opens the existing
        sidebar drawer for the long tail of modules.

        Hidden from lg+ — desktop keeps the left sidebar.
    -->
    <nav
        class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-200/70 dark:border-slate-800 shadow-[0_-4px_20px_-8px_rgba(15,23,42,0.08)] pb-safe"
        role="navigation"
        :aria-label="t('nav.bottomNav')"
    >
        <div class="grid grid-cols-5 max-w-2xl mx-auto">
            <button v-for="tab in tabs" :key="tab.id"
                type="button"
                @click="tab.onClick"
                :class="['bottom-tab', tab.active ? 'is-active' : '']"
                :aria-current="tab.active ? 'page' : undefined"
            >
                <span class="tab-icon">
                    <component :is="tab.icon" class="w-5 h-5" />
                </span>
                <span class="tab-label">{{ tab.label }}</span>
                <span v-if="tab.active" class="tab-indicator" aria-hidden="true" />
            </button>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import {
    HomeIcon, ShoppingCartIcon, ClipboardDocumentListIcon,
    ArchiveBoxIcon, Squares2X2Icon,
} from '@heroicons/vue/24/outline';
import {
    HomeIcon as HomeSolid,
    ShoppingCartIcon as PosSolid,
    ClipboardDocumentListIcon as SalesSolid,
    ArchiveBoxIcon as InvSolid,
} from '@heroicons/vue/24/solid';

const { t } = useI18n();
const route  = useRoute();
const router = useRouter();

const emit = defineEmits(['open-more']);

// Match by route name so deep-linked sub-routes (e.g. sale-invoice, sale-record)
// still light up the parent Sales tab.
const routeName = computed(() => String(route.name || ''));
const startsWith = (...names) => names.some((n) => routeName.value === n || routeName.value.startsWith(n + '-'));

const tabs = computed(() => {
    const dashActive  = startsWith('dashboard');
    const posActive   = startsWith('pos');
    const salesActive = startsWith('sales', 'sale');
    const invActive   = startsWith('inventory', 'products', 'product', 'stock');

    const go = (name) => () => router.push({ name }).catch(() => {});

    return [
        { id: 'dashboard', label: t('nav.dashboard'), active: dashActive,  onClick: go('dashboard'),
          icon: dashActive  ? HomeSolid  : HomeIcon },
        { id: 'pos',       label: t('nav.pos'),       active: posActive,   onClick: go('pos'),
          icon: posActive   ? PosSolid   : ShoppingCartIcon },
        { id: 'sales',     label: t('nav.sales'),     active: salesActive, onClick: go('sales'),
          icon: salesActive ? SalesSolid : ClipboardDocumentListIcon },
        { id: 'inventory', label: t('nav.inventory'), active: invActive,   onClick: go('inventory'),
          icon: invActive   ? InvSolid   : ArchiveBoxIcon },
        { id: 'more',      label: t('nav.more'),      active: false,       onClick: () => emit('open-more'),
          icon: Squares2X2Icon },
    ];
});
</script>

<style scoped>
@reference '../../../css/app.css';

.bottom-tab {
    @apply relative flex flex-col items-center justify-center gap-0.5 py-2 text-slate-500 dark:text-slate-400
           transition-colors duration-150 select-none;
    min-height: 56px;
}
.bottom-tab:active { transform: scale(0.96); transition-duration: 80ms; }
.bottom-tab.is-active { @apply text-indigo-600 dark:text-indigo-300; }

.tab-icon {
    @apply flex items-center justify-center transition-transform duration-200;
}
.bottom-tab.is-active .tab-icon { transform: translateY(-1px); }

.tab-label {
    @apply text-[10px] font-medium tracking-tight leading-none;
}

.tab-indicator {
    position: absolute;
    top: 6px;
    width: 22px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, #6366f1, #818cf8);
}
</style>
