<template>
    <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" class="overflow-visible">
        <!-- Filled area under the line -->
        <path v-if="points.length > 1" :d="areaPath" :fill="`url(#sk-${gradId})`" opacity="0.4" />
        <linearGradient :id="`sk-${gradId}`" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"  :stop-color="color" stop-opacity="0.5" />
            <stop offset="100%" :stop-color="color" stop-opacity="0" />
        </linearGradient>
        <!-- The line itself -->
        <path v-if="points.length > 1" :d="linePath" :stroke="color" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round" />
        <!-- Last point dot -->
        <circle v-if="points.length" :cx="lastX" :cy="lastY" r="2.2" :fill="color" />
    </svg>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    values: { type: Array,  default: () => [] },
    width:  { type: Number, default: 80 },
    height: { type: Number, default: 28 },
    color:  { type: String, default: '#6366f1' },
});

const gradId = Math.random().toString(36).slice(2, 8);

const points = computed(() => {
    const v = props.values.filter((n) => typeof n === 'number');
    if (v.length < 2) return [];
    const min = Math.min(...v);
    const max = Math.max(...v);
    const span = max - min || 1;
    const stepX = props.width / (v.length - 1);
    return v.map((y, i) => ({
        x: i * stepX,
        y: props.height - ((y - min) / span) * (props.height - 4) - 2,
    }));
});

const linePath = computed(() => {
    if (!points.value.length) return '';
    return points.value.reduce((acc, p, i) => acc + (i === 0 ? `M${p.x},${p.y}` : ` L${p.x},${p.y}`), '');
});

const areaPath = computed(() => {
    if (!points.value.length) return '';
    const line = linePath.value;
    const last = points.value[points.value.length - 1];
    return `${line} L${last.x},${props.height} L0,${props.height} Z`;
});

const lastX = computed(() => points.value[points.value.length - 1]?.x ?? 0);
const lastY = computed(() => points.value[points.value.length - 1]?.y ?? 0);
</script>
