<template>
    <!--
        Premium empty state — replaces the generic "No data found" message
        seen across list views. The icon sits in a soft tinted disc so it
        carries the same visual weight as the heading without overwhelming.
        Optional <action> slot for a primary CTA.
    -->
    <div class="empty-state">
        <div :class="['empty-icon', toneRingClass]">
            <component :is="icon" :class="['w-7 h-7 sm:w-8 sm:h-8', toneIconClass]" />
        </div>
        <h3 class="empty-title">{{ title }}</h3>
        <p v-if="description" class="empty-desc">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4 sm:mt-5">
            <slot name="action" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { InboxIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    icon:        { type: Object,  default: () => InboxIcon },
    title:       { type: String,  required: true },
    description: { type: String,  default: '' },
    tone:        { type: String,  default: 'slate' }, // slate | indigo | emerald | amber | rose
});

const toneRingClass = computed(() => ({
    slate:   'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300',
    indigo:  'bg-indigo-50  text-indigo-500  dark:bg-indigo-900/30  dark:text-indigo-300',
    emerald: 'bg-emerald-50 text-emerald-500 dark:bg-emerald-900/30 dark:text-emerald-300',
    amber:   'bg-amber-50   text-amber-500   dark:bg-amber-900/30   dark:text-amber-300',
    rose:    'bg-rose-50    text-rose-500    dark:bg-rose-900/30    dark:text-rose-300',
}[props.tone] || 'bg-slate-100 text-slate-500'));
const toneIconClass = computed(() => 'opacity-90');
</script>

<style scoped>
@reference '../../../css/app.css';

.empty-state {
    @apply flex flex-col items-center justify-center text-center py-12 sm:py-16 px-6;
}
.empty-icon {
    @apply w-14 h-14 sm:w-16 sm:h-16 rounded-2xl grid place-items-center mb-4 sm:mb-5;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04), 0 8px 24px -16px rgba(15,23,42,0.08);
}
.empty-title {
    @apply text-base sm:text-lg font-semibold text-slate-800 dark:text-slate-100;
}
.empty-desc {
    @apply mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm;
}
</style>
