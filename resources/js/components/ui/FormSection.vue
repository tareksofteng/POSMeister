<template>
    <!--
        FormSection — Phase AA. Groups related fields under a labelled
        divider so long forms (Supplier, Customer, Settings, HRM) stop
        feeling like a wall of inputs. First section in a form renders
        WITHOUT the top border (handled by the .form-section CSS rule
        `:first-child` selector) so the header still leads cleanly.

        Layout:
          +-------------------------------------------+
          |  ─── Title ────────  Optional description |
          |  ┌─────────────┐  ┌─────────────────────┐ |
          |  │   field     │  │   field             │ |
          |  └─────────────┘  └─────────────────────┘ |
          +-------------------------------------------+

        Usage:
          <FormSection title="Contact" description="How we reach the
                       supplier when an order needs confirming." cols="2">
              <FormField label="Email" required>
                  <input v-model="form.email" class="form-input" />
              </FormField>
              ...
          </FormSection>
    -->
    <section class="form-section">
        <header v-if="title || description" class="form-section-head">
            <div>
                <h3 class="form-section-title">{{ title }}</h3>
                <p v-if="description" class="form-section-desc">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="form-section-actions">
                <slot name="actions" />
            </div>
        </header>
        <div :class="['form-section-body', `cols-${cols}`]">
            <slot />
        </div>
    </section>
</template>

<script setup>
defineProps({
    title:       { type: String,  default: '' },
    description: { type: String,  default: '' },
    /** Field grid: 1 | 2 | 3 columns at sm+ breakpoint. */
    cols:        { type: [String, Number], default: 1 },
});
</script>

<style scoped>
@reference '../../../css/app.css';

.form-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.form-section-actions { flex-shrink: 0; }
</style>
