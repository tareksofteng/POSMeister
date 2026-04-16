<template>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-500 truncate">{{ label }}</p>
            <div :class="['w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0', iconColor]">
                <component :is="icon" class="w-5 h-5" />
            </div>
        </div>
        <div class="mt-3">
            <p class="text-2xl font-bold text-gray-900 tracking-tight">{{ value }}</p>
            <p v-if="change" class="mt-0.5 text-xs text-gray-400">{{ change }}</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label:     { type: String,  required: true },
    value:     { type: String,  required: true },
    change:    { type: String,  default: '' },
    icon:      { type: Object,  required: true },
    iconColor: { type: String,  default: 'bg-gray-100 text-gray-600' },
});
</script>
