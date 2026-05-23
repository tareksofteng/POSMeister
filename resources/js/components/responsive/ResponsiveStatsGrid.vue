<template>
    <!--
        KPI grid that scales:
          phone   : 1 column
          tablet  : 2
          laptop  : 4
          wide    : `wide` columns (default 6)
    -->
    <div :class="[
        'grid gap-3 sm:gap-4',
        'grid-cols-1 sm:grid-cols-2',
        cols >= 3 && 'md:grid-cols-3',
        cols >= 4 && 'lg:grid-cols-4',
        wide  >= 5 && 'xl:grid-cols-5',
        wide  >= 6 && '2xl:grid-cols-6',
    ]">
        <slot />
    </div>
</template>

<script setup>
defineProps({
    cols: { type: Number, default: 4 },
    wide: { type: Number, default: 6 },
});
</script>
