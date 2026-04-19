<template>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-4">
        <div class="flex items-start justify-between">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">{{ label }}</p>
            <div :class="['w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0', iconBg]">
                <component :is="icon" class="w-5 h-5" :class="iconColor" />
            </div>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-2 animate-pulse">
            <div class="h-8 bg-gray-100 rounded w-24"></div>
            <div class="h-3 bg-gray-100 rounded w-32"></div>
        </div>

        <div v-else>
            <p class="text-3xl font-bold text-gray-900 tracking-tight tabular-nums">{{ value }}</p>
            <p v-if="sub" class="mt-1 text-xs text-gray-400">{{ sub }}</p>
        </div>

        <!-- Optional bottom bar (e.g. trend) -->
        <div v-if="trend && !loading" class="flex items-center gap-1.5 pt-1 border-t border-gray-100">
            <span :class="['text-xs font-medium', trend.positive ? 'text-emerald-600' : 'text-red-500']">
                {{ trend.positive ? '▲' : '▼' }} {{ trend.value }}
            </span>
            <span class="text-xs text-gray-400">{{ trend.label }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label:    { type: String,  required: true },
    value:    { type: [String, Number], default: '—' },
    sub:      { type: String,  default: '' },
    icon:     { type: Object,  required: true },
    iconBg:   { type: String,  default: 'bg-gray-100' },
    iconColor:{ type: String,  default: 'text-gray-500' },
    loading:  { type: Boolean, default: false },
    trend:    { type: Object,  default: null },
});
</script>
