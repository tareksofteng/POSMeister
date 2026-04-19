<template>
    <Modal
        :model-value="open"
        :title="isEdit ? t('branches.editTitle') : t('branches.createTitle')"
        size="md"
        @update:model-value="$emit('update:open', $event)"
    >
        <form id="branch-form" @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <!-- Code + Name -->
            <div class="grid grid-cols-2 gap-4">
                <FormField :label="t('branches.code')" :error="errors.code" required>
                    <input
                        v-model="form.code"
                        type="text"
                        :placeholder="t('branches.codePlaceholder')"
                        maxlength="20"
                        :class="inputClass(errors.code)"
                        :disabled="isEdit"
                    />
                    <p v-if="isEdit" class="mt-1 text-xs text-gray-400">{{ t('branches.codeHint') }}</p>
                </FormField>

                <FormField :label="t('branches.name')" :error="errors.name" required>
                    <input
                        v-model="form.name"
                        type="text"
                        :placeholder="t('branches.namePlaceholder')"
                        :class="inputClass(errors.name)"
                    />
                </FormField>
            </div>

            <!-- Phone + Email -->
            <div class="grid grid-cols-2 gap-4">
                <FormField :label="t('common.phone')" :error="errors.phone">
                    <input
                        v-model="form.phone"
                        type="tel"
                        :placeholder="t('branches.phonePlaceholder')"
                        :class="inputClass(errors.phone)"
                    />
                </FormField>

                <FormField :label="t('common.email')" :error="errors.email">
                    <input
                        v-model="form.email"
                        type="email"
                        :placeholder="t('branches.emailPlaceholder')"
                        :class="inputClass(errors.email)"
                    />
                </FormField>
            </div>

            <!-- Address -->
            <FormField :label="t('branches.address')" :error="errors.address">
                <textarea
                    v-model="form.address"
                    rows="2"
                    :placeholder="t('branches.addressPlaceholder')"
                    :class="inputClass(errors.address)"
                />
            </FormField>

            <!-- Status -->
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="form.is_active = !form.is_active"
                    :class="[
                        'relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                        form.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                    ]"
                >
                    <span :class="['inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform', form.is_active ? 'translate-x-4' : 'translate-x-1']" />
                </button>
                <label class="text-sm font-medium text-gray-700">
                    {{ form.is_active ? t('common.active') : t('common.inactive') }}
                </label>
            </div>

            <!-- Global error -->
            <p v-if="globalError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">
                {{ globalError }}
            </p>
        </form>

        <template #footer>
            <button
                type="button"
                @click="$emit('update:open', false)"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="submit"
                form="branch-form"
                :disabled="submitting"
                class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors"
            >
                <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ isEdit ? t('branches.saveChanges') : t('branches.createBranch') }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal     from '@/components/ui/Modal.vue';
import FormField from '@/components/ui/FormField.vue';
import { branchService } from '@/services/branchService';

const { t } = useI18n();

const props = defineProps({
    open:   { type: Boolean, required: true },
    branch: { type: Object,  default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit    = computed(() => !!props.branch?.id);
const submitting = ref(false);
const globalError = ref('');

const form = reactive({
    code:      '',
    name:      '',
    phone:     '',
    email:     '',
    address:   '',
    is_active: true,
});

const errors = reactive({
    code: '', name: '', phone: '', email: '', address: '',
});

// Populate form when editing
watch(() => props.branch, (val) => {
    clearErrors();
    globalError.value = '';
    if (val) {
        form.code      = val.code      ?? '';
        form.name      = val.name      ?? '';
        form.phone     = val.phone     ?? '';
        form.email     = val.email     ?? '';
        form.address   = val.address   ?? '';
        form.is_active = val.is_active ?? true;
    } else {
        form.code = form.name = form.phone = form.email = form.address = '';
        form.is_active = true;
    }
}, { immediate: true });

async function handleSubmit() {
    clearErrors();
    globalError.value = '';

    if (!clientValidate()) return;

    submitting.value = true;

    try {
        if (isEdit.value) {
            await branchService.update(props.branch.id, form);
        } else {
            await branchService.store(form);
        }
        emit('saved');
    } catch (err) {
        const { status, data } = err.response ?? {};

        if (status === 422 && data?.errors) {
            Object.entries(data.errors).forEach(([field, msgs]) => {
                if (field in errors) errors[field] = msgs[0];
            });
        } else {
            globalError.value = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        submitting.value = false;
    }
}

function clientValidate() {
    let valid = true;
    if (!form.code.trim()) { errors.code = t('branches.codeRequired'); valid = false; }
    if (!form.name.trim()) { errors.name = t('branches.nameRequired'); valid = false; }
    return valid;
}

function clearErrors() {
    Object.keys(errors).forEach(k => (errors[k] = ''));
}

function inputClass(error) {
    return [
        'block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400',
        'focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors',
        error ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white',
    ];
}
</script>
