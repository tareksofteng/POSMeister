<template>
    <!--
        Business Insights timeline. Each card carries an insight produced
        by the rule-based services on the backend; the user can pin one
        for quick recall, resolve it once acted on, or ignore it to take
        it out of the pile. Filters along the top let the user shift the
        window: Today / Yesterday / Last 7 days / Last 30 days.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-5xl mx-auto anim-fade-in">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ t('insights.module', 'Business Insights') }}</p>
                <h1 class="h1-display">{{ t('insights.timeline.title', 'Insight timeline') }}</h1>
                <p class="mt-1.5 t-body">{{ t('insights.timeline.subtitle', 'Pattern signals the analytics engine noticed about your operations. Pin the important ones, resolve the rest as you go.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="isAdmin"
                    variant="secondary"
                    size="sm"
                    :loading="capturing"
                    :leading-icon="ArrowPathIcon"
                    @click="captureNow"
                >
                    {{ t('insights.timeline.refresh', 'Refresh now') }}
                </Button>
            </div>
        </header>

        <!-- KPI strip -->
        <section class="grid grid-cols-3 gap-2.5 sm:gap-4 anim-fade-up">
            <div class="card card-kpi">
                <p class="t-overline">{{ t('insights.timeline.active', 'Active') }}</p>
                <p class="t-kpi mt-1">{{ counts.active }}</p>
            </div>
            <div class="card card-kpi">
                <p class="t-overline">{{ t('insights.timeline.pinned', 'Pinned') }}</p>
                <p class="t-kpi mt-1">{{ counts.pinned }}</p>
            </div>
            <div class="card card-kpi">
                <p class="t-overline">{{ t('insights.timeline.resolvedWeek', 'Resolved · 7d') }}</p>
                <p class="t-kpi mt-1">{{ counts.resolved }}</p>
            </div>
        </section>

        <!-- Bucket chips -->
        <div class="card chip-toolbar">
            <button
                v-for="b in buckets"
                :key="b.key"
                :class="['filter-chip', bucket === b.key && 'is-active']"
                @click="setBucket(b.key)"
            >
                {{ b.label }}
            </button>
        </div>

        <!-- List -->
        <section class="card overflow-hidden">
            <div v-if="loading" class="p-4 space-y-3">
                <Skeleton v-for="i in 4" :key="i" variant="row" />
            </div>

            <EmptyState
                v-else-if="!rows.length"
                size="md"
                tone="emerald"
                :icon="CheckCircleIcon"
                :title="t('insights.timeline.emptyTitle', 'A quiet stretch')"
                :description="t('insights.timeline.emptyDesc', 'No insights surfaced in this window. The engine resamples every ten minutes — check back later.')"
            />

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="r in rows" :key="r.id" :class="['insight-row', `is-${r.status}`]">
                    <span :class="['insight-rail', `rail-${r.severity || 'info'}`]" />

                    <div class="insight-body">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="t-overline">{{ r.kind }}</span>
                            <span :class="['status-pill', sevPill(r.severity)]">{{ r.severity }}</span>
                            <span v-if="r.status === 'pinned'" class="status-pill status-pill-warning">
                                <BookmarkIcon class="w-3 h-3" /> {{ t('insights.timeline.pinned', 'Pinned') }}
                            </span>
                            <span v-else-if="r.status === 'resolved'" class="status-pill status-pill-success">
                                {{ t('insights.timeline.resolved', 'Resolved') }}
                            </span>
                            <span v-else-if="r.status === 'ignored'" class="status-pill status-pill-neutral">
                                {{ t('insights.timeline.ignored', 'Ignored') }}
                            </span>
                            <span class="t-caption ml-auto">{{ formatRelative(r.observed_at) }}</span>
                        </div>

                        <p class="insight-title">{{ r.title }}</p>
                        <p v-if="r.detail" class="insight-detail">{{ r.detail }}</p>

                        <div class="insight-meta">
                            <span class="t-caption">
                                {{ t('insights.timeline.confidence', 'Confidence') }}: {{ r.confidence }}%
                            </span>
                            <RouterLink
                                v-if="r.action && hasRoute(r.action.route)"
                                :to="{ name: r.action.route, params: r.action.params || {} }"
                                class="insight-action"
                            >
                                {{ t(r.action.label, r.action.label) }}
                                <ArrowLongRightIcon class="w-3 h-3" />
                            </RouterLink>
                        </div>
                    </div>

                    <div class="insight-actions">
                        <button
                            v-if="r.status !== 'pinned'"
                            class="row-action row-action-amber"
                            :title="t('insights.timeline.pin', 'Pin')"
                            @click="changeStatus(r, 'pinned')"
                        >
                            <BookmarkIcon class="w-4 h-4" />
                        </button>
                        <button
                            v-if="r.status !== 'resolved'"
                            class="row-action row-action-emerald"
                            :title="t('insights.timeline.markResolved', 'Mark resolved')"
                            @click="changeStatus(r, 'resolved')"
                        >
                            <CheckIcon class="w-4 h-4" />
                        </button>
                        <button
                            v-if="r.status !== 'ignored'"
                            class="row-action"
                            :title="t('insights.timeline.ignore', 'Ignore')"
                            @click="changeStatus(r, 'ignored')"
                        >
                            <XMarkIcon class="w-4 h-4" />
                        </button>
                        <button
                            v-if="r.status !== 'active'"
                            class="row-action row-action-indigo"
                            :title="t('insights.timeline.restore', 'Restore')"
                            @click="changeStatus(r, 'active')"
                        >
                            <ArrowPathIcon class="w-4 h-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter, RouterLink } from 'vue-router';
import {
    ArrowLongRightIcon, ArrowPathIcon, BookmarkIcon, CheckCircleIcon,
    CheckIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import { insightsService } from '@/services/insightsService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Button     from '@/components/ui/Button.vue';

const { t, locale } = useI18n();
const router = useRouter();
const auth   = useAuthStore();
const { toast } = useAlert();

const isAdmin = computed(() => auth.userRole === 'admin');
const validRouteNames = new Set(router.getRoutes().map(r => r.name).filter(Boolean));
function hasRoute(name) { return name ? validRouteNames.has(name) : false; }

const bucket = ref('today');
const rows = ref([]);
const counts = ref({ active: 0, pinned: 0, resolved: 0 });
const loading = ref(true);
const capturing = ref(false);

const buckets = computed(() => [
    { key: 'today',     label: t('insights.timeline.buckets.today',     'Today') },
    { key: 'yesterday', label: t('insights.timeline.buckets.yesterday', 'Yesterday') },
    { key: 'week',      label: t('insights.timeline.buckets.week',      'Last 7 days') },
    { key: 'month',     label: t('insights.timeline.buckets.month',     'Last 30 days') },
]);

async function load() {
    loading.value = true;
    try {
        const res = await insightsService.timeline(bucket.value);
        rows.value   = res.data?.data ?? [];
        counts.value = res.data?.counts ?? { active: 0, pinned: 0, resolved: 0 };
    } catch (e) {
        toast('error', t('common.unexpectedError'));
    } finally {
        loading.value = false;
    }
}
function setBucket(k) {
    if (k === bucket.value) return;
    bucket.value = k;
    load();
}

async function changeStatus(row, status) {
    try {
        await insightsService.markStatus(row.id, status);
        row.status = status;
        // Recount cheaply without reloading the whole list.
        if (status === 'resolved')      counts.value.resolved++;
        if (status === 'pinned')        counts.value.pinned++;
    } catch (e) {
        toast('error', t('common.unexpectedError'));
    }
}

async function captureNow() {
    capturing.value = true;
    try {
        await insightsService.captureNow();
        await load();
        toast('success', t('insights.timeline.refreshed', 'Insights refreshed.'));
    } catch (e) {
        toast('error', t('common.unexpectedError'));
    } finally {
        capturing.value = false;
    }
}

function sevPill(s) {
    return ({
        positive: 'status-pill-success',
        info:     'status-pill-info',
        warning:  'status-pill-warning',
        danger:   'status-pill-danger',
        critical: 'status-pill-danger',
    })[s] || 'status-pill-neutral';
}

function formatRelative(iso) {
    if (!iso) return '—';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60)    return `${Math.floor(diff)}s`;
    if (diff < 3600)  return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: 'short' }).format(new Date(iso));
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';

.chip-toolbar {
    padding: 0.625rem 0.875rem;
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    border: 1px solid var(--border-default);
    transition: background-color var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
}
.filter-chip:hover { color: var(--text-primary); border-color: var(--border-strong); }
.filter-chip.is-active {
    background: rgb(79 70 229);
    color: white;
    border-color: rgb(79 70 229);
    box-shadow: var(--elev-1);
}

.insight-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: stretch;
    gap: 0.875rem;
    padding: 1rem 1.125rem;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.insight-row:hover { background: rgb(248 250 252); }
html.dark .insight-row:hover { background: rgb(30 41 59 / 0.4); }
.insight-row.is-resolved, .insight-row.is-ignored {
    opacity: 0.7;
}

.insight-rail {
    width: 3px;
    align-self: stretch;
    min-height: 32px;
    border-radius: 999px;
}
.rail-positive { background: rgb(16 185 129); }
.rail-info     { background: rgb(99 102 241); }
.rail-warning  { background: rgb(245 158 11); }
.rail-danger   { background: rgb(244 63 94); }
.rail-critical { background: rgb(225 29 72); }

.insight-body { min-width: 0; }
.insight-title {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.4;
}
.insight-detail {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    line-height: 1.45;
}
.insight-meta {
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    flex-wrap: wrap;
}
.insight-action {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgb(67 56 202);
    transition: background-color var(--motion-fast) var(--motion-out);
}
.insight-action:hover { background: rgb(238 242 255); }
html.dark .insight-action { color: rgb(165 180 252); }
html.dark .insight-action:hover { background: rgb(67 56 202 / 0.18); }

.insight-actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .insight-actions { flex-direction: column; }
}
</style>
