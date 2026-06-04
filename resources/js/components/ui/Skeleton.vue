<template>
    <!--
        A single shimmer placeholder. Compose multiples to build list,
        card and table loading states. Honours <prefers-reduced-motion>
        so users with motion sensitivity see a static block instead.
    -->
    <span
        :class="['skeleton', rounded, block ? 'block' : 'inline-block']"
        :style="{ width, height }"
        aria-hidden="true"
    />
</template>

<script setup>
defineProps({
    width:   { type: String,  default: '100%' },
    height:  { type: String,  default: '14px' },
    rounded: { type: String,  default: 'rounded' }, // 'rounded' | 'rounded-md' | 'rounded-full' | etc.
    block:   { type: Boolean, default: true },
});
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
</style>
