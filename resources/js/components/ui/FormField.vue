<template>
    <!--
        FormField — Phase AA upgrade. Premium label + helper text + error
        state, all driven by the design system. Backwards compatible:
        every existing prop (label, error, hint, required) still works.

        New optional props:
          - id:          generates a `for=` on the label + injects it via
                         the #default slot's `id` scope arg so callers
                         don't have to wire two ends manually
          - description: small caption between label and field
          - inline:      true → label and field on one row (settings panel
                         style)

        New slots:
          - hint:   custom helper content (e.g. "8+ characters" with icon)
          - error:  custom error content (e.g. shake animation, link to
                    server log)
    -->
    <div :class="['form-field', { 'form-field-inline': inline }]">
        <label
            v-if="label"
            :for="id || undefined"
            :class="['form-label', { 'form-label-required': required }]"
        >
            {{ label }}
        </label>
        <p v-if="description" class="form-help" style="margin-top: 0; margin-bottom: 0.5rem;">
            {{ description }}
        </p>

        <div class="form-field-control">
            <slot :id="id" />
        </div>

        <template v-if="error">
            <slot name="error">
                <p class="form-error" role="alert">
                    <ExclamationCircleIcon v-if="!hideErrorIcon" class="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                    <span>{{ error }}</span>
                </p>
            </slot>
        </template>
        <template v-else-if="hint || $slots.hint">
            <slot name="hint">
                <p class="form-help">{{ hint }}</p>
            </slot>
        </template>
    </div>
</template>

<script setup>
import { ExclamationCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    label:       { type: String,  default: '' },
    description: { type: String,  default: '' },
    error:       { type: String,  default: '' },
    hint:        { type: String,  default: '' },
    required:    { type: Boolean, default: false },
    /** Optional id linked to the rendered label's for= attribute. */
    id:          { type: String,  default: '' },
    /** Render the field inline (label on the left, control on the right). */
    inline:      { type: Boolean, default: false },
    hideErrorIcon: { type: Boolean, default: false },
});
</script>

<style scoped>
@reference '../../../css/app.css';

.form-field {
    display: flex;
    flex-direction: column;
}
.form-field-control { width: 100%; }

.form-field-inline {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
    align-items: center;
}
@media (min-width: 640px) {
    .form-field-inline {
        grid-template-columns: minmax(140px, 1fr) 2fr;
        gap: 1.25rem;
    }
    .form-field-inline > .form-label { margin-bottom: 0; }
}
</style>
