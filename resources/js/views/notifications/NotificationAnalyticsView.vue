<template>
    <!--
        NotificationAnalyticsView — Phase AB-2.

        Admin-only deep dive into notification health. Consumes the
        structured payload from NotificationAnalyticsService:

          summary               — 4 headline KPIs
          by_category           — grouped count last 7d
          by_priority           — grouped count last 7d
          resolved_vs_unresolved — recovery rate
          top_recurring         — tuning candidates
          avg_resolution_time   — team responsiveness
          branch_comparison     — multi-branch hotspots
          timeline              — 30-day daily sparkline

        Pure visualisation, no inline charts library — uses simple
        CSS bars and the existing design-system primitives. Honours
        the workspace branch context automatically (the backend
        scopes the query).
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-6xl mx-auto anim-fade-in">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ t('notifications.module') }}</p>
                <h1 class="h1-display">{{ t('notifications.analytics.title', 'Notification Health') }}</h1>
                <p class="mt-1.5 t-body">{{ t('notifications.analytics.subtitle', 'How proactively the system is surfacing risks and how quickly the team resolves them.') }}</p>
            </div>
            <Button
                variant="secondary"
                size="sm"
                :loading="loading"
                :leading-icon="ArrowPathIcon"
                @click="load"
            >
                {{ t('common.refresh') }}
            </Button>
        </header>

        <!-- Loading state — 4 KPI skeletons + 2 chart skeletons. -->
        <template v-if="loading && !data">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 anim-stagger">
                <Skeleton v-for="i in 4" :key="i" variant="kpi-card" />
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <Skeleton variant="chart" />
                <Skeleton variant="chart" />
            </div>
        </template>

        <template v-else-if="data">
            <!-- Headline KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 anim-fade-up anim-stagger">
                <div class="card card-kpi">
                    <p class="t-overline">{{ t('notifications.analytics.unresolved', 'Unresolved') }}</p>
                    <p class="t-kpi mt-2">{{ data.summary.unresolved }}</p>
                </div>
                <div :class="['card card-kpi', data.summary.critical > 0 && 'is-critical']">
                    <p class="t-overline">{{ t('notifications.analytics.critical', 'Critical') }}</p>
                    <div class="flex items-center gap-1.5 mt-2">
                        <span v-if="data.summary.critical > 0" class="notif-dot" aria-hidden="true" />
                        <p class="t-kpi">{{ data.summary.critical }}</p>
                    </div>
                </div>
                <div class="card card-kpi">
                    <p class="t-overline">{{ t('notifications.analytics.last24h', 'Last 24h') }}</p>
                    <p class="t-kpi mt-2">{{ data.summary.last_24h }}</p>
                </div>
                <div class="card card-kpi">
                    <p class="t-overline">{{ t('notifications.analytics.avgResolution', 'Avg resolution') }}</p>
                    <p class="t-kpi mt-2">
                        <template v-if="data.avg_resolution_time != null">
                            {{ formatMinutes(data.avg_resolution_time) }}
                        </template>
                        <template v-else>—</template>
                    </p>
                </div>
            </section>

            <!-- Resolution split + timeline -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="card card-analytics">
                    <p class="t-overline">{{ t('notifications.analytics.resolutionRate', 'Resolution rate · last 7 days') }}</p>
                    <div class="mt-4">
                        <div class="flex items-baseline gap-2">
                            <p class="t-kpi">{{ data.resolved_vs_unresolved.pct_resolved }}%</p>
                            <p class="t-caption">{{ data.resolved_vs_unresolved.resolved }} / {{ data.resolved_vs_unresolved.resolved + data.resolved_vs_unresolved.unresolved }}</p>
                        </div>
                        <div class="resolution-bar mt-3" :aria-label="t('notifications.analytics.resolutionRate')">
                            <div
                                class="resolution-bar-fill"
                                :style="{ width: data.resolved_vs_unresolved.pct_resolved + '%' }"
                            />
                        </div>
                        <p class="t-caption mt-2">{{ t('notifications.analytics.resolutionHint', 'Higher = the team is closing alerts faster than the detectors raise them.') }}</p>
                    </div>
                </div>

                <div class="card card-analytics">
                    <p class="t-overline">{{ t('notifications.analytics.timeline', 'Daily volume · last 30 days') }}</p>
                    <div class="timeline-bars mt-4">
                        <div
                            v-for="(d, i) in data.timeline"
                            :key="i"
                            :class="['timeline-bar', d.count === 0 && 'is-zero']"
                            :style="{ height: barHeight(d.count) + '%' }"
                            :title="`${d.date}: ${d.count}`"
                        />
                    </div>
                    <div class="flex items-center justify-between t-caption mt-2">
                        <span>{{ data.timeline[0]?.date }}</span>
                        <span>{{ data.timeline[data.timeline.length - 1]?.date }}</span>
                    </div>
                </div>
            </section>

            <!-- By category + By priority -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="card card-analytics">
                    <p class="t-overline mb-3">{{ t('notifications.analytics.byCategory', 'By category · last 7 days') }}</p>
                    <ul v-if="categoryRows.length" class="dist-list">
                        <li v-for="row in categoryRows" :key="row.key" class="dist-row">
                            <div class="dist-row-head">
                                <span class="dist-row-label">{{ catLabel(row.key) }}</span>
                                <span class="dist-row-count">{{ row.value }}</span>
                            </div>
                            <div class="dist-bar">
                                <div class="dist-bar-fill" :style="{ width: row.pct + '%' }" />
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else size="sm" tone="emerald" :icon="CheckCircleIcon" :title="t('notifications.analytics.quietWeek', 'A quiet week — no alerts raised.')" />
                </div>

                <div class="card card-analytics">
                    <p class="t-overline mb-3">{{ t('notifications.analytics.byPriority', 'By priority · last 7 days') }}</p>
                    <ul v-if="priorityRows.length" class="dist-list">
                        <li v-for="row in priorityRows" :key="row.key" class="dist-row">
                            <div class="dist-row-head">
                                <span :class="['status-pill', sevPill(row.key)]">{{ row.key }}</span>
                                <span class="dist-row-count">{{ row.value }}</span>
                            </div>
                            <div class="dist-bar">
                                <div :class="['dist-bar-fill', `is-${row.key}`]" :style="{ width: row.pct + '%' }" />
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else size="sm" tone="emerald" :icon="CheckCircleIcon" :title="t('notifications.analytics.noPriorityYet', 'No alerts in the period.')" />
                </div>
            </section>

            <!-- Top recurring -->
            <section class="card overflow-hidden">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('notifications.analytics.topRecurring', 'Top recurring · last 7 days') }}</p>
                        <p class="t-caption mt-0.5">{{ t('notifications.analytics.topRecurringHint', 'Codes firing most often. Consider tuning their cooldown or detection threshold.') }}</p>
                    </div>
                </header>
                <ul v-if="data.top_recurring.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="(row, i) in data.top_recurring" :key="row.code" class="recurring-row">
                        <span class="dash-list-rank">#{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="recurring-code">{{ row.code }}</p>
                            <p class="t-caption">
                                {{ t('notifications.analytics.lastSeen', 'Last seen') }}:
                                {{ formatRelative(row.last_seen) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span :class="['status-pill', sevPill(row.last_severity)]">{{ row.last_severity }}</span>
                            <span class="recurring-count">×{{ row.occurrences }}</span>
                        </div>
                    </li>
                </ul>
                <EmptyState v-else size="sm" tone="emerald" :icon="CheckCircleIcon" :title="t('notifications.analytics.noRecurring', 'No repeating alerts.')" />
            </section>

            <!-- Branch comparison (only useful when there's more than one) -->
            <section v-if="data.branch_comparison.length > 1" class="card overflow-hidden">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('notifications.analytics.branchComparison', 'Branch comparison') }}</p>
                        <p class="t-caption mt-0.5">{{ t('notifications.analytics.branchHint', 'Unresolved alerts per branch — spot which workspace runs hottest.') }}</p>
                    </div>
                </header>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="row in data.branch_comparison" :key="row.branch_id" class="recurring-row">
                        <BuildingOffice2Icon class="w-4 h-4 text-indigo-500 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="recurring-code">Branch #{{ row.branch_id }}</p>
                        </div>
                        <span class="recurring-count">{{ row.unresolved }} {{ t('notifications.analytics.unresolved', 'Unresolved') }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowPathIcon, BuildingOffice2Icon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { notificationService } from '@/services/notificationService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Button     from '@/components/ui/Button.vue';

const { t, locale } = useI18n();
const loading = ref(true);
const data = ref(null);

async function load() {
    loading.value = true;
    try {
        const res = await notificationService.analytics();
        data.value = res.data?.data ?? null;
    } finally {
        loading.value = false;
    }
}
onMounted(load);

// ── Derived rows for the distribution lists ──────────────────────────────
const categoryRows = computed(() => {
    if (!data.value?.by_category) return [];
    const entries = Object.entries(data.value.by_category);
    const max = Math.max(...entries.map(([, v]) => v), 1);
    return entries
        .map(([key, value]) => ({ key, value, pct: Math.max(4, (value / max) * 100) }))
        .sort((a, b) => b.value - a.value);
});

const priorityRows = computed(() => {
    if (!data.value?.by_priority) return [];
    const order = { critical: 0, danger: 1, warning: 2, info: 3, success: 4 };
    const entries = Object.entries(data.value.by_priority);
    const max = Math.max(...entries.map(([, v]) => v), 1);
    return entries
        .map(([key, value]) => ({ key, value, pct: Math.max(4, (value / max) * 100) }))
        .sort((a, b) => (order[a.key] ?? 9) - (order[b.key] ?? 9));
});

// ── Helpers ──────────────────────────────────────────────────────────────

function barHeight(count) {
    if (!data.value?.timeline) return 4;
    const max = Math.max(...data.value.timeline.map(d => d.count), 1);
    return count === 0 ? 4 : Math.max(8, (count / max) * 100);
}

function sevPill(s) {
    return ({
        info:     'status-pill-info',
        success:  'status-pill-success',
        warning:  'status-pill-warning',
        danger:   'status-pill-danger',
        critical: 'status-pill-danger',
    })[s] || 'status-pill-neutral';
}

function catLabel(key) {
    return t(`notifications.category.${key}`, key.charAt(0).toUpperCase() + key.slice(1));
}

function formatMinutes(min) {
    if (min < 60) return `${Math.round(min)}m`;
    if (min < 1440) return `${(min / 60).toFixed(1)}h`;
    return `${(min / 1440).toFixed(1)}d`;
}

function formatRelative(iso) {
    if (!iso) return '—';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return `${Math.floor(diff)}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit' }).format(new Date(iso));
}
</script>

<style scoped>
@reference '../../../css/app.css';

.card-kpi.is-critical {
    border-color: rgb(244 63 94 / 0.45);
    background:
        radial-gradient(120% 90% at 100% 0%, rgba(244, 63, 94, 0.10), transparent 50%),
        var(--surface-raised);
}

/* Resolution bar */
.resolution-bar {
    width: 100%; height: 10px;
    border-radius: 999px;
    background: rgb(241 245 249);
    overflow: hidden;
}
html.dark .resolution-bar { background: rgb(30 41 59); }
.resolution-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, rgb(16 185 129), rgb(34 197 94));
    border-radius: 999px;
    transition: width var(--motion-slow) var(--motion-spring);
}

/* Timeline bars */
.timeline-bars {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 110px;
}
.timeline-bar {
    flex: 1 1 0;
    background: linear-gradient(180deg, rgb(99 102 241), rgb(79 70 229));
    border-radius: 3px 3px 0 0;
    min-height: 4px;
    transition: transform var(--motion-fast) var(--motion-out), opacity var(--motion-fast) var(--motion-out);
}
.timeline-bar:hover {
    transform: scaleY(1.05);
    transform-origin: bottom;
}
.timeline-bar.is-zero {
    background: rgb(226 232 240);
    opacity: 0.6;
}
html.dark .timeline-bar.is-zero { background: rgb(51 65 85); }

/* Distribution lists */
.dist-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.dist-row { display: flex; flex-direction: column; gap: 0.375rem; }
.dist-row-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.dist-row-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    text-transform: capitalize;
}
.dist-row-count {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}
.dist-bar {
    width: 100%; height: 6px;
    border-radius: 999px;
    background: rgb(241 245 249);
    overflow: hidden;
}
html.dark .dist-bar { background: rgb(30 41 59); }
.dist-bar-fill {
    height: 100%;
    background: rgb(99 102 241);
    border-radius: 999px;
    transition: width var(--motion-slow) var(--motion-spring);
}
.dist-bar-fill.is-critical { background: rgb(225 29 72); }
.dist-bar-fill.is-danger   { background: rgb(244 63 94); }
.dist-bar-fill.is-warning  { background: rgb(245 158 11); }
.dist-bar-fill.is-info     { background: rgb(14 165 233); }
.dist-bar-fill.is-success  { background: rgb(16 185 129); }

/* Recurring rows */
.recurring-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.125rem;
}
.recurring-code {
    font-family: ui-monospace, SF Mono, Menlo, monospace;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
}
.recurring-count {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    background: rgb(241 245 249);
    color: var(--text-primary);
    white-space: nowrap;
}
html.dark .recurring-count {
    background: rgb(30 41 59);
}
</style>
