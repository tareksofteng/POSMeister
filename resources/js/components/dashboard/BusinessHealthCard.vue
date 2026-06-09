<template>
    <!--
        BusinessHealthCard — Phase AC Round 1.

        A 0–100 health score the owner can read in two seconds. The
        backend (BusinessHealthService) returns the composite score plus
        five sub-scores; this card renders the score as a coloured ring
        with the headline delta and lets the user expand to see the
        breakdown.

        Tier → ring colour map:
          90-100  emerald (very healthy)
          70-89   sky     (healthy)
          50-69   amber   (attention)
          < 50    rose    (action)
    -->
    <section :class="['card health-card', `tier-${tier}`]">
        <div class="flex items-start gap-4">
            <!-- Score ring -->
            <div class="ring-wrap" :aria-label="`Health score ${score} of 100`">
                <svg viewBox="0 0 100 100" class="ring-svg">
                    <circle class="ring-bg" cx="50" cy="50" r="42" stroke-width="8" fill="none" />
                    <circle
                        class="ring-fg"
                        cx="50" cy="50" r="42" stroke-width="8" fill="none"
                        stroke-linecap="round"
                        transform="rotate(-90 50 50)"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="strokeOffset"
                    />
                </svg>
                <div class="ring-center">
                    <p class="ring-score">{{ score }}</p>
                    <p class="ring-of">/100</p>
                </div>
            </div>

            <!-- Headline + delta + tier label -->
            <div class="flex-1 min-w-0">
                <p class="t-overline">{{ t('dashboard.health.title', 'Business health') }}</p>
                <p class="health-tier">{{ tierLabel }}</p>
                <p v-if="delta != null" class="health-delta">
                    <ArrowTrendingUpIcon v-if="delta >= 0" class="w-4 h-4 inline" />
                    <ArrowTrendingDownIcon v-else class="w-4 h-4 inline" />
                    <span :class="delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ delta >= 0 ? '+' : '' }}{{ delta }}%
                    </span>
                    <span class="t-caption">{{ t('dashboard.health.vsAverage', 'vs 7-day average') }}</span>
                </p>
                <p v-else class="t-caption mt-1">{{ t('dashboard.health.noDelta', 'Sales baseline still warming up.') }}</p>
            </div>

            <button
                v-if="subscores.length"
                @click="expanded = !expanded"
                class="row-action row-action-indigo"
                :title="t('dashboard.health.toggleDetail', 'Show breakdown')"
                :aria-expanded="expanded"
            >
                <ChevronDownIcon class="w-4 h-4 transition-transform" :class="expanded && 'rotate-180'" />
            </button>
        </div>

        <!-- Breakdown grid (collapsible) -->
        <Transition name="expand">
            <div v-if="expanded && subscores.length" class="health-breakdown">
                <div v-for="s in subscores" :key="s.key" class="sub-row">
                    <div class="flex items-center justify-between gap-2">
                        <p class="sub-label">{{ s.label }}</p>
                        <p class="sub-value">{{ Math.round(s.score) }}<span class="t-caption"> / {{ s.max }}</span></p>
                    </div>
                    <div class="sub-bar">
                        <div class="sub-bar-fill" :style="{ width: ((s.score / s.max) * 100) + '%' }" />
                    </div>
                    <p v-if="s.note" class="t-caption mt-1">{{ s.note }}</p>
                </div>
            </div>
        </Transition>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowTrendingUpIcon, ArrowTrendingDownIcon, ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    health: { type: Object, default: null },
});

const { t } = useI18n();
const expanded = ref(false);

const score = computed(() => props.health?.score ?? 0);
const tier = computed(() => props.health?.tier ?? 'rose');
const delta = computed(() => props.health?.delta ?? null);

const tierLabel = computed(() => ({
    emerald: t('dashboard.health.tier.excellent', 'Excellent'),
    sky:     t('dashboard.health.tier.healthy',   'Healthy'),
    amber:   t('dashboard.health.tier.attention', 'Needs attention'),
    rose:    t('dashboard.health.tier.action',    'Action required'),
})[tier.value] || '—');

// Score ring math — pre-compute the dash offset for the filled arc.
const circumference = 2 * Math.PI * 42;
const strokeOffset = computed(() => circumference * (1 - score.value / 100));

const subscores = computed(() => {
    const s = props.health?.subscores;
    if (!s) return [];
    return [
        { key: 'sales',       label: s.sales?.label || 'Sales',        score: s.sales?.score || 0,       max: 25, note: s.sales?.note },
        { key: 'profit',      label: s.profit?.label || 'Profitability', score: s.profit?.score || 0,    max: 20, note: s.profit?.note },
        { key: 'cash',        label: s.cash?.label || 'Cash position', score: s.cash?.score || 0,        max: 20, note: s.cash?.note },
        { key: 'receivables', label: s.receivables?.label || 'Receivables', score: s.receivables?.score || 0, max: 15, note: s.receivables?.note },
        { key: 'risk',        label: s.risk?.label || 'Operational risk', score: s.risk?.score || 0,    max: 20, note: s.risk?.note },
    ];
});
</script>

<style scoped>
@reference '../../../css/app.css';

.health-card {
    padding: 1rem 1.125rem;
    position: relative;
    overflow: hidden;
    isolation: isolate;
}
.health-card::before {
    content: '';
    position: absolute; inset: 0;
    pointer-events: none;
    z-index: -1;
    opacity: 0.65;
}
.tier-emerald::before { background: radial-gradient(120% 90% at 0% 0%, rgba(16, 185, 129, 0.12), transparent 50%); }
.tier-sky::before     { background: radial-gradient(120% 90% at 0% 0%, rgba(14, 165, 233, 0.12), transparent 50%); }
.tier-amber::before   { background: radial-gradient(120% 90% at 0% 0%, rgba(245, 158, 11, 0.14), transparent 50%); }
.tier-rose::before    { background: radial-gradient(120% 90% at 0% 0%, rgba(244, 63, 94, 0.14),  transparent 50%); }

.ring-wrap {
    position: relative;
    width: 80px; height: 80px;
    flex-shrink: 0;
}
.ring-svg { width: 100%; height: 100%; }
.ring-bg { stroke: var(--border-default); }
.ring-fg {
    transition: stroke-dashoffset 600ms var(--motion-spring);
}
.tier-emerald .ring-fg { stroke: rgb(16 185 129); }
.tier-sky .ring-fg     { stroke: rgb(14 165 233); }
.tier-amber .ring-fg   { stroke: rgb(245 158 11); }
.tier-rose .ring-fg    { stroke: rgb(244 63 94); }
.ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.ring-score {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}
.ring-of {
    font-size: 0.6875rem;
    color: var(--text-tertiary);
    font-weight: 600;
}

.health-tier {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-top: 0.125rem;
    letter-spacing: -0.012em;
}
.health-delta {
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    font-weight: 600;
}

/* Breakdown */
.health-breakdown {
    margin-top: 0.875rem;
    padding-top: 0.875rem;
    border-top: 1px solid var(--border-subtle);
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.875rem;
}
@media (min-width: 640px) {
    .health-breakdown { grid-template-columns: 1fr 1fr; }
}
.sub-row { display: flex; flex-direction: column; gap: 0.25rem; }
.sub-label { font-size: 0.8125rem; font-weight: 600; color: var(--text-primary); }
.sub-value { font-size: 0.8125rem; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.sub-bar {
    width: 100%; height: 5px;
    border-radius: 999px;
    background: var(--surface-sunken);
    overflow: hidden;
}
.sub-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, rgb(79 70 229), rgb(99 102 241));
    border-radius: 999px;
    transition: width 500ms var(--motion-spring);
}

.expand-enter-active, .expand-leave-active {
    transition: opacity var(--motion-base) var(--motion-out), max-height var(--motion-base) var(--motion-out);
    overflow: hidden;
}
.expand-enter-from, .expand-leave-to { opacity: 0; max-height: 0; }
.expand-enter-to,   .expand-leave-from { opacity: 1; max-height: 600px; }
</style>
