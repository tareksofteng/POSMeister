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

/* Phase AA — refined navigation. The previous bright indigo gradient
   read as "admin template"; this version uses a tinted surface with a
   crisp 3px left rail (Linear / Vercel pattern) so active state is
   clearly readable but the sidebar still feels enterprise-grade. */
.nav-item {
    @apply relative w-full flex items-center gap-3 px-3 py-2 rounded-lg
           text-sm font-medium text-slate-400;
    transition:
        background-color var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out),
        transform        var(--motion-fast) var(--motion-out);
}
.nav-item:hover:not(.is-disabled):not(.is-active) {
    @apply bg-slate-800/60 text-slate-100;
}
.nav-item:hover:not(.is-disabled) .nav-item-icon { transform: translateX(1px); }

.nav-item.is-collapsed { @apply justify-center; }

/* Active route — tinted surface, refined indigo, no flashy gradient */
.nav-item.is-active {
    color: rgb(224 231 255);
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.16) 0%, rgba(79, 70, 229, 0.14) 100%);
    box-shadow:
        inset 0 0 0 1px rgba(129, 140, 248, 0.22),
        0 1px 0 rgba(255, 255, 255, 0.04) inset;
}
.nav-item.is-active .nav-item-icon { color: rgb(165 180 252); }

/* Disabled / coming-soon */
.nav-item.is-disabled {
    @apply text-slate-600 cursor-not-allowed;
}

.nav-item-icon {
    @apply flex-shrink-0;
    transition: transform var(--motion-fast) var(--motion-out);
}

/* Crisp 3px rail on the left edge of the active item — taller, glow-tipped */
.nav-indicator {
    position: absolute;
    left: -1px;
    top: 50%;
    width: 3px;
    height: 70%;
    background: linear-gradient(180deg, rgb(165 180 252) 0%, rgb(99 102 241) 100%);
    border-radius: 0 3px 3px 0;
    transform: translateY(-50%);
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.22), 0 0 10px -2px rgba(99, 102, 241, 0.55);
}

.nav-soon-badge {
    @apply flex-shrink-0 text-[10px] font-medium text-slate-500 bg-slate-800/80
           px-1.5 py-0.5 rounded uppercase tracking-wider;
}

/* Touch-grade height on phones — matches Apple HIG / WCAG 2.5.5 */
@media (max-width: 768px) {
    .nav-item { min-height: 44px; }
}
</style>
