<template>
    <div :class="['inline-flex items-center justify-center rounded-full overflow-hidden font-semibold select-none', sizeClass, bgClass]">
        <img v-if="src" :src="src" :alt="alt" class="w-full h-full object-cover" />
        <span v-else :class="textClass">{{ initials }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    src:  { type: String, default: '' },
    name: { type: String, default: '' },
    alt:  { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
});

const initials = computed(() => {
    if (!props.name) return '?';
    return props.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(p => p[0]?.toUpperCase())
        .join('');
});

const sizeClass = computed(() => ({
    sm: 'w-8 h-8 text-xs',
    md: 'w-10 h-10 text-sm',
    lg: 'w-14 h-14 text-base',
    xl: 'w-24 h-24 text-2xl',
}[props.size] || 'w-10 h-10 text-sm'));

const textClass = computed(() => 'tracking-wide');

// Pick a stable colour per name, so each employee has a consistent avatar
const bgClass = computed(() => {
    if (props.src) return 'bg-slate-100';
    const palette = [
        'bg-indigo-100 text-indigo-700',
        'bg-emerald-100 text-emerald-700',
        'bg-amber-100 text-amber-700',
        'bg-rose-100 text-rose-700',
        'bg-sky-100 text-sky-700',
        'bg-violet-100 text-violet-700',
        'bg-teal-100 text-teal-700',
    ];
    if (!props.name) return palette[0];
    let h = 0;
    for (let i = 0; i < props.name.length; i++) h = (h * 31 + props.name.charCodeAt(i)) >>> 0;
    return palette[h % palette.length];
});
</script>
