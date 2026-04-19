<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ label }}
            <span v-if="required" class="text-red-500 ml-0.5">*</span>
        </label>
        <slot />
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</p>
    </div>
</template>

<script setup>
defineProps({
    label:    { type: String,  default: '' },
    error:    { type: String,  default: '' },
    hint:     { type: String,  default: '' },
    required: { type: Boolean, default: false },
});
</script>
