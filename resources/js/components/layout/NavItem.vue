<template>
    <!-- Active route — renders a real <a href> so middle-click / Ctrl+click open new tabs -->
    <RouterLink
        v-if="!disabled"
        :to="to"
        :title="collapsed ? label : undefined"
        custom
        v-slot="{ isActive, navigate, href }"
    >
        <a
            :href="href"
            @click="handleNav($event, navigate)"
            :class="[
                'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150',
                isActive
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                collapsed ? 'justify-center' : '',
            ]"
        >
            <slot name="icon" />
            <span v-if="!collapsed" class="truncate flex-1 text-left">{{ label }}</span>
        </a>
    </RouterLink>

    <!-- Disabled / coming soon -->
    <button
        v-else
        :title="collapsed ? label : undefined"
        :class="[
            'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium cursor-not-allowed',
            'text-slate-600',
            collapsed ? 'justify-center' : '',
        ]"
        tabindex="-1"
        disabled
    >
        <slot name="icon" />
        <span v-if="!collapsed" class="truncate flex-1 text-left">{{ label }}</span>
        <span v-if="!collapsed" class="flex-shrink-0 text-xs font-medium text-slate-600 bg-slate-800 px-1.5 py-0.5 rounded">
            Soon
        </span>
    </button>
</template>

<script setup>
defineProps({
    to:        { type: [String, Object], default: '/' },
    label:     { type: String,           required: true },
    collapsed: { type: Boolean,          default: false },
    disabled:  { type: Boolean,          default: false },
});

// Plain left-click → SPA navigation.
// Ctrl / Meta / Shift / middle-click → let browser open a new tab via href.
// NOTE: navigate() must be called WITHOUT the event — Vue Router's guardEvent()
// returns early when e.defaultPrevented is true, which we set right before it.
function handleNav(event, navigate) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    navigate();
}
</script>
