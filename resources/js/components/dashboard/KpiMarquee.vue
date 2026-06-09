<template>
    <!--
        KpiMarquee — Phase AC Round 2 polish.

        Premium executive KPI carousel. The auto-scroll behaviour from
        Phase X is preserved, but the marquee now:

          – renders a mini sparkline per card when item.sparkline is
            provided (a [n1, n2, ...] array of recent values),
          – exposes manual prev/next nav buttons on desktop (≥ md), so
            the owner can scrub without waiting for the auto-loop,
          – pauses the auto-scroll while either nav button is held or
            on hover (existing behaviour).

        Backwards compatible — every existing prop on every item is
        honoured. Cards without a sparkline array just skip the chart.
    -->
    <section
        class="kpi-marquee group"
        @mouseenter="pause = true"
        @mouseleave="pause = false"
    >
        <!-- Manual nav — md+ only; doesn't compete with phone swipe. -->
        <button
            type="button"
            class="kpi-nav kpi-nav--prev"
            :aria-label="t('dashboard.marquee.prev', 'Previous')"
            @click="nudge(-1)"
        >
            <ChevronLeftIcon class="w-4 h-4" />
        </button>
        <button
            type="button"
            class="kpi-nav kpi-nav--next"
            :aria-label="t('dashboard.marquee.next', 'Next')"
            @click="nudge(1)"
        >
            <ChevronRightIcon class="w-4 h-4" />
        </button>

        <div
            ref="trackRef"
            class="kpi-track"
            :class="{ 'kpi-track--paused': pause || manualScroll, 'kpi-track--manual': manualScroll }"
            :style="trackStyle"
        >
            <div
                v-for="(kpi, i) in renderItems"
                :key="i + '-' + kpi.key"
                :class="['kpi-card', kpi.tone, kpi.urgent && 'kpi-card--urgent']"
            >
                <div class="flex items-center gap-3">
                    <div :class="['kpi-icon', kpi.tone + '-icon']">
                        <component :is="kpi.icon" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="kpi-label">{{ kpi.label }}</p>
                        <p class="kpi-value">
                            <AnimatedCounter
                                :value="kpi.value"
                                :decimals="kpi.decimals ?? 0"
                                :prefix="kpi.prefix ?? ''"
                                :suffix="kpi.suffix ?? ''"
                            />
                        </p>
                    </div>
                    <div v-if="kpi.delta != null" :class="['kpi-trend', kpi.delta >= 0 ? 'kpi-trend--up' : 'kpi-trend--down']">
                        <ArrowUpIcon v-if="kpi.delta >= 0" class="w-3 h-3" />
                        <ArrowDownIcon v-else class="w-3 h-3" />
                        {{ Math.abs(kpi.delta).toFixed(1) }}%
                    </div>
                </div>

                <!-- Mini sparkline — pure inline SVG so no chart lib. -->
                <div v-if="hasSparkline(kpi)" class="kpi-sparkline">
                    <svg :viewBox="`0 0 ${SPK_W} ${SPK_H}`" preserveAspectRatio="none" class="kpi-spk-svg">
                        <path :d="sparkAreaPath(kpi.sparkline)" :class="['kpi-spk-area', kpi.tone + '-spk-area']" />
                        <path :d="sparkLinePath(kpi.sparkline)" :class="['kpi-spk-line', kpi.tone + '-spk-line']" />
                    </svg>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowUpIcon, ArrowDownIcon, ChevronLeftIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline';
import AnimatedCounter from './AnimatedCounter.vue';

const props = defineProps({
    items: { type: Array, required: true },
});

const { t } = useI18n();
const pause = ref(false);
const trackRef = ref(null);

// Duplicate so the CSS-animated translate loops without a visible jump
const renderItems = computed(() => [...props.items, ...props.items]);

// Slow scroll for many items, faster for few — feels right at any count
const duration = computed(() => Math.max(20, props.items.length * 4));

// ── Manual nav state ─────────────────────────────────────────────────────
// When the user clicks a nav button we switch the track from CSS keyframe
// animation to a manual transform offset for one step. The auto-scroll
// re-engages after a short idle so the dashboard never feels "stuck".
const manualOffset = ref(0);
const manualScroll = ref(false);
const CARD_WIDTH_PX = 252;   // 240 card + 12 gap

const trackStyle = computed(() => {
    if (manualScroll.value) {
        return { transform: `translateX(${manualOffset.value}px)`, animationDuration: `${duration.value}s` };
    }
    return { animationDuration: `${duration.value}s` };
});

let resumeTimer = null;
function nudge(dir) {
    manualScroll.value = true;
    // Bound the offset to one full loop length so we don't disappear.
    const maxOffset = -(props.items.length * CARD_WIDTH_PX);
    manualOffset.value = Math.max(maxOffset, Math.min(0, manualOffset.value + dir * -CARD_WIDTH_PX));

    clearTimeout(resumeTimer);
    resumeTimer = setTimeout(() => {
        manualScroll.value = false;
        manualOffset.value = 0;
    }, 4000);   // resume auto-scroll after 4s of inactivity
}

// ── Sparkline rendering (inline SVG, no library) ─────────────────────────

const SPK_W = 120;
const SPK_H = 28;

function hasSparkline(kpi) {
    return Array.isArray(kpi.sparkline) && kpi.sparkline.length >= 2;
}
function sparkPoints(arr) {
    const vals = arr.map(v => Number(v) || 0);
    let min = Math.min(...vals);
    let max = Math.max(...vals);
    if (min === max) { min -= 1; max += 1; }
    const span = max - min;
    return vals.map((v, i) => ({
        x: (i / (vals.length - 1)) * SPK_W,
        y: SPK_H - 2 - ((v - min) / span) * (SPK_H - 4),
    }));
}
function sparkLinePath(arr) {
    const pts = sparkPoints(arr);
    return 'M ' + pts.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L ');
}
function sparkAreaPath(arr) {
    const pts = sparkPoints(arr);
    const line = pts.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L ');
    return `M 0,${SPK_H} L ${line} L ${SPK_W},${SPK_H} Z`;
}

// If parent swaps the items array (e.g. workspace switch) reset manual state.
watch(() => props.items, () => {
    manualScroll.value = false;
    manualOffset.value = 0;
    clearTimeout(resumeTimer);
});
</script>

<style scoped>
@reference '../../../css/app.css';

.kpi-marquee {
    @apply relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-50/80 to-white/80 dark:from-slate-900/60 dark:to-slate-950/40 border border-slate-200/60 dark:border-slate-800;
    -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 32px, #000 calc(100% - 32px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 32px, #000 calc(100% - 32px), transparent 100%);
}

/* Manual nav buttons — desktop only so phone swipe still feels native */
.kpi-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 30px; height: 30px;
    z-index: 3;
    display: none;
    align-items: center; justify-content: center;
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
    border-radius: 999px;
    color: var(--text-secondary);
    box-shadow: var(--elev-2);
    transition:
        background-color var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out),
        transform        var(--motion-fast) var(--motion-out);
}
@media (min-width: 768px) {
    .group:hover .kpi-nav { display: flex; }
}
.kpi-nav:hover { color: var(--text-primary); background: rgb(238 242 255); }
html.dark .kpi-nav:hover { background: rgb(67 56 202 / 0.18); }
.kpi-nav:active { transform: translateY(-50%) scale(0.92); }
.kpi-nav--prev { left: 8px; }
.kpi-nav--next { right: 8px; }

.kpi-track {
    display: flex;
    gap: 0.75rem;
    width: max-content;
    padding: 0.75rem;
    animation: kpi-scroll linear infinite;
}
.kpi-track--paused { animation-play-state: paused; }
.kpi-track--manual {
    animation: none !important;
    transition: transform 400ms var(--motion-spring);
}
.kpi-marquee:hover .kpi-track:not(.kpi-track--manual) { animation-play-state: paused; }

@keyframes kpi-scroll {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}

.kpi-card {
    @apply relative flex-shrink-0 w-60 px-4 py-3 rounded-xl backdrop-blur-sm border transition-all duration-200;
    background: linear-gradient(135deg, rgba(255,255,255,0.85), rgba(255,255,255,0.55));
    border-color: rgba(226,232,240,0.7);
    box-shadow: 0 1px 2px rgba(15,23,42,0.04), 0 8px 24px -12px rgba(15,23,42,0.08);
}
:global(.dark) .kpi-card {
    background: linear-gradient(135deg, rgba(15,23,42,0.6), rgba(15,23,42,0.3));
    border-color: rgba(51,65,85,0.5);
}
.kpi-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(15,23,42,0.06), 0 16px 32px -8px rgba(15,23,42,0.12);
}

.kpi-card--urgent::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 0.75rem;
    pointer-events: none;
    background: radial-gradient(circle at top right, rgba(244,63,94,0.18), transparent 65%);
    animation: kpi-pulse 2.5s ease-in-out infinite;
}
@keyframes kpi-pulse {
    0%, 100% { opacity: 0.4; }
    50%      { opacity: 0.9; }
}

.kpi-icon {
    @apply w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0;
}

/* Tone palettes — light + dark variants */
.indigo  { /* base only — additional styles in icon */ }
.indigo-icon  { @apply bg-indigo-100  text-indigo-700  dark:bg-indigo-900/40  dark:text-indigo-300; }
.emerald-icon { @apply bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300; }
.amber-icon   { @apply bg-amber-100   text-amber-700   dark:bg-amber-900/40   dark:text-amber-300; }
.rose-icon    { @apply bg-rose-100    text-rose-700    dark:bg-rose-900/40    dark:text-rose-300; }
.sky-icon     { @apply bg-sky-100     text-sky-700     dark:bg-sky-900/40     dark:text-sky-300; }
.violet-icon  { @apply bg-violet-100  text-violet-700  dark:bg-violet-900/40  dark:text-violet-300; }
.slate-icon   { @apply bg-slate-100   text-slate-700   dark:bg-slate-800      dark:text-slate-300; }

.kpi-label {
    @apply text-[10px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400 truncate;
}
.kpi-value {
    @apply text-base font-bold text-slate-900 dark:text-slate-100 tabular-nums leading-tight mt-0.5;
}

.kpi-trend {
    @apply flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0;
}
.kpi-trend--up   { @apply bg-emerald-100/70 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300; }
.kpi-trend--down { @apply bg-rose-100/70    text-rose-700    dark:bg-rose-900/30    dark:text-rose-300; }

/* ── Mini sparkline per card — phase AC Round 2 ────────────────────────── */
.kpi-sparkline {
    margin-top: 0.5rem;
    width: 100%;
    height: 28px;
    overflow: hidden;
}
.kpi-spk-svg { width: 100%; height: 100%; display: block; }
.kpi-spk-line {
    fill: none;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.kpi-spk-area { opacity: 0.18; }

/* Per-tone sparkline colors so the chart inherits the card's mood. */
.indigo-spk-line  { stroke: rgb(79 70 229); }
.emerald-spk-line { stroke: rgb(16 185 129); }
.amber-spk-line   { stroke: rgb(245 158 11); }
.rose-spk-line    { stroke: rgb(244 63 94); }
.sky-spk-line     { stroke: rgb(14 165 233); }
.violet-spk-line  { stroke: rgb(124 58 237); }
.slate-spk-line   { stroke: rgb(100 116 139); }

.indigo-spk-area  { fill: rgb(79 70 229); }
.emerald-spk-area { fill: rgb(16 185 129); }
.amber-spk-area   { fill: rgb(245 158 11); }
.rose-spk-area    { fill: rgb(244 63 94); }
.sky-spk-area     { fill: rgb(14 165 233); }
.violet-spk-area  { fill: rgb(124 58 237); }
.slate-spk-area   { fill: rgb(100 116 139); }

@media (prefers-reduced-motion: reduce) {
    .kpi-track { animation: none !important; }
}
</style>
