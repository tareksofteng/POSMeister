<template>
    <Teleport to="body">
        <Transition name="modal">
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')" />

                <div class="relative w-full sm:max-w-lg bg-white sm:rounded-2xl shadow-2xl flex flex-col max-h-screen sm:max-h-[85vh]">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <UsersIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <h2 class="text-base font-semibold text-gray-900">
                                {{ isEdit ? t('customers.editTitle') : t('customers.addTitle') }}
                            </h2>
                        </div>
                        <button @click="$emit('close')" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                        <!-- Name -->
                        <div>
                            <label class="form-label">{{ t('customers.name') }} <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" autofocus class="form-input"
                                :class="{ 'border-red-300 focus:ring-red-500': errors.name }" />
                            <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                        </div>

                        <!-- Phone + Email -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('customers.phone') }}</label>
                                <input v-model="form.phone" type="tel" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">{{ t('common.email') }}</label>
                                <input v-model="form.email" type="email" class="form-input"
                                    :class="{ 'border-red-300 focus:ring-red-500': errors.email }" />
                                <p v-if="errors.email" class="form-error">{{ errors.email[0] }}</p>
                            </div>
                        </div>

                        <!-- Address -->
                        <div>
                            <label class="form-label">{{ t('common.address') }}</label>
                            <textarea v-model="form.address" rows="2" class="form-input resize-none" />
                        </div>

                        <!-- Type + Credit limit -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('customers.type') }}</label>
                                <select v-model="form.customer_type" class="form-input">
                                    <option value="retail">{{ t('customers.typeRetail') }}</option>
                                    <option value="wholesale">{{ t('customers.typeWholesale') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">{{ t('customers.creditLimit') }}</label>
                                <input v-model.number="form.credit_limit" type="number" min="0" step="0.01" class="form-input" />
                            </div>
                        </div>

                        <!-- Status (edit only) -->
                        <div v-if="isEdit" class="flex items-center gap-3">
                            <input id="is_active" v-model="form.is_active" type="checkbox"
                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                            <label for="is_active" class="text-sm text-gray-700">{{ t('common.active') }}</label>
                        </div>

                        <!-- Error banner -->
                        <div v-if="apiError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                            {{ apiError }}
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
                        <button type="button" @click="$emit('close')" class="btn-ghost">
                            {{ t('common.cancel') }}
                        </button>
                        <button @click="submit" :disabled="saving" class="btn-primary">
                            <span v-if="saving" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                            {{ t('common.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAlert } from '@/composables/useAlert';
import { customerService } from '@/services/customerService';
import { UsersIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props  = defineProps({ customer: { type: Object, default: null } });
const emit   = defineEmits(['close', 'saved']);
const { t }  = useI18n();
const { toast } = useAlert();

const isEdit = computed(() => !!props.customer);

const defaultForm = () => ({
    name: '', phone: '', email: '', address: '',
    customer_type: 'retail', credit_limit: 0, is_active: true,
});

const form     = ref(defaultForm());
const errors   = ref({});
const apiError = ref('');
const saving   = ref(false);

watch(() => props.customer, (c) => {
    if (c) {
        form.value = {
            name: c.name ?? '', phone: c.phone ?? '', email: c.email ?? '',
            address: c.address ?? '', customer_type: c.customer_type ?? 'retail',
            credit_limit: parseFloat(c.credit_limit) || 0,
            is_active: c.is_active ?? true,
        };
    } else {
        form.value = defaultForm();
    }
    errors.value   = {};
    apiError.value = '';
}, { immediate: true });

async function submit() {
    errors.value   = {};
    apiError.value = '';
    saving.value   = true;
    try {
        if (isEdit.value) {
            await customerService.update(props.customer.id, form.value);
        } else {
            await customerService.store(form.value);
        }
        toast('success', t('common.savedSuccess'));
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors ?? {};
        } else {
            apiError.value = err.response?.data?.message ?? t('common.unexpectedError');
        }
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.form-label { @apply block text-sm font-medium text-gray-700 mb-1; }
.form-input  { @apply w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors; }
.form-error  { @apply mt-1 text-xs text-red-600; }
.btn-primary { @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors; }
.btn-ghost   { @apply px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors; }
.modal-enter-active, .modal-leave-active { @apply transition-all duration-200; }
.modal-enter-from, .modal-leave-to { @apply opacity-0; }
</style>
