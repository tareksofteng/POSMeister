<template>
    <div :class="['sk-wrap', kind === 'table' ? 'sk-table' : '']">
        <template v-if="kind === 'card'">
            <div v-for="i in count" :key="i" class="sk-card">
                <div class="sk-bar w-1/3 h-3"></div>
                <div class="sk-bar w-2/3 h-6 mt-2"></div>
                <div class="sk-bar w-1/2 h-2.5 mt-2"></div>
            </div>
        </template>

        <template v-else-if="kind === 'table'">
            <div v-for="i in count" :key="i" class="sk-row">
                <div class="sk-bar w-12 h-3"></div>
                <div class="sk-bar flex-1 h-3"></div>
                <div class="sk-bar w-24 h-3"></div>
                <div class="sk-bar w-16 h-3"></div>
            </div>
        </template>

        <template v-else-if="kind === 'list'">
            <div v-for="i in count" :key="i" class="sk-list-row">
                <div class="sk-circle"></div>
                <div class="flex-1 space-y-2">
                    <div class="sk-bar w-3/4 h-3"></div>
                    <div class="sk-bar w-1/2 h-2.5"></div>
                </div>
                <div class="sk-bar w-16 h-3"></div>
            </div>
        </template>

        <div v-else class="sk-bar" :style="{ width: width, height: height }"></div>
    </div>
</template>

<script setup>
defineProps({
    kind:   { type: String, default: 'bar' },      // bar | card | table | list
    count:  { type: Number, default: 3 },
    width:  { type: String, default: '100%' },
    height: { type: String, default: '1rem' },
});
</script>

<style scoped>
@reference '../../css/app.css';

.sk-wrap {
    @apply space-y-3;
}
.sk-wrap.sk-table { @apply space-y-2; }

.sk-card {
    @apply bg-white border border-slate-200 rounded-xl p-4;
}

.sk-row {
    @apply flex items-center gap-4 px-4 py-3 bg-white border border-slate-100 rounded-lg;
}
.sk-list-row {
    @apply flex items-center gap-3 px-4 py-3 bg-white border border-slate-100 rounded-lg;
}
.sk-circle {
    @apply w-9 h-9 rounded-full flex-shrink-0;
    background: linear-gradient(90deg, rgb(241 245 249), rgb(226 232 240), rgb(241 245 249));
    background-size: 200% 100%;
    animation: shimmer 1.4s ease-in-out infinite;
}

.sk-bar {
    background: linear-gradient(90deg, rgb(241 245 249), rgb(226 232 240), rgb(241 245 249));
    background-size: 200% 100%;
    animation: shimmer 1.4s ease-in-out infinite;
    border-radius: 4px;
}

@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
