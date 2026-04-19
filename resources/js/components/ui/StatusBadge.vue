<template>
    <span :class="badgeClass">
        <span :class="dotClass" />
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    active:     { type: Boolean, required: true },
    trueLabel:  { type: String,  default: 'Active' },
    falseLabel: { type: String,  default: 'Inactive' },
});

const label = computed(() => props.active ? props.trueLabel : props.falseLabel);

const badgeClass = computed(() =>
    props.active
        ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
        : 'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20'
);

const dotClass = computed(() =>
    props.active ? 'h-1.5 w-1.5 rounded-full bg-emerald-500' : 'h-1.5 w-1.5 rounded-full bg-gray-400'
);
</script>
