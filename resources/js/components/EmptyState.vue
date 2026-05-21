<template>
    <div :class="['empty-state', compact ? 'is-compact' : '']">
        <div v-if="icon" class="empty-icon">
            <component :is="icon" class="w-8 h-8" />
        </div>
        <p class="empty-title">{{ title }}</p>
        <p v-if="message" class="empty-message">{{ message }}</p>
        <div v-if="$slots.action || actionLabel" class="mt-4">
            <slot name="action">
                <RouterLink v-if="actionTo" :to="actionTo" class="empty-cta">
                    {{ actionLabel }}
                </RouterLink>
                <button v-else-if="actionLabel" @click="$emit('action')" class="empty-cta">
                    {{ actionLabel }}
                </button>
            </slot>
        </div>
    </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    title:       { type: String, required: true },
    message:     { type: String, default: '' },
    icon:        { type: [Object, Function], default: null },
    actionLabel: { type: String, default: '' },
    actionTo:    { type: [String, Object], default: null },
    compact:     { type: Boolean, default: false },
});
defineEmits(['action']);
</script>

<style scoped>
@reference '../../css/app.css';

.empty-state {
    @apply flex flex-col items-center justify-center text-center py-16 px-6;
}
.empty-state.is-compact { @apply py-8; }

.empty-icon {
    @apply w-14 h-14 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-4;
}
.empty-title {
    @apply text-sm font-semibold text-slate-700;
}
.empty-message {
    @apply text-xs text-slate-500 mt-1 max-w-sm;
}
.empty-cta {
    @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white
           text-sm font-medium hover:bg-indigo-700 transition-colors;
}
</style>
