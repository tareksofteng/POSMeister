<template>
    <RouterLink
        :to="to"
        :title="collapsed ? label : undefined"
        custom
        v-slot="{ isActive, navigate }"
    >
        <button
            @click="navigate"
            :class="[
                'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150',
                isActive
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                collapsed ? 'justify-center' : '',
            ]"
        >
            <slot name="icon" />
            <span v-if="!collapsed" class="truncate">{{ label }}</span>
        </button>
    </RouterLink>
</template>

<script setup>
defineProps({
    to:        { type: [String, Object], required: true },
    label:     { type: String,           required: true },
    collapsed: { type: Boolean,          default: false },
});
</script>
