<template>
    <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium', tone]">
        <span :class="['w-1.5 h-1.5 rounded-full', dot]"></span>
        {{ t(`hrm.status_${status}`) }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: { type: String, required: true },
});

const { t } = useI18n();

const palette = {
    active:     { tone: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
    inactive:   { tone: 'bg-slate-100 text-slate-700',     dot: 'bg-slate-400' },
    terminated: { tone: 'bg-rose-100 text-rose-700',       dot: 'bg-rose-500' },
    resigned:   { tone: 'bg-amber-100 text-amber-700',     dot: 'bg-amber-500' },
};

const tone = computed(() => (palette[props.status] ?? palette.inactive).tone);
const dot  = computed(() => (palette[props.status] ?? palette.inactive).dot);
</script>
