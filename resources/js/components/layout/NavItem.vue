<template>
    <!-- Active route renders as a real <a href> so middle-click / Ctrl+click open new tabs. -->
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
                'nav-item',
                isActive ? 'is-active' : '',
                collapsed ? 'is-collapsed' : '',
            ]"
        >
            <span v-if="isActive && !collapsed" class="nav-indicator" aria-hidden="true"></span>
            <span class="nav-item-icon">
                <slot name="icon" />
            </span>
            <span v-if="!collapsed" class="truncate flex-1 text-left">{{ label }}</span>
        </a>
    </RouterLink>

    <!-- Disabled / coming-soon item -->
    <button
        v-else
        :title="collapsed ? label : undefined"
        class="nav-item is-disabled"
        :class="collapsed ? 'is-collapsed' : ''"
        tabindex="-1"
        disabled
    >
        <span class="nav-item-icon">
            <slot name="icon" />
        </span>
        <span v-if="!collapsed" class="truncate flex-1 text-left">{{ label }}</span>
        <span v-if="!collapsed" class="nav-soon-badge">Soon</span>
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
// Ctrl/Meta/Shift/middle-click → let browser open a new tab via href.
function handleNav(event, navigate) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    navigate();
}
</script>

<style scoped>
@reference '../../../css/app.css';

.nav-item {
    @apply relative w-full flex items-center gap-3 px-3 py-2 rounded-lg
           text-sm font-medium text-slate-400
           transition-all duration-200;
}
.nav-item:hover:not(.is-disabled):not(.is-active) {
    @apply bg-slate-800/60 text-slate-100;
}
.nav-item:hover:not(.is-disabled) .nav-item-icon { transform: translateX(1px); }

.nav-item.is-collapsed { @apply justify-center; }

/* Active route: indigo gradient + soft shadow + bright text */
.nav-item.is-active {
    color: rgb(255 255 255);
    background-image: linear-gradient(to right, rgb(79 70 229), rgb(99 102 241));
    box-shadow: 0 4px 12px -2px rgb(79 70 229 / 0.35),
                inset 0 0 0 1px rgb(129 140 248 / 0.45);
}
.nav-item.is-active .nav-item-icon { color: white; }

/* Disabled state */
.nav-item.is-disabled {
    @apply text-slate-600 cursor-not-allowed;
}

.nav-item-icon {
    @apply flex-shrink-0 transition-transform duration-200;
}

/* Subtle vertical accent on the left edge of the active item */
.nav-indicator {
    position: absolute;
    left: 0;
    top: 50%;
    width: 3px;
    height: 60%;
    background: white;
    border-radius: 0 3px 3px 0;
    transform: translateY(-50%);
    opacity: 0.85;
}

.nav-soon-badge {
    @apply flex-shrink-0 text-[10px] font-medium text-slate-500 bg-slate-800/80
           px-1.5 py-0.5 rounded uppercase tracking-wider;
}
</style>
