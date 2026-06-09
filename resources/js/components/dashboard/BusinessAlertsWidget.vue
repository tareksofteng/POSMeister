<template>
    <!--
        BusinessAlertsWidget — Phase AB-2 dashboard surface.

        Three stacked tiles that turn the silent notification feed into
        the loudest signal on the dashboard:

          1. Critical    — count of critical-severity, unresolved alerts.
                           Red pulsing dot when > 0.
          2. Unresolved  — count of every unresolved alert across all 9
                           detector domains. Lights up amber when > 5.
          3. Top Recurring — the alert code firing most often this week,
                           so the admin can tune it.

        Below the tiles, a scrollable list of the 5 highest-urgency
        unresolved notifications with one-click actions ("Open" / "Ack").
        Same .notif-rail vocabulary as the Notification Center so the
        product reads as one design language.

        Branch-aware: respects the Topbar workspace. Switching from "All
        Branches" to "Dhaka" automatically re-fetches the branch-scoped
        slice, just like every other dashboard query.
    -->
    <section class="card business-alerts-widget anim-fade-up">

        <!-- Header — overline + KPI strip + view-all link. -->
        <header class="bw-head">
            <div class="min-w-0">
                <p class="t-overline">{{ t('dashboard.alerts.title', 'Business alerts') }}</p>
                <p class="t-caption mt-0.5">{{ t('dashboard.alerts.subtitle', 'Top issues across every branch you can see.') }}</p>
            </div>
            <RouterLink :to="{ name: 'notifications' }" class="bw-link">
                {{ t('dashboard.viewAll') }}
                <ArrowLongRightIcon class="w-3.5 h-3.5" />
            </RouterLink>
        </header>

        <!-- KPI tiles -->
        <div class="bw-tiles">
            <div :class="['bw-tile', critCount > 0 && 'is-loud']">
                <div class="bw-tile-icon">
                    <span v-if="critCount > 0" class="notif-dot" aria-hidden="true" />
                    <FireIcon v-else class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.alerts.critical', 'Critical') }}</p>
                    <p class="t-kpi mt-0.5">{{ loading ? '—' : critCount }}</p>
                </div>
            </div>

            <div :class="['bw-tile', unresolvedCount > 5 && 'is-warning']">
                <div class="bw-tile-icon">
                    <ExclamationTriangleIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.alerts.unresolved', 'Unresolved') }}</p>
                    <p class="t-kpi mt-0.5">{{ loading ? '—' : unresolvedCount }}</p>
                </div>
            </div>

            <div class="bw-tile">
                <div class="bw-tile-icon">
                    <ArrowPathIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.alerts.topRecurring', 'Top recurring') }}</p>
                    <p
                        class="bw-top-code"
                        :title="topRecurring?.code"
                    >
                        <template v-if="loading">—</template>
                        <template v-else-if="topRecurring">
                            {{ topRecurring.code }}
                            <span class="bw-top-count">×{{ topRecurring.occurrences }}</span>
                        </template>
                        <template v-else>{{ t('dashboard.alerts.noneThisWeek', 'None this week') }}</template>
                    </p>
                </div>
            </div>
        </div>

        <!-- Top 5 list -->
        <div v-if="loading" class="bw-list-loading">
            <Skeleton v-for="i in 3" :key="i" variant="row" />
        </div>

        <EmptyState
            v-else-if="!visible.length"
            size="sm"
            tone="emerald"
            :icon="CheckCircleIcon"
            :title="t('dashboard.alerts.empty', 'No business alerts')"
            :description="t('dashboard.alerts.emptyHint', 'When inventory, finance, or operations require attention, they appear here first.')"
        />

        <ul v-else class="bw-list">
            <li v-for="n in visible" :key="n.id" class="bw-row">
                <span :class="['notif-rail', `notif-rail-${n.severity || 'info'}`]" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="t-overline">{{ n.category }}</span>
                        <span :class="['status-pill', sevPill(n.severity)]">{{ n.severity }}</span>
                    </div>
                    <p class="bw-row-title">{{ n.title }}</p>
                    <p class="bw-row-msg">{{ n.message }}</p>
                </div>
                <div class="bw-row-actions">
                    <RouterLink
                        v-for="(a, ai) in primaryAction(n)"
                        :key="ai"
                        :to="{ name: a.route, params: a.params || {} }"
                        class="notif-action-lg is-primary"
                    >
                        {{ t(a.label) }}
                    </RouterLink>
                    <button
                        v-if="!n.acked_at"
                        @click="ack(n)"
                        class="bw-ack-btn"
                        :title="t('notifications.ack')"
                    >
                        <CheckIcon class="w-3.5 h-3.5" />
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import {
    ArrowLongRightIcon, ArrowPathIcon, CheckCircleIcon, CheckIcon,
    ExclamationTriangleIcon, FireIcon,
} from '@heroicons/vue/24/outline';
import { useNotificationsStore } from '@/stores/notifications';
import { notificationService } from '@/services/notificationService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const router = useRouter();
const store = useNotificationsStore();

// Pre-compute valid route-name Set ONCE so the per-render filter on
// action links is O(1) and stale notification rows can't crash this
// widget the way they used to crash the dropdown.
const validRouteNames = new Set(
    router.getRoutes().map(r => r.name).filter(Boolean)
);

// ── Local state — headline KPIs from /analytics, top-5 list from /list. ──

const loading = ref(true);
const summary = ref(null);
const topRecurring = ref(null);

const critCount = computed(() => summary.value?.critical ?? 0);
const unresolvedCount = computed(() => summary.value?.unresolved ?? 0);

const visible = computed(() => {
    return store.items
        .filter(n => !n.acked_at && !n.archived_at)
        .filter(n => ['critical', 'danger', 'warning'].includes(n.severity))
        .slice(0, 5);
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

function primaryAction(n) {
    if (!Array.isArray(n.actions)) return [];
    return n.actions
        .filter(a => a && a.route && validRouteNames.has(a.route))
        .slice(0, 1);
}

async function ack(n) {
    await store.ack(n.id);
}

// ── Load — analytics + live list in parallel. ──
async function load() {
    loading.value = true;
    try {
        const [a] = await Promise.all([
            notificationService.analytics().catch(() => null),
            store.fetch(),
        ]);
        const data = a?.data?.data ?? null;
        if (data) {
            summary.value = data.summary ?? null;
            topRecurring.value = (data.top_recurring ?? [])[0] ?? null;
        }
    } finally {
        loading.value = false;
    }
}

// Poll alongside the bell so the widget stays current without being chatty.
let pollTimer = null;
onMounted(() => {
    load();
    pollTimer = setInterval(load, 120_000);  // 2-minute refresh
});
onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
@reference '../../../css/app.css';

.business-alerts-widget {
    padding: 1rem 1.125rem 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.bw-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.bw-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgb(67 56 202);
    transition: background-color var(--motion-fast) var(--motion-out);
    flex-shrink: 0;
}
.bw-link:hover { background: rgb(238 242 255); }
html.dark .bw-link { color: rgb(165 180 252); }
html.dark .bw-link:hover { background: rgb(67 56 202 / 0.18); }

/* Three KPI tiles — equal width on desktop, stack on phones. */
.bw-tiles {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
}
@media (min-width: 640px) {
    .bw-tiles { grid-template-columns: repeat(3, 1fr); }
}
.bw-tile {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 0.875rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
    border-radius: 0.625rem;
    min-width: 0;
}
.bw-tile.is-loud {
    background: linear-gradient(180deg, rgb(255 241 242), rgb(255 228 230));
    border-color: rgb(254 205 211);
}
.bw-tile.is-warning {
    background: linear-gradient(180deg, rgb(255 251 235), rgb(254 243 199));
    border-color: rgb(253 230 138);
}
html.dark .bw-tile.is-loud {
    background: linear-gradient(180deg, rgb(159 18 57 / 0.18), rgb(159 18 57 / 0.12));
    border-color: rgb(244 63 94 / 0.4);
}
html.dark .bw-tile.is-warning {
    background: linear-gradient(180deg, rgb(180 83 9 / 0.22), rgb(180 83 9 / 0.16));
    border-color: rgb(245 158 11 / 0.4);
}
.bw-tile-icon {
    width: 32px; height: 32px;
    border-radius: 0.5rem;
    display: grid; place-items: center;
    background: var(--surface-raised);
    color: var(--text-secondary);
    flex-shrink: 0;
    position: relative;
}
.bw-tile.is-loud .bw-tile-icon { color: rgb(190 18 60); }
.bw-tile.is-warning .bw-tile-icon { color: rgb(146 64 14); }

/* Top recurring code — monospace for code readability. */
.bw-top-code {
    margin-top: 0.125rem;
    font-family: ui-monospace, SF Mono, Menlo, monospace;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bw-top-count {
    font-family: var(--font-sans);
    font-size: 0.6875rem;
    font-weight: 700;
    margin-left: 0.25rem;
    color: var(--text-tertiary);
}

/* List */
.bw-list-loading {
    display: flex; flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem 0;
}
.bw-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
}
.bw-row {
    display: flex;
    align-items: stretch;
    gap: 0.75rem;
    padding: 0.625rem 0;
    border-top: 1px solid var(--border-subtle);
}
.bw-row:first-child { border-top: 0; }

.bw-row-title {
    margin-top: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
}
.bw-row-msg {
    margin-top: 0.125rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.bw-row-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}
.bw-ack-btn {
    width: 28px; height: 28px;
    display: inline-flex;
    align-items: center; justify-content: center;
    border-radius: 0.375rem;
    color: var(--text-tertiary);
    transition: color var(--motion-fast) var(--motion-out), background-color var(--motion-fast) var(--motion-out);
}
.bw-ack-btn:hover {
    color: rgb(4 120 87);
    background: rgb(236 253 245);
}
html.dark .bw-ack-btn:hover {
    color: rgb(110 231 183);
    background: rgb(5 150 105 / 0.25);
}

@media (max-width: 639px) {
    .bw-row-actions { flex-direction: column; align-items: flex-end; }
    .bw-row-msg { -webkit-line-clamp: 3; }
}
</style>
