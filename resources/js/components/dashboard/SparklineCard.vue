<template>
    <div :class="['sparkline-card', tone]">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="card-label">{{ label }}</p>
                <p class="card-value">
                    <AnimatedCounter :value="value" :decimals="decimals" :prefix="prefix" :suffix="suffix" />
                </p>
                <p v-if="sub" class="card-sub">{{ sub }}</p>
            </div>
            <div v-if="$slots.icon" :class="['card-icon', tone + '-bg']">
                <slot name="icon" />
            </div>
        </div>

        <div class="mt-3 flex items-end justify-between gap-2">
            <div v-if="delta != null" :class="['trend-pill', delta >= 0 ? 'trend-up' : 'trend-down']">
                <span class="text-[10px]">{{ delta >= 0 ? '▲' : '▼' }}</span>
                {{ Math.abs(delta).toFixed(1) }}%
                <span class="ml-0.5 opacity-70">{{ deltaLabel }}</span>
            </div>
            <div v-else><!-- spacer --></div>

            <Sparkline
                v-if="sparkline?.length"
                :values="sparkline"
                :color="sparkColor"
                :width="84"
                :height="28"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AnimatedCounter from './AnimatedCounter.vue';
import Sparkline from './Sparkline.vue';

const props = defineProps({
    label:      { type: String,  required: true },
    value:      { type: Number,  required: true },
    sub:        { type: String,  default: '' },
    prefix:     { type: String,  default: '' },
    suffix:     { type: String,  default: '' },
    decimals:   { type: Number,  default: 0 },
    delta:      { type: Number,  default: null },
    deltaLabel: { type: String,  default: '' },
    sparkline:  { type: Array,   default: () => [] },
    tone:       { type: String,  default: 'indigo' },   // indigo | emerald | amber | rose | sky | violet | slate
});

const sparkColor = computed(() => ({
    indigo:  '#6366f1',
    emerald: '#10b981',
    amber:   '#f59e0b',
    rose:    '#f43f5e',
    sky:     '#0ea5e9',
    violet:  '#8b5cf6',
    slate:   '#64748b',
}[props.tone] || '#6366f1'));
</script>

<style scoped>
@reference '../../../css/app.css';

.sparkline-card {
    @apply relative rounded-2xl border border-slate-200/70 dark:border-slate-800
           bg-white dark:bg-slate-900 px-5 py-4 transition-all duration-200;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04), 0 8px 24px -16px rgba(15,23,42,0.08);
}
.sparkline-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -8px rgba(15,23,42,0.06), 0 24px 48px -12px rgba(15,23,42,0.12);
}

/* Subtle top-edge gradient accent that hints at the tone */
.sparkline-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 2px;
    border-radius: 1rem 1rem 0 0;
    background: linear-gradient(90deg, transparent, currentColor, transparent);
    opacity: 0;
    transition: opacity 200ms ease;
}
.sparkline-card:hover::before { opacity: 0.5; }

.indigo  { color: rgb(99 102 241); }
.emerald { color: rgb(16 185 129); }
.amber   { color: rgb(245 158 11); }
.rose    { color: rgb(244 63 94); }
.sky     { color: rgb(14 165 233); }
.violet  { color: rgb(139 92 246); }
.slate   { color: rgb(100 116 139); }

.card-label {
    @apply text-[11px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400;
}
.card-value {
    @apply text-2xl font-bold text-slate-900 dark:text-slate-100 tabular-nums leading-tight mt-1;
    font-feature-settings: 'tnum' on;
}
.card-sub {
    @apply text-xs text-slate-500 dark:text-slate-400 mt-0.5;
}

.card-icon {
    @apply w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0;
}
.indigo-bg  { @apply bg-indigo-50  text-indigo-600  dark:bg-indigo-900/40  dark:text-indigo-300; }
.emerald-bg { @apply bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300; }
.amber-bg   { @apply bg-amber-50   text-amber-600   dark:bg-amber-900/40   dark:text-amber-300; }
.rose-bg    { @apply bg-rose-50    text-rose-600    dark:bg-rose-900/40    dark:text-rose-300; }
.sky-bg     { @apply bg-sky-50     text-sky-600     dark:bg-sky-900/40     dark:text-sky-300; }
.violet-bg  { @apply bg-violet-50  text-violet-600  dark:bg-violet-900/40  dark:text-violet-300; }
.slate-bg   { @apply bg-slate-50   text-slate-600   dark:bg-slate-800      dark:text-slate-300; }

.trend-pill {
    @apply inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full;
}
.trend-up   { @apply bg-emerald-100/70 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300; }
.trend-down { @apply bg-rose-100/70    text-rose-700    dark:bg-rose-900/30    dark:text-rose-300; }
</style>
