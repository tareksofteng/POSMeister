<template>
    <!--
        NotificationDigestView — Phase AB Round 4.

        Renders the user's current digest with period tabs (Morning /
        Evening / Weekly). Each tab pulls the latest persisted digest
        from /api/notifications/digest?period=…; a "Preview now" button
        swaps in a live computed snapshot from
        /api/notifications/digest/preview?period=… without persisting.

        The shape of `data.summary` matches NotificationDigestService:

            generated_at, period, window {since, until, label}, role,
            counts {total, critical, danger, warning, info},
            top_alerts [{ code, category, severity, title, branch_id, ack, archived }],
            focus  (period-specific block),
            business (period-specific business KPIs),
            trend (weekly only),
            top_recurring (weekly only),
            by_branch (admin only)
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-5xl mx-auto anim-fade-in">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ t('notifications.module') }}</p>
                <h1 class="h1-display">{{ t('notifications.digest.title', 'Notification Digest') }}</h1>
                <p class="mt-1.5 t-body">{{ t('notifications.digest.subtitle', 'A summary of risks and activity at a glance — three views tuned to different moments in the workday.') }}</p>
            </div>
            <Button
                variant="secondary"
                size="sm"
                :loading="loading"
                :leading-icon="EyeIcon"
                @click="loadPreview"
            >
                {{ isPreview ? t('notifications.digest.previewing', 'Live preview') : t('notifications.digest.previewNow', 'Preview now') }}
            </Button>
        </header>

        <!-- Period tabs — design-system chip toolbar. -->
        <div class="card chip-toolbar">
            <p class="t-overline mr-2 hidden sm:inline">{{ t('notifications.digest.period', 'Period') }}</p>
            <button
                v-for="p in periods"
                :key="p.key"
                @click="switchPeriod(p.key)"
                :class="['filter-chip', period === p.key && 'is-active']"
            >
                <component :is="p.icon" class="w-3.5 h-3.5" aria-hidden="true" />
                {{ p.label }}
            </button>
            <span class="ml-auto t-caption" v-if="summary?.window?.label">{{ summary.window.label }}</span>
        </div>

        <!-- Loading state -->
        <template v-if="loading && !summary">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 anim-stagger">
                <Skeleton v-for="i in 4" :key="i" variant="kpi-card" />
            </div>
            <Skeleton variant="row" />
            <Skeleton variant="row" />
            <Skeleton variant="row" />
        </template>

        <!-- Empty state — no digest persisted yet for this period. -->
        <EmptyState
            v-else-if="!summary"
            size="md"
            tone="indigo"
            :icon="DocumentTextIcon"
            :title="t('notifications.digest.emptyTitle', 'No digest yet for this period')"
            :description="t('notifications.digest.emptyDesc', 'The scheduler builds one digest per user per period. Use the Preview button above to see what your next digest will contain.')"
        >
            <template #action>
                <Button variant="primary" :leading-icon="EyeIcon" @click="loadPreview">
                    {{ t('notifications.digest.previewNow', 'Preview now') }}
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <!-- Preview banner — make it crystal-clear this isn't persisted. -->
            <div v-if="isPreview" class="card card-alert card-alert-info text-sm flex items-start gap-2">
                <EyeIcon class="w-4 h-4 mt-0.5 flex-shrink-0" />
                <div>
                    <p class="font-semibold">{{ t('notifications.digest.livePreviewTitle', 'Live preview') }}</p>
                    <p class="mt-0.5 text-xs">{{ t('notifications.digest.livePreviewHint', 'This is what your next scheduled digest will look like right now. It has not been saved.') }}</p>
                </div>
            </div>

            <!-- Period-specific focus tiles -->
            <section v-if="focusTiles.length" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 anim-fade-up anim-stagger">
                <div v-for="t in focusTiles" :key="t.label" :class="['card card-kpi', t.loud && 'is-loud']">
                    <p class="t-overline">{{ t.label }}</p>
                    <p class="t-kpi mt-2">{{ t.value }}</p>
                    <p v-if="t.sub" class="t-caption mt-1">{{ t.sub }}</p>
                </div>
            </section>

            <!-- Severity counts -->
            <section class="card p-4 sm:p-5">
                <p class="t-overline mb-3">{{ t('notifications.digest.severityCounts', 'Alerts by severity') }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <div v-for="s in severityCounts" :key="s.key" class="sev-counter">
                        <span :class="['status-pill', sevPill(s.key)]">{{ s.label }}</span>
                        <p class="sev-counter-value">{{ s.value }}</p>
                    </div>
                </div>
            </section>

            <!-- 7-day trend (weekly only) -->
            <section v-if="summary.trend?.length" class="card card-analytics">
                <p class="t-overline">{{ t('notifications.digest.trend', '7-day trend') }}</p>
                <div class="trend-bars mt-3">
                    <div v-for="(d, i) in summary.trend" :key="i" class="trend-bar-wrap">
                        <span :class="['trend-bar', d.count === 0 && 'is-zero']" :style="{ height: trendHeight(d.count) + '%' }" :title="`${d.date}: ${d.count}`" />
                        <span class="trend-bar-label">{{ formatTrendLabel(d.date) }}</span>
                    </div>
                </div>
            </section>

            <!-- Business KPIs -->
            <section v-if="businessRows.length" class="card p-4 sm:p-5">
                <p class="t-overline mb-3">{{ t('notifications.digest.business', 'Business snapshot') }}</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">
                    <div v-for="b in businessRows" :key="b.label" class="flex items-center justify-between gap-3">
                        <dt class="t-body">{{ b.label }}</dt>
                        <dd class="text-sm font-semibold text-slate-800 dark:text-slate-100 font-mono">{{ b.value }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Top recurring (weekly only) -->
            <section v-if="summary.top_recurring?.length" class="card overflow-hidden">
                <header class="dash-list-head">
                    <p class="t-overline">{{ t('notifications.digest.topRecurring', 'Top recurring · last 7 days') }}</p>
                </header>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="(r, i) in summary.top_recurring" :key="r.code" class="recurring-row">
                        <span class="dash-list-rank">#{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="recurring-code">{{ r.code }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span :class="['status-pill', sevPill(r.last_severity)]">{{ r.last_severity }}</span>
                            <span class="recurring-count">×{{ r.occurrences }}</span>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Top alerts list -->
            <section v-if="summary.top_alerts?.length" class="card overflow-hidden">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('notifications.digest.topAlerts', 'Highlighted alerts') }}</p>
                        <p class="t-caption mt-0.5">{{ t('notifications.digest.topAlertsHint', 'Highest-urgency alerts inside the window.') }}</p>
                    </div>
                </header>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="a in summary.top_alerts" :key="a.id" class="bw-row" style="padding: 0.75rem 1.125rem;">
                        <span :class="['notif-rail', `notif-rail-${a.severity || 'info'}`]" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="t-overline">{{ a.category }}</span>
                                <span :class="['status-pill', sevPill(a.severity)]">{{ a.severity }}</span>
                                <span v-if="a.acked" class="status-pill status-pill-success">{{ t('notifications.ack', 'Ack') }}</span>
                            </div>
                            <p class="bw-row-title">{{ a.title }}</p>
                            <p class="t-caption">{{ a.code }}</p>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Admin-only branch breakdown -->
            <section v-if="summary.by_branch?.length" class="card overflow-hidden">
                <header class="dash-list-head">
                    <p class="t-overline">{{ t('notifications.digest.byBranch', 'By branch') }}</p>
                </header>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="b in summary.by_branch" :key="b.branch_id" class="recurring-row">
                        <BuildingOffice2Icon class="w-4 h-4 text-indigo-500 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="recurring-code">Branch #{{ b.branch_id }}</p>
                            <p class="t-caption">
                                {{ b.total }} {{ t('notifications.digest.raised', 'raised') }}
                                ·
                                <span class="text-rose-600 dark:text-rose-400 font-semibold">{{ b.open }} {{ t('notifications.digest.open', 'open') }}</span>
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <p class="t-caption text-center">
                {{ t('notifications.digest.generatedAt', 'Generated') }}
                {{ formatRelative(summary.generated_at) }}
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import {
    BuildingOffice2Icon, CalendarDaysIcon, ChartBarIcon, DocumentTextIcon,
    EyeIcon, MoonIcon, SunIcon,
} from '@heroicons/vue/24/outline';
import { notificationService } from '@/services/notificationService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Button     from '@/components/ui/Button.vue';

const { t, locale } = useI18n();
const settingsStore = useSettingsStore();

const period = ref('morning');
const summary = ref(null);
const loading = ref(true);
const isPreview = ref(false);

const periods = computed(() => [
    { key: 'morning', label: t('notifications.digest.morning', 'Morning'), icon: SunIcon },
    { key: 'evening', label: t('notifications.digest.evening', 'Evening'), icon: MoonIcon },
    { key: 'weekly',  label: t('notifications.digest.weekly',  'Weekly'),  icon: CalendarDaysIcon },
]);

async function switchPeriod(p) {
    if (period.value === p) return;
    period.value = p;
    isPreview.value = false;
    await loadDigest();
}

async function loadDigest() {
    loading.value = true;
    isPreview.value = false;
    try {
        const res = await notificationService.digest(period.value);
        const row = res.data?.data;
        summary.value = row?.summary ?? null;
    } catch {
        summary.value = null;
    } finally {
        loading.value = false;
    }
}

async function loadPreview() {
    loading.value = true;
    try {
        const res = await notificationService.digestPreview(period.value);
        summary.value = res.data?.data?.summary ?? null;
        isPreview.value = true;
    } catch {
        // fall back to whatever we already showed
    } finally {
        loading.value = false;
    }
}

onMounted(loadDigest);

// ── Period-specific focus tiles ──────────────────────────────────────────

const focusTiles = computed(() => {
    const f = summary.value?.focus;
    if (!f) return [];
    if (period.value === 'morning') {
        return [
            { label: t('notifications.digest.openCritical', 'Open critical'), value: f.open_critical ?? 0, loud: (f.open_critical ?? 0) > 0 },
            { label: t('notifications.digest.lowStock', 'Low stock'),         value: f.low_stock_count ?? 0 },
            { label: t('notifications.digest.dueToday', 'Due today'),         value: f.due_today_count ?? 0 },
            {
                label: t('notifications.digest.salesYesterday', 'Yesterday'),
                value: fmt(summary.value?.business?.sales_yesterday ?? 0),
                sub: t('notifications.digest.salesUnit', 'sales total'),
            },
        ];
    }
    if (period.value === 'evening') {
        return [
            { label: t('notifications.digest.raisedToday', 'Raised today'),     value: f.raised_today ?? 0 },
            { label: t('notifications.digest.resolvedToday', 'Resolved today'), value: f.resolved_today ?? 0 },
            { label: t('notifications.digest.resolution', 'Resolution'),        value: `${f.resolution_pct ?? 0}%` },
            { label: t('notifications.digest.openCriticalEod', 'Open critical · EOD'), value: f.open_critical_eod ?? 0, loud: (f.open_critical_eod ?? 0) > 0 },
        ];
    }
    // weekly
    return [
        { label: t('notifications.digest.raised', 'Raised'),       value: f.raised ?? 0 },
        { label: t('notifications.digest.resolved', 'Resolved'),   value: f.resolved ?? 0 },
        { label: t('notifications.digest.resolution', 'Resolution'), value: `${f.resolution_pct ?? 0}%` },
        { label: t('notifications.digest.avgPerDay', 'Avg / day'), value: f.avg_per_day ?? 0 },
    ];
});

const severityCounts = computed(() => {
    const c = summary.value?.counts ?? {};
    return [
        { key: 'critical', label: t('notifications.severity.critical'), value: c.critical ?? 0 },
        { key: 'danger',   label: t('notifications.severity.danger'),   value: c.danger ?? 0 },
        { key: 'warning',  label: t('notifications.severity.warning'),  value: c.warning ?? 0 },
        { key: 'info',     label: t('notifications.severity.info'),     value: c.info ?? 0 },
        { key: 'total',    label: t('notifications.digest.total', 'Total'), value: c.total ?? 0 },
    ];
});

const businessRows = computed(() => {
    const b = summary.value?.business;
    if (!b) return [];
    if (period.value === 'morning' || period.value === 'evening') {
        const rows = [
            { label: t('notifications.digest.salesToday', 'Today\'s sales'), value: fmt(b.sales_today) },
            { label: t('notifications.digest.salesYesterday', 'Yesterday'), value: fmt(b.sales_yesterday) },
        ];
        if (b.transactions_today != null) {
            rows.push({ label: t('notifications.digest.transactionsToday', 'Today\'s transactions'), value: String(b.transactions_today) });
        }
        if (b.overdue_count != null) {
            rows.push({ label: t('notifications.digest.overdueCount', 'Overdue customer invoices'), value: String(b.overdue_count) });
        }
        return rows;
    }
    if (period.value === 'weekly') {
        return [
            { label: t('notifications.digest.salesWeek', 'Sales · last 7 days'),    value: fmt(b.sales_week) },
            { label: t('notifications.digest.salesPriorWeek', 'Sales · prior 7'),   value: fmt(b.sales_prior) },
        ];
    }
    return [];
});

// ── Helpers ──────────────────────────────────────────────────────────────

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(locale.value || 'en-US', { style: 'currency', currency: code, maximumFractionDigits: 0 })
        .format(Number(value) || 0);
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

function trendHeight(count) {
    if (!summary.value?.trend) return 4;
    const max = Math.max(...summary.value.trend.map(d => d.count), 1);
    return count === 0 ? 6 : Math.max(10, (count / max) * 100);
}

function formatTrendLabel(iso) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { weekday: 'short' }).format(new Date(iso));
}

function formatRelative(iso) {
    if (!iso) return '—';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return `${Math.floor(diff)}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}
</script>

<style scoped>
@reference '../../../css/app.css';

.card-kpi.is-loud {
    border-color: rgb(244 63 94 / 0.45);
    background:
        radial-gradient(120% 90% at 100% 0%, rgba(244, 63, 94, 0.10), transparent 50%),
        var(--surface-raised);
}

/* Severity counter cards inside the "Alerts by severity" panel. */
.sev-counter {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.375rem;
    padding: 0.75rem;
    border-radius: 0.625rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
}
.sev-counter-value {
    font-family: ui-monospace, SF Mono, Menlo, monospace;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

/* 7-day trend bars. */
.trend-bars {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    height: 96px;
}
.trend-bar-wrap {
    flex: 1 1 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.375rem;
}
.trend-bar {
    width: 100%;
    background: linear-gradient(180deg, rgb(99 102 241), rgb(79 70 229));
    border-radius: 4px 4px 0 0;
    min-height: 6px;
    transition: transform var(--motion-fast) var(--motion-out);
}
.trend-bar:hover { transform: scaleY(1.05); transform-origin: bottom; }
.trend-bar.is-zero {
    background: rgb(226 232 240);
    opacity: 0.6;
}
html.dark .trend-bar.is-zero { background: rgb(51 65 85); }
.trend-bar-label {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-tertiary);
}

/* Recurring row + recurring code styling shared from analytics view. */
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
html.dark .recurring-count { background: rgb(30 41 59); }

/* bw-row reused vocabulary for the highlighted alerts. */
.bw-row {
    display: flex;
    align-items: stretch;
    gap: 0.75rem;
}
.bw-row-title {
    margin-top: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
}
.notif-rail {
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
</style>
