<template>
    <div class="ss-root" ref="rootEl">
        <!-- Trigger -->
        <button
            type="button"
            @click="toggle"
            :class="['ss-trigger', open ? 'is-open' : '', disabled ? 'is-disabled' : '']"
            :disabled="disabled"
        >
            <span v-if="selected" class="truncate text-left">
                {{ selected.label }}
                <span v-if="selected.sub" class="text-xs text-slate-400 ml-1">{{ selected.sub }}</span>
            </span>
            <span v-else class="truncate text-left text-slate-400">{{ placeholder }}</span>
            <ChevronDownIcon :class="['w-4 h-4 ml-2 flex-shrink-0 transition-transform', open ? 'rotate-180' : '']" />
        </button>

        <!-- Dropdown -->
        <Transition name="ss-fade">
            <div v-if="open" class="ss-panel">
                <div class="ss-search">
                    <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
                    <input
                        ref="searchEl"
                        v-model="query"
                        @keydown="onKey"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="ss-search-input"
                    />
                    <button v-if="query" type="button" @click="query = ''" class="ss-clear">
                        <XMarkIcon class="w-3.5 h-3.5" />
                    </button>
                </div>

                <ul class="ss-list" ref="listEl">
                    <li v-if="clearable && modelValue !== null && modelValue !== undefined && modelValue !== ''"
                        @click="select(null)"
                        class="ss-option is-clear">
                        <span class="text-xs text-slate-500 italic">{{ clearLabel }}</span>
                    </li>
                    <li
                        v-for="(opt, i) in filtered"
                        :key="opt.value"
                        @click="select(opt.value)"
                        :class="['ss-option', i === highlight ? 'is-highlighted' : '', opt.value === modelValue ? 'is-selected' : '']"
                        @mouseenter="highlight = i"
                    >
                        <span class="truncate flex-1">{{ opt.label }}</span>
                        <span v-if="opt.sub" class="text-xs text-slate-400 ml-2 flex-shrink-0">{{ opt.sub }}</span>
                    </li>
                    <li v-if="filtered.length === 0" class="ss-empty">{{ emptyText }}</li>
                </ul>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { ChevronDownIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue:        { type: [String, Number, null], default: null },
    options:           { type: Array, required: true },   // [{ value, label, sub? }]
    placeholder:       { type: String, default: '— Select —' },
    searchPlaceholder: { type: String, default: 'Search…' },
    emptyText:         { type: String, default: 'No matches' },
    clearable:         { type: Boolean, default: true },
    clearLabel:        { type: String, default: 'Clear selection' },
    disabled:          { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open      = ref(false);
const query     = ref('');
const highlight = ref(0);
const rootEl    = ref(null);
const searchEl  = ref(null);
const listEl    = ref(null);

const selected = computed(() =>
    props.options.find(o => o.value === props.modelValue) ?? null
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(o => {
        const haystack = (o.label + ' ' + (o.sub ?? '')).toLowerCase();
        return haystack.includes(q);
    });
});

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        nextTick(() => searchEl.value?.focus());
    }
}

function select(value) {
    emit('update:modelValue', value);
    emit('change', value);
    open.value = false;
    query.value = '';
}

function onKey(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlight.value = Math.min(highlight.value + 1, filtered.value.length - 1);
        scrollHighlightIntoView();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlight.value = Math.max(highlight.value - 1, 0);
        scrollHighlightIntoView();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const opt = filtered.value[highlight.value];
        if (opt) select(opt.value);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
}

function scrollHighlightIntoView() {
    nextTick(() => {
        const el = listEl.value?.querySelector('.is-highlighted');
        el?.scrollIntoView({ block: 'nearest' });
    });
}

watch(query, () => { highlight.value = 0; });

function onDocClick(e) {
    if (open.value && rootEl.value && !rootEl.value.contains(e.target)) {
        open.value = false;
    }
}
document.addEventListener('click', onDocClick);
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<style scoped>
@reference '../../css/app.css';

.ss-root { @apply relative w-full; }

.ss-trigger {
    @apply w-full flex items-center justify-between px-3 py-2 text-sm
           border border-slate-300 rounded-lg bg-white text-slate-800
           transition-colors hover:border-slate-400 focus:outline-none
           focus:ring-2 focus:ring-indigo-500 focus:border-transparent;
}
.ss-trigger.is-open { @apply ring-2 ring-indigo-500 border-transparent; }
.ss-trigger.is-disabled { @apply bg-slate-50 text-slate-400 cursor-not-allowed; }

.ss-panel {
    @apply absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-lg
           shadow-lg overflow-hidden;
    min-width: 240px;
}

.ss-search {
    @apply flex items-center gap-2 px-3 py-2 border-b border-slate-100;
}
.ss-search-input {
    @apply flex-1 text-sm bg-transparent outline-none placeholder:text-slate-400;
}
.ss-clear {
    @apply text-slate-400 hover:text-slate-700;
}

.ss-list {
    @apply max-h-64 overflow-y-auto py-1;
}

.ss-option {
    @apply flex items-center px-3 py-2 text-sm text-slate-700 cursor-pointer
           transition-colors;
}
.ss-option.is-highlighted { @apply bg-indigo-50 text-indigo-700; }
.ss-option.is-selected    { @apply bg-indigo-100 text-indigo-800 font-medium; }
.ss-option.is-clear { @apply justify-center text-xs italic; }
.ss-option.is-clear:hover { @apply bg-rose-50 text-rose-700; }

.ss-empty {
    @apply px-3 py-6 text-center text-xs text-slate-400 italic;
}

/* Smooth open animation */
.ss-fade-enter-active, .ss-fade-leave-active { transition: opacity 150ms ease, transform 150ms ease; }
.ss-fade-enter-from, .ss-fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
