<template>
    <!--
        Skeleton — Phase AA. Single primitive with two roles:

        1. As a raw shimmer block (the original use case — keeps every
           existing import working exactly as before).
        2. As a high-level placeholder via `variant`:
             - kpi-card     mini KPI tile shape (label + number + delta)
             - row          a list row (avatar + 2 lines + trailing chip)
             - table        N×cols shimmer grid
             - chart        chart frame with skeleton bars
             - paragraph    3 lines of text
             - avatar       circle

        Honours <prefers-reduced-motion> by collapsing the shimmer to a
        steady tone so motion-sensitive users still see structure.
    -->
    <template v-if="!variant">
        <span
            :class="['skeleton', rounded, block ? 'block' : 'inline-block']"
            :style="{ width, height }"
            aria-hidden="true"
        />
    </template>

    <div v-else-if="variant === 'kpi-card'" class="sk-kpi" aria-hidden="true">
        <div class="sk-kpi-row">
            <span class="skeleton rounded-lg" style="width:32px;height:32px;" />
            <span class="skeleton rounded" style="width:64px;height:10px;" />
        </div>
        <span class="skeleton rounded-md mt-3" style="width:60%;height:22px;" />
        <span class="skeleton rounded mt-2" style="width:40%;height:10px;" />
    </div>

    <div v-else-if="variant === 'row'" class="sk-row" aria-hidden="true">
        <span class="skeleton rounded-full" style="width:36px;height:36px;flex-shrink:0;" />
        <div class="sk-row-text">
            <span class="skeleton rounded" style="width:55%;height:12px;" />
            <span class="skeleton rounded mt-1.5" style="width:35%;height:10px;" />
        </div>
        <span class="skeleton rounded-md" style="width:48px;height:18px;flex-shrink:0;" />
    </div>

    <div v-else-if="variant === 'table'" class="sk-table" aria-hidden="true">
        <div v-for="r in rows" :key="r" class="sk-table-row">
            <span
                v-for="c in cols"
                :key="c"
                class="skeleton rounded"
                :style="{ height: '12px', flex: c === 1 ? '2 1 0' : '1 1 0' }"
            />
        </div>
    </div>

    <div v-else-if="variant === 'chart'" class="sk-chart" aria-hidden="true">
        <span class="skeleton rounded" style="width:32%;height:12px;" />
        <div class="sk-chart-bars">
            <span v-for="(h, i) in chartBars" :key="i" class="skeleton rounded-t" :style="{ height: h }" />
        </div>
    </div>

    <div v-else-if="variant === 'paragraph'" class="sk-para" aria-hidden="true">
        <span class="skeleton rounded" style="width:100%;height:10px;" />
        <span class="skeleton rounded mt-2" style="width:92%;height:10px;" />
        <span class="skeleton rounded mt-2" style="width:64%;height:10px;" />
    </div>

    <span
        v-else-if="variant === 'avatar'"
        class="skeleton rounded-full inline-block align-middle"
        :style="{ width: avatarSize, height: avatarSize }"
        aria-hidden="true"
    />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Raw block size — used when no variant. */
    width:   { type: String,  default: '100%' },
    height:  { type: String,  default: '14px' },
    rounded: { type: String,  default: 'rounded' },
    block:   { type: Boolean, default: true },
    /** High-level placeholder shape. */
    variant: { type: String,  default: '' },
    /** Table variant: row + column counts. */
    rows:    { type: Number,  default: 5 },
    cols:    { type: Number,  default: 4 },
    /** Avatar variant: circle size. */
    avatarSize: { type: String, default: '40px' },
});

// Deterministic but lively chart bar heights — avoids Math.random()
// which would re-roll on every render and look glitchy.
const chartBars = computed(() => [
    '38%', '64%', '52%', '78%', '44%', '70%', '58%', '85%', '50%', '66%',
]);
</script>

<style scoped>
@reference '../../../css/app.css';

.skeleton {
    background: linear-gradient(
        90deg,
        rgba(148, 163, 184, 0.12) 0%,
        rgba(148, 163, 184, 0.22) 50%,
        rgba(148, 163, 184, 0.12) 100%
    );
    background-size: 200% 100%;
    animation: skel-shimmer 1.3s ease-in-out infinite;
}
@keyframes skel-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
@media (prefers-reduced-motion: reduce) {
    .skeleton { animation: none; background: rgba(148, 163, 184, 0.18); }
}

.sk-kpi {
    padding: 1rem 1.125rem;
    border: 1px solid var(--border-default);
    border-radius: 0.875rem;
    background: var(--surface-raised);
}
.sk-kpi-row {
    display: flex; align-items: center; justify-content: space-between;
}

.sk-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 0;
}
.sk-row-text { flex: 1 1 0; min-width: 0; }

.sk-table {
    display: flex; flex-direction: column; gap: 0.875rem;
    padding: 0.75rem 0;
}
.sk-table-row {
    display: flex; align-items: center; gap: 1rem;
}

.sk-chart {
    padding: 1rem;
    border: 1px solid var(--border-default);
    border-radius: 0.875rem;
    background: var(--surface-raised);
}
.sk-chart-bars {
    display: flex; align-items: flex-end; gap: 8px;
    height: 96px; margin-top: 1rem;
}
.sk-chart-bars > span { flex: 1 1 0; min-width: 8px; border-radius: 4px 4px 0 0; }

.sk-para { display: flex; flex-direction: column; }
</style>
