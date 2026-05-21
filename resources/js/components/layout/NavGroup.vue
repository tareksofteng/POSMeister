<template>
    <!-- Collapsed sidebar: icon only, indigo glow when a child is active -->
    <button
        v-if="collapsed"
        :title="label"
        :class="[
            'nav-collapsed-btn',
            isChildActive ? 'is-active' : '',
        ]"
    >
        <slot name="icon" />
    </button>

    <!-- Expanded sidebar: accordion with smooth grid-rows animation -->
    <template v-else>
        <button
            @click="open = !open"
            :class="['nav-group-trigger', isChildActive ? 'is-active' : '']"
        >
            <span class="nav-group-icon">
                <slot name="icon" />
            </span>
            <span class="flex-1 text-left truncate">{{ label }}</span>
            <ChevronDownIcon
                :class="['w-3.5 h-3.5 flex-shrink-0 transition-transform duration-300', open ? 'rotate-180' : '']"
            />
        </button>

        <!-- Smooth grid-rows trick: 0fr → 1fr animates from collapsed to auto height -->
        <div :class="['nav-group-collapsible', open ? 'is-open' : '']">
            <div class="nav-group-clip">
                <div class="nav-group-children">
                    <slot />
                </div>
            </div>
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

watch(isChildActive, (active) => { if (active) open.value = true; });
</script>

<style scoped>
@reference '../../../css/app.css';

.nav-collapsed-btn {
    @apply w-full flex items-center justify-center px-3 py-2 rounded-lg
           text-slate-400 transition-all duration-200
           hover:bg-slate-800/80 hover:text-slate-100;
}
.nav-collapsed-btn.is-active {
    @apply bg-indigo-500/15 text-indigo-300;
    box-shadow: inset 0 0 0 1px rgb(99 102 241 / 0.25);
}

.nav-group-trigger {
    @apply w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
           text-slate-400 transition-all duration-200
           hover:bg-slate-800/70 hover:text-slate-100;
}
.nav-group-trigger.is-active {
    @apply text-indigo-200 bg-indigo-500/10;
}

.nav-group-icon {
    @apply flex-shrink-0 transition-transform duration-200;
}
.nav-group-trigger:hover .nav-group-icon { transform: translateX(1px); }

/* Smooth collapse via grid-template-rows: animates 0fr → 1fr from collapsed
   to auto height. Pure CSS, no max-height hack, no JS measuring. */
.nav-group-collapsible {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 280ms cubic-bezier(0.22, 1, 0.36, 1);
}
.nav-group-collapsible.is-open { grid-template-rows: 1fr; }

.nav-group-clip { overflow: hidden; }

.nav-group-children {
    @apply mt-0.5 ml-2 pl-3 space-y-0.5;
    border-left: 1px solid rgb(51 65 85 / 0.6);
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity 220ms ease, transform 220ms ease;
    transition-delay: 0ms;
}
.nav-group-collapsible.is-open .nav-group-children {
    opacity: 1;
    transform: translateY(0);
    transition-delay: 80ms;
}
</style>
