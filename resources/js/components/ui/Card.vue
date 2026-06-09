<template>
    <!--
        Card primitive — Phase AA design system.

        Six variants tuned so identical content reads differently depending
        on intent. Default to `kpi` for dashboard tiles, `analytics` for
        chart containers, `action` for clickable launch tiles, `status` for
        status indicators with a coloured rail, `alert` for inline banners,
        and `summary` for hero blocks at the top of a section.

        Compose freely via the default <slot>. Optional <header> slot or
        title/subtitle/icon props give a consistent header rhythm so the
        whole product feels written by one designer.
    -->
    <component
        :is="tag"
        :class="['card', variantClass, tone ? `card-${variant}-${tone}` : '']"
        v-bind="$attrs"
    >
        <header v-if="$slots.header || title || icon" class="card-header">
            <slot name="header">
                <div class="card-head-row">
                    <div v-if="icon" :class="['card-icon', iconToneClass]">
                        <component :is="icon" class="w-4 h-4" />
                    </div>
                    <div class="card-head-text">
                        <div v-if="overline" class="t-overline">{{ overline }}</div>
                        <h3 v-if="title" class="h3-card">{{ title }}</h3>
                        <p v-if="subtitle" class="t-caption mt-0.5">{{ subtitle }}</p>
                    </div>
                    <div v-if="$slots.actions" class="card-head-actions">
                        <slot name="actions" />
                    </div>
                </div>
            </slot>
        </header>
        <slot />
        <footer v-if="$slots.footer" class="card-footer">
            <slot name="footer" />
        </footer>
    </component>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Visual variant — picks a base layout + decoration. */
    variant: {
        type: String,
        default: 'kpi',
        validator: v => ['kpi', 'analytics', 'action', 'status', 'alert', 'summary'].includes(v),
    },
    /** Tone for status / alert variants. */
    tone: {
        type: String,
        default: '',
        validator: v => ['', 'info', 'success', 'warning', 'danger'].includes(v),
    },
    /** Heroicon component reference for the header icon. */
    icon:     { type: [Object, Function], default: null },
    overline: { type: String, default: '' },
    title:    { type: String, default: '' },
    subtitle: { type: String, default: '' },
    /** Render as <a>, <button>, etc. for action cards. */
    tag:      { type: String, default: 'div' },
});

const variantClass = computed(() => `card-${props.variant}`);
const iconToneClass = computed(() => {
    const palette = {
        info:    'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300',
        success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300',
        warning: 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300',
        danger:  'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300',
    };
    return palette[props.tone] || 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300';
});
</script>

<style scoped>
@reference '../../../css/app.css';

.card-header { margin-bottom: 0.75rem; }
.card-head-row {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
}
.card-icon {
    @apply w-8 h-8 rounded-lg grid place-items-center flex-shrink-0;
}
.card-head-text { flex: 1 1 auto; min-width: 0; }
.card-head-actions { flex-shrink: 0; }
.card-footer {
    margin-top: 0.875rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-subtle);
}
</style>
