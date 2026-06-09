<template>
    <!--
        InsightsCarousel — Phase AC Round 1.

        Renders the rule-based insights returned by DashboardInsightsService
        as a horizontally-scrolling row of cards. On desktop the row fits
        the page width; on mobile it scroll-snaps so each card lines up
        with the viewport edge.

        Severity → tone:
          positive  emerald (good news)
          info      indigo  (neutral observation)
          warning   amber   (action soon)
          danger    rose    (act now)
    -->
    <section v-if="insights.length" class="insights-section anim-fade-up">
        <header class="insights-head">
            <div>
                <p class="t-overline">{{ t('dashboard.insights.title', 'Business insights') }}</p>
                <p class="t-caption mt-0.5">{{ t('dashboard.insights.subtitle', 'Pattern signals the system noticed about your operations.') }}</p>
            </div>
        </header>

        <div class="insights-track">
            <div
                v-for="(ins, i) in insights"
                :key="i"
                :class="['insight-card', `tone-${ins.severity || 'info'}`]"
            >
                <div class="insight-head">
                    <div :class="['insight-icon', `tone-${ins.severity || 'info'}-icon`]">
                        <component :is="iconFor(ins.kind)" class="w-4 h-4" />
                    </div>
                    <span :class="['status-pill', sevPill(ins.severity)]">{{ severityLabel(ins.severity) }}</span>
                </div>

                <p class="insight-title">{{ ins.title }}</p>
                <p v-if="ins.detail" class="insight-detail">{{ ins.detail }}</p>

                <div v-if="ins.action && hasRoute(ins.action.route)" class="insight-action">
                    <RouterLink
                        :to="{ name: ins.action.route, params: ins.action.params || {} }"
                        class="insight-link-btn"
                    >
                        {{ t(ins.action.label, ins.action.label) }}
                        <ArrowLongRightIcon class="w-3.5 h-3.5" />
                    </RouterLink>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import {
    ArrowLongRightIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon,
    BanknotesIcon, CubeIcon, DocumentTextIcon, UsersIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    insights: { type: Array, default: () => [] },
});

const { t } = useI18n();
const router = useRouter();

// Pre-compute the valid route set so a stale action link can't crash
// the carousel — same defensive pattern the bell + center use.
const validRouteNames = new Set(
    router.getRoutes().map(r => r.name).filter(Boolean)
);
function hasRoute(name) { return validRouteNames.has(name); }

function iconFor(kind) {
    return {
        sales:       ArrowTrendingUpIcon,
        customer:    UsersIcon,
        inventory:   CubeIcon,
        cash:        BanknotesIcon,
        receivables: DocumentTextIcon,
    }[kind] || ArrowTrendingUpIcon;
}

function sevPill(s) {
    return ({
        positive: 'status-pill-success',
        info:     'status-pill-info',
        warning:  'status-pill-warning',
        danger:   'status-pill-danger',
    })[s] || 'status-pill-neutral';
}

function severityLabel(s) {
    return ({
        positive: t('dashboard.insights.positive', 'Positive'),
        info:     t('dashboard.insights.info',     'Info'),
        warning:  t('dashboard.insights.warning',  'Warning'),
        danger:   t('dashboard.insights.danger',   'Critical'),
    })[s] || t('dashboard.insights.info', 'Info');
}
</script>

<style scoped>
@reference '../../../css/app.css';

.insights-section {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.insights-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

/* Horizontal scrolling track. Cards are fixed-width so the user
   sees ~2.3 cards on mobile (peek pattern) and ~4 on desktop. */
.insights-track {
    display: flex;
    gap: 0.875rem;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
    padding-bottom: 0.25rem;
    scrollbar-width: thin;
}
.insights-track::-webkit-scrollbar { height: 6px; }
.insights-track::-webkit-scrollbar-track { background: transparent; }
.insights-track::-webkit-scrollbar-thumb {
    background-color: rgba(100, 116, 139, 0.25);
    border-radius: 999px;
}

.insight-card {
    flex: 0 0 280px;
    padding: 1rem 1.125rem;
    border-radius: 0.875rem;
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
    box-shadow: var(--elev-1);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    scroll-snap-align: start;
    transition:
        box-shadow var(--motion-fast) var(--motion-out),
        transform  var(--motion-fast) var(--motion-out),
        border-color var(--motion-fast) var(--motion-out);
}
.insight-card:hover {
    box-shadow: var(--elev-2);
    transform: translateY(-1px);
    border-color: var(--border-strong);
}

/* Per-tone left rail */
.insight-card.tone-positive { border-left: 3px solid rgb(16 185 129); }
.insight-card.tone-info     { border-left: 3px solid rgb(99 102 241); }
.insight-card.tone-warning  { border-left: 3px solid rgb(245 158 11); }
.insight-card.tone-danger   { border-left: 3px solid rgb(244 63 94); }

.insight-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.insight-icon {
    width: 32px; height: 32px;
    border-radius: 0.5rem;
    display: grid; place-items: center;
}
.tone-positive-icon { background: rgb(209 250 229); color: rgb(6 95 70); }
.tone-info-icon     { background: rgb(238 242 255); color: rgb(67 56 202); }
.tone-warning-icon  { background: rgb(254 243 199); color: rgb(146 64 14); }
.tone-danger-icon   { background: rgb(254 226 226); color: rgb(159 18 57); }
html.dark .tone-positive-icon { background: rgb(6 95 70 / 0.25);  color: rgb(167 243 208); }
html.dark .tone-info-icon     { background: rgb(67 56 202 / 0.25); color: rgb(165 180 252); }
html.dark .tone-warning-icon  { background: rgb(146 64 14 / 0.3);  color: rgb(252 211 77); }
html.dark .tone-danger-icon   { background: rgb(159 18 57 / 0.25); color: rgb(254 205 211); }

.insight-title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.35;
    color: var(--text-primary);
}
.insight-detail {
    font-size: 0.75rem;
    color: var(--text-secondary);
    line-height: 1.45;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.insight-action { margin-top: auto; }
.insight-link-btn {
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
.insight-link-btn:hover { background: rgb(238 242 255); }
html.dark .insight-link-btn { color: rgb(165 180 252); }
html.dark .insight-link-btn:hover { background: rgb(67 56 202 / 0.18); }
</style>
