<template>
    <p class="mt-4 mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-slate-600 select-none">
        {{ label }}
    </p>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
});
</script>
