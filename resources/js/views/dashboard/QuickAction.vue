<template>
    <button
        :class="[
            'w-full flex items-center gap-3.5 px-4 py-3 rounded-lg border text-left transition-colors',
            colorMap[color]?.border ?? 'border-gray-100',
            colorMap[color]?.hover  ?? 'hover:bg-gray-50',
        ]"
    >
        <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', colorMap[color]?.icon ?? 'bg-gray-100 text-gray-600']">
            <component :is="icon" class="w-4 h-4" />
        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-800 truncate">{{ label }}</p>
            <p class="text-xs text-gray-400 truncate">{{ description }}</p>
        </div>
        <ChevronRightIcon class="w-4 h-4 text-gray-300 ml-auto flex-shrink-0" />
    </button>
</template>

<script setup>
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    label:       { type: String, required: true },
    description: { type: String, default: '' },
    icon:        { type: Object, required: true },
    color:       { type: String, default: 'indigo' },
});

const colorMap = {
    indigo: { border: 'border-indigo-100', hover: 'hover:bg-indigo-50', icon: 'bg-indigo-100 text-indigo-600' },
    violet: { border: 'border-violet-100', hover: 'hover:bg-violet-50', icon: 'bg-violet-100 text-violet-600' },
    amber:  { border: 'border-amber-100',  hover: 'hover:bg-amber-50',  icon: 'bg-amber-100 text-amber-600' },
    green:  { border: 'border-green-100',  hover: 'hover:bg-green-50',  icon: 'bg-green-100 text-green-600' },
};
</script>
