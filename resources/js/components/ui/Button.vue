<template>
    <!--
        Button primitive — Phase AA. One language across every module so
        the user never wonders "is this primary or just an icon?". Five
        variants, three sizes, full loading + icon-only support. Renders
        as <button> by default, as <RouterLink> when `to` is set, as <a>
        when `href` is set — same surface, right semantics.
    -->
    <component
        :is="tagComponent"
        :to="to || undefined"
        :href="href || undefined"
        :type="tagComponent === 'button' ? type : undefined"
        :disabled="disabled || loading"
        :aria-disabled="disabled || loading"
        :aria-busy="loading"
        :class="['btn', variantClass, sizeClass, { 'btn-icon': iconOnly, 'btn-block': block, 'btn-loading': loading }]"
        v-bind="$attrs"
    >
        <span v-if="loading" class="btn-spinner" aria-hidden="true" />
        <component v-else-if="leadingIcon" :is="leadingIcon" :class="iconSizeClass" aria-hidden="true" />
        <span v-if="!iconOnly" class="btn-label"><slot /></span>
        <component v-if="trailingIcon && !loading" :is="trailingIcon" :class="iconSizeClass" aria-hidden="true" />
    </component>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: v => ['primary', 'secondary', 'ghost', 'danger', 'success'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: v => ['sm', 'md', 'lg'].includes(v),
    },
    type:         { type: String,  default: 'button' },
    to:           { type: [String, Object], default: null },
    href:         { type: String,  default: '' },
    disabled:     { type: Boolean, default: false },
    loading:      { type: Boolean, default: false },
    block:        { type: Boolean, default: false },
    iconOnly:     { type: Boolean, default: false },
    leadingIcon:  { type: [Object, Function], default: null },
    trailingIcon: { type: [Object, Function], default: null },
});

const tagComponent = computed(() => {
    if (props.to)   return 'router-link';
    if (props.href) return 'a';
    return 'button';
});
const variantClass = computed(() => `btn-${props.variant}`);
const sizeClass    = computed(() => (props.size === 'md' ? '' : `btn-${props.size}`));
const iconSizeClass = computed(() => (props.size === 'sm' ? 'w-3.5 h-3.5' : props.size === 'lg' ? 'w-5 h-5' : 'w-4 h-4'));
</script>

<style scoped>
@reference '../../../css/app.css';

.btn-label { display: inline-block; }
.btn-block { width: 100%; }
.btn-loading { pointer-events: none; }
.btn-spinner {
    width: 0.875rem; height: 0.875rem;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 999px;
    animation: btn-spin 0.7s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
    .btn-spinner { animation: none; }
}
</style>
