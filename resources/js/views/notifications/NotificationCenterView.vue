<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-5xl mx-auto anim-fade-in">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ t('notifications.module') }}</p>
                <h1 class="h1-display">{{ t('notifications.centerTitle') }}</h1>
                <p class="mt-1.5 t-body">{{ t('notifications.centerSubtitle') }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <Button
                    variant="secondary"
                    size="sm"
                    :disabled="store.unread === 0"
                    @click="store.markAllRead()"
                >
                    {{ t('notifications.markAllRead') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    :disabled="!hasRead"
                    @click="onClearRead"
                >
                    {{ t('notifications.clearRead') }}
                </Button>
                <Button
                    variant="danger"
                    size="sm"
                    :disabled="store.items.length === 0"
                    @click="onClearAll"
                >
                    {{ t('notifications.clearAll') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    :to="{ name: 'notification-digest' }"
                    :leading-icon="DocumentTextIcon"
                >
                    {{ t('notifications.digest.title', 'Digest') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    :to="{ name: 'notification-analytics' }"
                    :leading-icon="ChartBarIcon"
                >
                    {{ t('notifications.analytics.title', 'Analytics') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    :to="{ name: 'notification-rules' }"
                    :leading-icon="AdjustmentsHorizontalIcon"
                >
                    {{ t('notifications.rules.title', 'Rules') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    :to="{ name: 'notification-devices' }"
                    :leading-icon="DevicePhoneMobileIcon"
                >
                    {{ t('push.devices.title', 'Devices') }}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    :to="{ name: 'notification-preferences' }"
                    :leading-icon="CogIcon"
                >
                    {{ t('notifications.preferences') }}
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :loading="store.loading"
                    :leading-icon="ArrowPathIcon"
                    @click="store.fetch()"
                >
                    {{ t('common.refresh') }}
                </Button>
            </div>
        </header>

        <!-- Filter chips — category row + severity row. Premium pill chrome,
             active state highlighted via .is-active. -->
        <div class="card chip-toolbar">
            <div class="chip-row">
                <p class="t-overline mr-2 hidden sm:inline">{{ t('notifications.categoryLabel', 'Category') }}</p>
                <button
                    v-for="c in categories"
                    :key="c.key"
                    @click="filterCategory = filterCategory === c.key ? null : c.key"
                    :class="['filter-chip', filterCategory === c.key && 'is-active']"
                >
                    {{ c.label }}
                    <span class="filter-chip-count">{{ counts[c.key] || 0 }}</span>
                </button>
            </div>
            <div class="chip-row">
                <p class="t-overline mr-2 hidden sm:inline">{{ t('notifications.severityLabel', 'Severity') }}</p>
                <button
                    v-for="s in severities"
                    :key="s.key"
                    @click="filterSeverity = filterSeverity === s.key ? null : s.key"
                    :class="['status-pill', sevPill(s.key), 'cursor-pointer transition-transform', filterSeverity === s.key && 'is-pressed']"
                >
                    {{ s.label }}
                </button>
            </div>
        </div>

        <!-- List card -->
        <section class="card overflow-hidden">
            <div v-if="store.loading && !visible.length" class="p-4 space-y-3">
                <Skeleton v-for="i in 5" :key="i" variant="row" />
            </div>

            <EmptyState
                v-else-if="!visible.length"
                size="md"
                tone="emerald"
                :icon="CheckCircleIcon"
                :title="t('notifications.empty')"
                :description="filterCategory || filterSeverity ? t('notifications.emptyFilterHint', 'Try clearing the filters above to see other notifications.') : ''"
            >
                <template v-if="filterCategory || filterSeverity" #action>
                    <Button variant="secondary" size="sm" @click="filterCategory = null; filterSeverity = null">
                        {{ t('common.clearFilters', 'Clear filters') }}
                    </Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li
                    v-for="n in visible"
                    :key="n.id"
                    :class="['notif-row', !n.read_at && 'is-unread']"
                >
                    <div class="flex items-start gap-3">
                        <!-- Severity rail — vertical accent matches the dropdown. -->
                        <span :class="['notif-rail', `notif-rail-${n.severity || 'info'}`]" />

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="t-overline">{{ n.category }}</p>
                                <span :class="['status-pill', sevPill(n.severity)]">{{ n.severity }}</span>
                                <span
                                    v-if="n.escalation_level > 0"
                                    class="status-pill status-pill-warning"
                                    :title="t('notifications.escalationHint', 'Escalation level')"
                                >
                                    ↑ {{ n.escalation_level }}
                                </span>
                                <span class="text-[10px] text-slate-400 ml-auto">{{ formatDate(n.created_at) }}</span>
                            </div>

                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ n.title }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300 mt-0.5">{{ n.message }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <!-- Same route-name guard as the dropdown — a stale name
                                     elsewhere would crash the whole list. -->
                                <RouterLink
                                    v-for="(a, ai) in validActions(n.actions)"
                                    :key="ai"
                                    :to="{ name: a.route, params: a.params || {} }"
                                    @click="store.markRead(n.id)"
                                    :class="['notif-action-lg', a.type === 'primary' && 'is-primary']"
                                >
                                    {{ t(a.label) }}
                                </RouterLink>
                                <button
                                    v-if="!n.acked_at"
                                    @click="store.ack(n.id)"
                                    class="notif-link-btn text-emerald-700 dark:text-emerald-400"
                                >
                                    {{ t('notifications.ack') }}
                                </button>
                                <button
                                    @click="store.archive(n.id)"
                                    class="notif-link-btn is-muted ml-auto"
                                >
                                    {{ t('notifications.archive') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { ArrowPathIcon, CogIcon, ChartBarIcon, AdjustmentsHorizontalIcon, DocumentTextIcon, DevicePhoneMobileIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { useNotificationsStore } from '@/stores/notifications';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Button     from '@/components/ui/Button.vue';

const { t, locale } = useI18n();
const router = useRouter();
const store = useNotificationsStore();
const filterCategory = ref(null);
const filterSeverity = ref(null);

// Same defensive route-name set as the dropdown — keeps stale action links
// from crashing the list when a route is removed or renamed.
const validRouteNames = new Set(
    router.getRoutes().map(r => r.name).filter(Boolean)
);
function validActions(actions) {
    if (!Array.isArray(actions)) return [];
    return actions.filter(a => a && a.route && validRouteNames.has(a.route));
}

const categories = computed(() => [
    { key: 'inventory', label: t('notifications.category.inventory') },
    { key: 'sales',     label: t('notifications.category.sales') },
    { key: 'finance',   label: t('notifications.category.finance') },
    { key: 'hrm',       label: t('notifications.category.hrm') },
    { key: 'system',    label: t('notifications.category.system') },
]);
const severities = computed(() => [
    { key: 'info',     label: t('notifications.severity.info') },
    { key: 'warning',  label: t('notifications.severity.warning') },
    { key: 'danger',   label: t('notifications.severity.danger') },
    { key: 'critical', label: t('notifications.severity.critical') },
]);

const visible = computed(() => store.items.filter((n) => {
    if (filterCategory.value && n.category !== filterCategory.value) return false;
    if (filterSeverity.value && n.severity !== filterSeverity.value) return false;
    return true;
}));

const counts = computed(() => {
    const c = {};
    store.items.forEach((n) => { c[n.category] = (c[n.category] || 0) + 1; });
    return c;
});

function sevPill(s) {
    return ({
        info:     'status-pill-info',
        success:  'status-pill-success',
        warning:  'status-pill-warning',
        danger:   'status-pill-danger',
        critical: 'status-pill-danger',
    })[s] || 'status-pill-neutral';
}
function formatDate(iso) {
    if (!iso) return '';
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}

const hasRead = computed(() => store.items.some(n => n.read_at));

async function onClearRead() {
    if (!window.confirm(t('notifications.confirmClearRead'))) return;
    await store.clearRead();
}
async function onClearAll() {
    if (!window.confirm(t('notifications.confirmClearAll'))) return;
    await store.clearAll();
}

onMounted(() => store.fetch());
</script>

<style scoped>
@reference '../../../css/app.css';

/* Filter toolbar — two rows of chips (category + severity). */
.chip-toolbar {
    padding: 0.75rem 0.875rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.chip-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    border: 1px solid var(--border-default);
    transition:
        background-color var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out),
        border-color     var(--motion-fast) var(--motion-out);
}
.filter-chip:hover {
    border-color: var(--border-strong);
    color: var(--text-primary);
}
.filter-chip.is-active {
    background: rgb(79 70 229);
    color: white;
    border-color: rgb(79 70 229);
    box-shadow: var(--elev-1);
}
.filter-chip-count {
    font-size: 0.625rem;
    opacity: 0.8;
}

/* Severity chip "pressed" state when used as a filter. */
.status-pill.is-pressed {
    box-shadow: inset 0 0 0 2px currentColor;
    transform: scale(0.98);
}

/* List row — same rail vocabulary as the dropdown, more roomy. */
.notif-row {
    padding: 1rem;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.notif-row:hover { background: rgb(248 250 252); }
html.dark .notif-row:hover { background: rgb(30 41 59 / 0.4); }
.notif-row.is-unread { background: rgb(238 242 255 / 0.55); }
html.dark .notif-row.is-unread { background: rgb(67 56 202 / 0.12); }

.notif-rail {
    margin-top: 0.375rem;
    width: 3px;
    align-self: stretch;
    min-height: 32px;
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

/* Action button — slightly larger than the dropdown chip. */
.notif-action-lg {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgb(226 232 240);
    color: rgb(51 65 85);
    transition: background-color var(--motion-fast) var(--motion-out);
}
.notif-action-lg:hover { background: rgb(203 213 225); }
html.dark .notif-action-lg {
    background: rgb(51 65 85);
    color: rgb(226 232 240);
}
html.dark .notif-action-lg:hover { background: rgb(71 85 105); }
.notif-action-lg.is-primary {
    background: rgb(79 70 229);
    color: white;
}
.notif-action-lg.is-primary:hover { background: rgb(67 56 202); }

.notif-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
    transition:
        background-color var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out);
}
.notif-link-btn.is-muted { color: var(--text-tertiary); }
.notif-link-btn.is-muted:hover { background: rgb(241 245 249); color: var(--text-primary); }
html.dark .notif-link-btn.is-muted:hover { background: rgb(30 41 59); }
</style>
