<template>
    <div class="relative" ref="containerRef">
        <!-- Bell trigger — Phase AA: refined hover, pulsing red dot when
             there's an UNREAD notification (.notif-dot from app.css), badge
             chip for the count. -->
        <button
            @click="toggle"
            class="notif-bell touch-target relative inline-flex items-center justify-center rounded-lg"
            :aria-label="t('notifications.title')"
            :aria-expanded="open"
            :aria-haspopup="true"
        >
            <BellIcon class="w-5 h-5" />
            <span
                v-if="store.unread > 0"
                class="notif-badge absolute -top-0.5 -right-0.5"
            >
                {{ store.unread > 99 ? '99+' : store.unread }}
            </span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="open" class="notif-panel" role="dialog" :aria-label="t('notifications.title')">

                <!-- Header — premium surface with overline + actions cluster. -->
                <header class="notif-panel-head">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-tight">{{ t('notifications.title') }}</p>
                        <p class="t-caption mt-0.5">{{ t('notifications.unread', { n: store.unread }) }}</p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button
                            v-if="store.unread > 0"
                            @click="store.markAllRead()"
                            class="notif-link-btn"
                        >
                            {{ t('notifications.markAllRead') }}
                        </button>
                        <button
                            v-if="store.items.length > 0"
                            @click="onClearRead"
                            class="notif-link-btn is-muted"
                        >
                            {{ t('notifications.clearRead') }}
                        </button>
                    </div>
                </header>

                <!-- Body — scroll container, empty state, list. -->
                <div class="notif-panel-body">
                    <EmptyState
                        v-if="!store.items.length"
                        size="sm"
                        tone="emerald"
                        :icon="CheckCircleIcon"
                        :title="t('notifications.empty')"
                    />

                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li
                            v-for="n in store.items.slice(0, 20)"
                            :key="n.id"
                            :class="['notif-item', !n.read_at && 'is-unread']"
                        >
                            <div class="flex items-start gap-2.5">
                                <!-- Severity rail — coloured priority chip per .notif-priority-* -->
                                <span :class="['notif-rail', `notif-rail-${n.severity || 'info'}`]" />

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="t-overline">{{ n.category }}</p>
                                        <span :class="['status-pill', sevPill(n.severity)]">
                                            {{ n.severity }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100 leading-snug">{{ n.title }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2">{{ n.message }}</p>

                                    <!-- Action row — Only render RouterLinks when the action
                                         carries a route name that still exists. A stale name
                                         (e.g. `inventory-reorder` after a rename) makes
                                         router.resolve() throw, which kills the dropdown and
                                         floods the console with "Cannot read properties of
                                         undefined (reading 'href')". Filtering at the
                                         pre-computed Set is O(1) per action. -->
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <RouterLink
                                            v-for="(a, ai) in validActions(n.actions)"
                                            :key="ai"
                                            :to="{ name: a.route, params: a.params || {} }"
                                            @click="store.markRead(n.id); open = false;"
                                            :class="['notif-action', a.type === 'primary' && 'is-primary']"
                                        >
                                            {{ t(a.label) }}
                                        </RouterLink>
                                        <button
                                            v-if="!n.acked_at"
                                            @click="store.ack(n.id)"
                                            class="notif-link-btn ml-auto text-emerald-700 dark:text-emerald-400"
                                        >
                                            {{ t('notifications.ack') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <footer class="notif-panel-foot">
                    <RouterLink
                        :to="{ name: 'notifications' }"
                        @click="open = false"
                        class="notif-link-btn"
                    >
                        {{ t('notifications.viewAll') }}
                        <ArrowLongRightIcon class="w-3.5 h-3.5" />
                    </RouterLink>
                </footer>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { onClickOutside } from '@vueuse/core';
import { BellIcon, CheckCircleIcon, ArrowLongRightIcon } from '@heroicons/vue/24/outline';
import { useNotificationsStore } from '@/stores/notifications';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const router = useRouter();
const store = useNotificationsStore();
const open = ref(false);
const containerRef = ref(null);

// Pre-compute the set of valid route names ONCE so the per-render filter is
// O(1) per action and never has to swallow a thrown router.resolve. This is
// the source of the "Cannot read properties of undefined (reading 'href')"
// console spam — older notification rows stored route names like
// `inventory-reorder` that have since been renamed or deleted.
const validRouteNames = new Set(
    router.getRoutes().map(r => r.name).filter(Boolean)
);

function validActions(actions) {
    if (!Array.isArray(actions)) return [];
    return actions.filter(a => a && a.route && validRouteNames.has(a.route));
}

function toggle() { open.value = !open.value; if (open.value) store.fetch(); }
onClickOutside(containerRef, () => { open.value = false; });

async function onClearRead() {
    if (!window.confirm(t('notifications.confirmClearRead'))) return;
    await store.clearRead();
}

onMounted(() => store.startPolling());
onUnmounted(() => store.stopPolling());

// Severity → .status-pill tone. Single mapping across the product so a
// "critical" alert in the dropdown reads the same as a "critical" badge
// in the Dashboard alert banner.
function sevPill(s) {
    return ({
        info:     'status-pill-info',
        success:  'status-pill-success',
        warning:  'status-pill-warning',
        danger:   'status-pill-danger',
        critical: 'status-pill-danger',
    })[s] || 'status-pill-neutral';
}
</script>

<style scoped>
@reference '../../css/app.css';

/* Bell trigger — premium hover wash. */
.notif-bell {
    color: var(--text-tertiary);
    width: 36px; height: 36px;
    transition:
        color            var(--motion-fast) var(--motion-out),
        background-color var(--motion-fast) var(--motion-out);
}
.notif-bell:hover {
    color: var(--text-primary);
    background: rgb(241 245 249);
}
html.dark .notif-bell:hover { background: rgb(30 41 59); }

/* Dropdown panel — design-system .card surface, elevation 4 for the float. */
.notif-panel {
    position: absolute;
    right: 0; top: 100%;
    margin-top: 0.5rem;
    width: 22rem;
    max-width: 92vw;
    transform-origin: top right;
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
    border-radius: 0.875rem;
    box-shadow: var(--elev-4);
    z-index: 50;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 80vh;
}
.notif-panel-head {
    flex-shrink: 0;
    padding: 0.625rem 0.875rem;
    border-bottom: 1px solid var(--border-default);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.notif-panel-body {
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.notif-panel-foot {
    flex-shrink: 0;
    padding: 0.5rem 0.875rem;
    border-top: 1px solid var(--border-default);
    background: var(--surface-sunken);
    text-align: center;
}

/* Each list item — left rail for severity, hover wash, unread tint. */
.notif-item {
    padding: 0.75rem;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.notif-item:hover { background: rgb(248 250 252); }
html.dark .notif-item:hover { background: rgb(30 41 59 / 0.4); }
.notif-item.is-unread {
    background: rgb(238 242 255 / 0.55);
}
html.dark .notif-item.is-unread {
    background: rgb(67 56 202 / 0.12);
}
.notif-rail {
    margin-top: 0.375rem;
    width: 3px;
    align-self: stretch;
    min-height: 28px;
    border-radius: 999px;
    flex-shrink: 0;
}
.notif-rail-info     { background: rgb(14 165 233); }
.notif-rail-success  { background: rgb(16 185 129); }
.notif-rail-warning  { background: rgb(245 158 11); }
.notif-rail-danger   { background: rgb(244 63 94); }
.notif-rail-critical {
    background: rgb(225 29 72);
    box-shadow: 0 0 0 1px rgba(225, 29, 72, 0.18);
    animation: notif-pulse 2.4s ease-in-out infinite;
}
@media (prefers-reduced-motion: reduce) {
    .notif-rail-critical { animation: none; }
}

/* Action chips inside an item — match button primitive vocabulary. */
.notif-action {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    background: rgb(226 232 240);
    color: rgb(51 65 85);
    transition: background-color var(--motion-fast) var(--motion-out);
}
.notif-action:hover { background: rgb(203 213 225); }
html.dark .notif-action {
    background: rgb(51 65 85);
    color: rgb(226 232 240);
}
html.dark .notif-action:hover { background: rgb(71 85 105); }
.notif-action.is-primary {
    background: rgb(79 70 229);
    color: white;
}
.notif-action.is-primary:hover { background: rgb(67 56 202); }

/* Text-link-button — the small "Mark all read / Clear read / View all" CTAs */
.notif-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgb(67 56 202);
    transition:
        background-color var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out);
}
.notif-link-btn:hover {
    background: rgb(238 242 255);
}
html.dark .notif-link-btn { color: rgb(165 180 252); }
html.dark .notif-link-btn:hover { background: rgb(67 56 202 / 0.18); }
.notif-link-btn.is-muted { color: var(--text-tertiary); }
.notif-link-btn.is-muted:hover { background: rgb(241 245 249); color: var(--text-primary); }
html.dark .notif-link-btn.is-muted:hover { background: rgb(30 41 59); }
</style>
