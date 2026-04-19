<template>
    <Modal :model-value="modelValue" :title="title" size="sm" persistent @update:model-value="cancel">
        <p class="text-sm text-gray-600">{{ message }}</p>

        <template #footer>
            <button
                @click="cancel"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
                {{ cancelLabel }}
            </button>
            <button
                @click="confirm"
                :disabled="loading"
                :class="[
                    'flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors disabled:opacity-60',
                    danger
                        ? 'bg-red-600 text-white hover:bg-red-700'
                        : 'bg-indigo-600 text-white hover:bg-indigo-700',
                ]"
            >
                <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ confirmLabel }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import Modal from './Modal.vue';

defineProps({
    modelValue:   { type: Boolean, required: true },
    title:        { type: String,  default: 'Confirm Action' },
    message:      { type: String,  required: true },
    confirmLabel: { type: String,  default: 'Confirm' },
    cancelLabel:  { type: String,  default: 'Cancel' },
    danger:       { type: Boolean, default: false },
    loading:      { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

function confirm() { emit('confirm'); }
function cancel()  { emit('update:modelValue', false); emit('cancel'); }
</script>
