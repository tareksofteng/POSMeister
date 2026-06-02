<template>
    <span :class="$attrs.class">{{ formatted }}</span>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    value:    { type: Number,  default: 0 },
    duration: { type: Number,  default: 700 },
    decimals: { type: Number,  default: 0 },
    /** Pass a function (n) => string to customise the final rendering. */
    format:   { type: Function, default: null },
    /** Prefix/suffix shorthand for currency-style displays. */
    prefix:   { type: String, default: '' },
    suffix:   { type: String, default: '' },
});

const display = ref(0);
let raf = null;
let lastTarget = 0;

function animate(from, to) {
    cancelAnimationFrame(raf);
    const start = performance.now();
    const tick = (now) => {
        const t = Math.min((now - start) / props.duration, 1);
        const eased = 1 - Math.pow(1 - t, 3);   // easeOutCubic
        display.value = from + (to - from) * eased;
        if (t < 1) raf = requestAnimationFrame(tick);
        else      display.value = to;
    };
    raf = requestAnimationFrame(tick);
}

watch(() => props.value, (v) => {
    animate(lastTarget, v);
    lastTarget = v;
});

onMounted(() => {
    lastTarget = props.value;
    animate(0, props.value);
});

import { computed } from 'vue';
const formatted = computed(() => {
    if (props.format) return props.format(display.value);
    const fixed = Number(display.value).toLocaleString(undefined, {
        minimumFractionDigits: props.decimals,
        maximumFractionDigits: props.decimals,
    });
    return `${props.prefix}${fixed}${props.suffix}`;
});
</script>
