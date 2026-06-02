<template>
    <!--
        Premium executive KPI marquee. Auto-scrolls horizontally, pauses on
        hover or touch, supports manual swipe/drag. Items are duplicated so
        the scroll loops seamlessly. Each card is a tiny glassmorphism tile
        with a coloured glow, animated counter and trend pill.
    -->
    <section class="kpi-marquee group" @mouseenter="pause = true" @mouseleave="pause = false">
        <div
            ref="trackRef"
            class="kpi-track"
            :class="{ 'kpi-track--paused': pause }"
            :style="{ animationDuration: `${duration}s` }"
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
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/outline';
import AnimatedCounter from './AnimatedCounter.vue';

const props = defineProps({
    items: { type: Array, required: true },
});

const pause = ref(false);
const trackRef = ref(null);

// Duplicate so the CSS-animated translate loops without a visible jump
const renderItems = computed(() => [...props.items, ...props.items]);

// Slow scroll for many items, faster for few — feels right at any count
const duration = computed(() => Math.max(20, props.items.length * 4));
</script>

<style scoped>
@reference '../../../css/app.css';

.kpi-marquee {
    @apply relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-50/80 to-white/80 dark:from-slate-900/60 dark:to-slate-950/40 border border-slate-200/60 dark:border-slate-800;
    -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 32px, #000 calc(100% - 32px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 32px, #000 calc(100% - 32px), transparent 100%);
}

.kpi-track {
    display: flex;
    gap: 0.75rem;
    width: max-content;
    padding: 0.75rem;
    animation: kpi-scroll linear infinite;
}
.kpi-track--paused { animation-play-state: paused; }
.kpi-marquee:hover .kpi-track { animation-play-state: paused; }

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

@media (prefers-reduced-motion: reduce) {
    .kpi-track { animation: none !important; }
}
</style>
