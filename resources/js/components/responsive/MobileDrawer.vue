<template>
    <!-- Generic bottom-up or right-side drawer for mobile actions, filters, cart, etc. -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="modelValue" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm" @click="close" aria-hidden="true" />
        </Transition>

        <Transition
            :enter-active-class="position === 'bottom' ? 'transition-transform duration-300 ease-out' : 'transition-transform duration-300 ease-out'"
            :enter-from-class="position === 'bottom' ? 'translate-y-full' : 'translate-x-full'"
            enter-to-class="translate-y-0 translate-x-0"
            :leave-active-class="position === 'bottom' ? 'transition-transform duration-200 ease-in' : 'transition-transform duration-200 ease-in'"
            leave-from-class="translate-y-0 translate-x-0"
            :leave-to-class="position === 'bottom' ? 'translate-y-full' : 'translate-x-full'"
        >
            <div
                v-if="modelValue"
                :class="[
                    'fixed z-50 bg-white dark:bg-slate-900 shadow-2xl flex flex-col',
                    position === 'bottom'
                        ? 'bottom-0 inset-x-0 rounded-t-2xl max-h-[92vh] pb-safe'
                        : 'top-0 right-0 h-full w-[90vw] max-w-md',
                ]"
                role="dialog"
                aria-modal="true"
            >
                <!-- Header -->
                <header v-if="title || $slots.header" class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-slate-800">
                    <slot name="header">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 truncate">{{ title }}</h2>
                    </slot>
                    <button @click="close" class="touch-target -mr-2 flex items-center justify-center text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 rounded-lg">
                        <span aria-hidden="true">✕</span>
                    </button>
                </header>

                <!-- Visual grab handle on bottom drawer -->
                <div v-if="position === 'bottom' && !title && !$slots.header" class="flex justify-center pt-3 pb-1">
                    <span class="block w-10 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700" />
                </div>

                <div class="flex-1 overflow-y-auto overscroll-contain">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="border-t border-slate-200 dark:border-slate-800 px-4 py-3">
                    <slot name="footer" />
                </footer>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { watch, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title:      { type: String,  default: '' },
    /** "bottom" (default, phone-native) | "right" (sheet) */
    position:   { type: String,  default: 'bottom' },
});

const emit = defineEmits(['update:modelValue']);

function close() { emit('update:modelValue', false); }

watch(() => props.modelValue, (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
});
onUnmounted(() => {
    if (typeof document !== 'undefined') document.body.style.overflow = '';
});
</script>
