<template>
    <!--
        Sticky bottom action bar — shows on mobile only by default. Use for
        primary CTAs that should always be reachable (POS checkout,
        Save/Publish on long forms, etc.). Honors the iOS safe-area.
    -->
    <div
        v-if="show"
        :class="[
            'fixed left-0 right-0 bottom-0 z-30 border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 pb-safe',
            hideOnDesktop && 'lg:hidden',
        ]"
    >
        <div class="px-3 sm:px-4 py-2.5 flex items-center gap-2">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: true },
    hideOnDesktop: { type: Boolean, default: true },
});
</script>
