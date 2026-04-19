<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="onBackdropClick"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" />

                <!-- Panel -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                >
                    <div
                        v-if="modelValue"
                        :class="['relative w-full bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh]', sizeClass]"
                        role="dialog"
                        :aria-label="title"
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                            <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
                            <button
                                @click="$emit('update:modelValue', false)"
                                class="p-1.5 -mr-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Body (scrollable) -->
                        <div class="flex-1 overflow-y-auto px-6 py-5">
                            <slot />
                        </div>

                        <!-- Footer -->
                        <div
                            v-if="$slots.footer"
                            class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50/50 rounded-b-2xl"
                        >
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title:      { type: String,  required: true },
    size:       { type: String,  default: 'md' },   // sm | md | lg | xl
    persistent: { type: Boolean, default: false },  // prevent close on backdrop click
});

const emit = defineEmits(['update:modelValue']);

const sizeClass = computed(() => ({
    'sm': 'max-w-sm',
    'md': 'max-w-lg',
    'lg': 'max-w-2xl',
    'xl': 'max-w-4xl',
})[props.size] ?? 'max-w-lg');

function onBackdropClick() {
    if (!props.persistent) {
        emit('update:modelValue', false);
    }
}

// ESC key to close
function onKeydown(e) {
    if (e.key === 'Escape' && props.modelValue && !props.persistent) {
        emit('update:modelValue', false);
    }
}

onMounted  (() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>
