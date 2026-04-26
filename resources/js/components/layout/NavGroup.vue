<template>
    <!-- ── Collapsed sidebar: show icon only, tinted when a child is active ── -->
    <button
        v-if="collapsed"
        :title="label"
        :class="[
            'w-full flex items-center justify-center px-3 py-2 rounded-lg transition-colors duration-150',
            isChildActive
                ? 'bg-indigo-600/20 text-indigo-300'
                : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
        ]"
    >
        <slot name="icon" />
    </button>

    <!-- ── Expanded sidebar: full accordion ── -->
    <template v-else>
        <button
            @click="open = !open"
            :class="[
                'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150',
                isChildActive
                    ? 'text-indigo-300 bg-indigo-600/10'
                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
            ]"
        >
            <slot name="icon" />
            <span class="flex-1 text-left truncate">{{ label }}</span>
            <ChevronDownIcon
                :class="[
                    'w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200',
                    open ? 'rotate-180' : '',
                ]"
            />
        </button>

        <!-- Children: indented with a left border guide -->
        <div v-show="open" class="mt-0.5 ml-2 pl-3 border-l border-slate-700/60 space-y-0.5">
            <slot />
        </div>
    </template>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    collapsed:   { type: Boolean, default: false },
    label:       { type: String,  required: true },
    childRoutes: { type: Array,   default: () => [] },
});

const route         = useRoute();
const isChildActive = computed(() => props.childRoutes.includes(String(route.name)));
const open          = ref(isChildActive.value);

// Auto-open when navigating into a child route
watch(isChildActive, (active) => { if (active) open.value = true; });
</script>

<style scoped>
@reference '../../../css/app.css';
</style>
