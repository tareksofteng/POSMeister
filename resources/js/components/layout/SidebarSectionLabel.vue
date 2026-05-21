<template>
    <div class="section-label">
        <span class="section-label-text">{{ label }}</span>
        <span class="section-label-line" aria-hidden="true"></span>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
});
</script>

<style scoped>
@reference '../../../css/app.css';

.section-label {
    @apply mt-5 mb-1.5 px-3 flex items-center gap-2 select-none;
}

.section-label-text {
    @apply text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500;
}

.section-label-line {
    @apply flex-1 h-px;
    background: linear-gradient(to right, rgb(51 65 85 / 0.6), transparent);
}
</style>
