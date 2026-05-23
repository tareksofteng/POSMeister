<template>
    <div :class="[
        'mx-auto w-full',
        maxWidthClass,
        // Tighter padding on mobile, generous on desktop
        dense ? 'p-3 sm:p-4 lg:p-6' : 'p-4 sm:p-6 lg:p-8',
        'space-y-4 sm:space-y-5 lg:space-y-6',
    ]">
        <header v-if="title || $slots.header" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p v-if="eyebrow" class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ eyebrow }}</p>
                <h1 v-if="title" class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight truncate">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500 line-clamp-2 sm:line-clamp-none">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title:    { type: String, default: '' },
    eyebrow:  { type: String, default: '' },
    subtitle: { type: String, default: '' },
    /** Max content width; matches our existing convention. */
    width:    { type: String, default: '7xl' },
    /** Tightens padding for data-heavy screens (POS, tables). */
    dense:    { type: Boolean, default: false },
});

const maxWidthClass = computed(() => ({
    sm:   'max-w-screen-sm',
    md:   'max-w-screen-md',
    lg:   'max-w-screen-lg',
    xl:   'max-w-screen-xl',
    '2xl':'max-w-screen-2xl',
    '5xl':'max-w-5xl',
    '6xl':'max-w-6xl',
    '7xl':'max-w-7xl',
    full: 'max-w-full',
})[props.width] || 'max-w-7xl');
</script>
