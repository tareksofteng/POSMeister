<template>
    <!--
        EmptyState — Phase AA. "No data" was the most boring screen in
        the product. This swaps the flat disc for a layered illustration
        (soft gradient halo + tinted ring + iconogram) so an empty list
        becomes a moment, not a dead end.

        Backwards compatible: every prop on the old component (icon, title,
        description, tone) still works. New optional props:

          - hint:     small footnote below description ("e.g. import a CSV")
          - size:     'sm' | 'md' | 'lg' — picks padding + scale
          - elevated: when true, wraps the whole block in a .card surface

        Two action slots so primary + secondary CTAs stay aligned:

          <template #action>...primary...</template>
          <template #secondary-action>...</template>
    -->
    <div :class="['empty-state', `is-${size}`, { 'empty-elevated': elevated }]" role="status">
        <div class="empty-figure" aria-hidden="true">
            <span :class="['empty-halo', toneHaloClass]" />
            <span :class="['empty-ring', toneRingClass]" />
            <div :class="['empty-disc', toneDiscClass]">
                <component :is="icon" :class="['empty-icon-svg', toneIconClass]" />
            </div>
        </div>

        <h3 class="empty-title">{{ title }}</h3>
        <p v-if="description" class="empty-desc">{{ description }}</p>
        <p v-if="hint" class="empty-hint">{{ hint }}</p>

        <div v-if="$slots.action || $slots['secondary-action']" class="empty-actions">
            <slot name="action" />
            <slot name="secondary-action" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { InboxIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    icon:        { type: [Object, Function], default: () => InboxIcon },
    title:       { type: String, required: true },
    description: { type: String, default: '' },
    hint:        { type: String, default: '' },
    tone:        { type: String, default: 'slate' },
    size:        { type: String, default: 'md', validator: v => ['sm', 'md', 'lg'].includes(v) },
    elevated:    { type: Boolean, default: false },
});

// Three concentric tone layers — halo (softest), ring (mid), disc (boldest)
// — give the icon a real "stage" instead of floating on flat background.
const palette = {
    slate:   { halo: 'halo-slate',   ring: 'ring-slate',   disc: 'disc-slate',   icon: 'text-slate-500' },
    indigo:  { halo: 'halo-indigo',  ring: 'ring-indigo',  disc: 'disc-indigo',  icon: 'text-indigo-600' },
    emerald: { halo: 'halo-emerald', ring: 'ring-emerald', disc: 'disc-emerald', icon: 'text-emerald-600' },
    amber:   { halo: 'halo-amber',   ring: 'ring-amber',   disc: 'disc-amber',   icon: 'text-amber-600' },
    rose:    { halo: 'halo-rose',    ring: 'ring-rose',    disc: 'disc-rose',    icon: 'text-rose-600' },
    sky:     { halo: 'halo-sky',     ring: 'ring-sky',     disc: 'disc-sky',     icon: 'text-sky-600' },
};
const tone = computed(() => palette[props.tone] || palette.slate);
const toneHaloClass = computed(() => tone.value.halo);
const toneRingClass = computed(() => tone.value.ring);
const toneDiscClass = computed(() => tone.value.disc);
const toneIconClass = computed(() => tone.value.icon);
</script>

<style scoped>
@reference '../../../css/app.css';

.empty-state {
    @apply flex flex-col items-center justify-center text-center px-6;
    animation: fade-up var(--motion-base) var(--motion-out) both;
}
.empty-state.is-sm { @apply py-8 sm:py-10; }
.empty-state.is-md { @apply py-12 sm:py-16; }
.empty-state.is-lg { @apply py-16 sm:py-24; }
.empty-elevated {
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
    border-radius: 1rem;
    box-shadow: var(--elev-1);
}

/* Stacked iconogram — halo > ring > disc > icon */
.empty-figure {
    position: relative;
    display: grid; place-items: center;
    margin-bottom: 1.25rem;
}
.is-sm .empty-figure { margin-bottom: 0.75rem; }
.empty-halo, .empty-ring, .empty-disc {
    border-radius: 999px;
    position: absolute;
}
.empty-halo {
    width: 96px; height: 96px;
    opacity: 0.45;
    filter: blur(14px);
}
.is-sm .empty-halo { width: 72px; height: 72px; }
.is-lg .empty-halo { width: 128px; height: 128px; }
.empty-ring {
    width: 80px; height: 80px;
    border: 1px dashed currentColor;
    opacity: 0.35;
}
.is-sm .empty-ring { width: 64px; height: 64px; }
.is-lg .empty-ring { width: 112px; height: 112px; }
.empty-disc {
    position: relative;
    width: 56px; height: 56px;
    border-radius: 999px;
    display: grid; place-items: center;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 10px 28px -16px rgba(15, 23, 42, 0.12);
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
}
.is-sm .empty-disc { width: 44px; height: 44px; }
.is-lg .empty-disc { width: 72px; height: 72px; }
.empty-icon-svg {
    width: 24px; height: 24px;
    opacity: 0.95;
}
.is-sm .empty-icon-svg { width: 20px; height: 20px; }
.is-lg .empty-icon-svg { width: 32px; height: 32px; }

/* Tone palettes — halo (soft glow), ring (dashed accent), disc (subtle tint) */
.halo-slate   { background: radial-gradient(circle, rgba(100,116,139,0.30) 0%, transparent 70%); }
.ring-slate   { color: rgb(148 163 184); }
.disc-slate   { background: rgb(248 250 252); border-color: rgb(226 232 240); }

.halo-indigo  { background: radial-gradient(circle, rgba(99,102,241,0.35) 0%, transparent 70%); }
.ring-indigo  { color: rgb(129 140 248); }
.disc-indigo  { background: rgb(238 242 255); border-color: rgb(224 231 255); }

.halo-emerald { background: radial-gradient(circle, rgba(16,185,129,0.32) 0%, transparent 70%); }
.ring-emerald { color: rgb(52 211 153); }
.disc-emerald { background: rgb(236 253 245); border-color: rgb(209 250 229); }

.halo-amber   { background: radial-gradient(circle, rgba(245,158,11,0.34) 0%, transparent 70%); }
.ring-amber   { color: rgb(251 191 36); }
.disc-amber   { background: rgb(255 251 235); border-color: rgb(253 230 138); }

.halo-rose    { background: radial-gradient(circle, rgba(244,63,94,0.32) 0%, transparent 70%); }
.ring-rose    { color: rgb(251 113 133); }
.disc-rose    { background: rgb(255 241 242); border-color: rgb(254 205 211); }

.halo-sky     { background: radial-gradient(circle, rgba(14,165,233,0.34) 0%, transparent 70%); }
.ring-sky     { color: rgb(56 189 248); }
.disc-sky     { background: rgb(240 249 255); border-color: rgb(186 230 253); }

.empty-title {
    @apply text-base sm:text-lg font-semibold;
    color: var(--text-primary);
    letter-spacing: -0.01em;
}
.empty-desc {
    @apply mt-1.5 text-sm max-w-md;
    color: var(--text-secondary);
}
.empty-hint {
    @apply mt-1 text-xs;
    color: var(--text-tertiary);
}
.empty-actions {
    @apply mt-5 sm:mt-6 flex flex-wrap items-center justify-center gap-2;
}

/* Dark mode tone overrides — tint the disc + dim the dashed ring so the
   illustration reads as part of the dark surface, not a glaring patch. */
html.dark .disc-slate   { background: rgb(30 41 59 / 0.6);  border-color: rgb(51 65 85); }
html.dark .disc-indigo  { background: rgb(67 56 202 / 0.18); border-color: rgb(99 102 241 / 0.3); }
html.dark .disc-emerald { background: rgb(5 150 105 / 0.18); border-color: rgb(16 185 129 / 0.3); }
html.dark .disc-amber   { background: rgb(180 83 9 / 0.18);  border-color: rgb(245 158 11 / 0.3); }
html.dark .disc-rose    { background: rgb(159 18 57 / 0.18); border-color: rgb(244 63 94 / 0.3); }
html.dark .disc-sky     { background: rgb(2 132 199 / 0.18); border-color: rgb(14 165 233 / 0.3); }
html.dark .empty-halo   { opacity: 0.55; }
</style>
