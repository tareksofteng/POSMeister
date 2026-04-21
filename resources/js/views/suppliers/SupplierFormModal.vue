<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('update:open', false)" />

                <div class="relative w-full sm:max-w-2xl bg-white sm:rounded-2xl shadow-2xl flex flex-col max-h-screen sm:max-h-[90vh]">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <BuildingOffice2Icon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 leading-none">
                                    {{ isEdit ? t('suppliers.editTitle') : t('suppliers.createTitle') }}
                                </h2>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ isEdit ? t('suppliers.editSubtitle') : t('suppliers.createSubtitle') }}
                                </p>
                            </div>
                        </div>
                        <button @click="$emit('update:open', false)" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submit" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                        <!-- Company name -->
                        <div>
                            <label class="form-label">{{ t('suppliers.companyName') }} <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                autofocus
                                class="form-input"
                                :class="{ 'border-red-300 focus:ring-red-500': errors.name }"
                                :placeholder="t('suppliers.namePlaceholder')"
                            />
                            <p v-if="errors.name" class="form-error">{{ errors.name }}</p>
                        </div>

                        <!-- Contact row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('suppliers.contactPerson') }}</label>
                                <input v-model="form.contact_person" type="text" class="form-input" :placeholder="t('suppliers.contactPersonPlaceholder')" />
                            </div>
                            <div>
                                <label class="form-label">{{ t('suppliers.vatNumber') }}</label>
                                <input v-model="form.vat_number" type="text" class="form-input" placeholder="DE123456789" />
                            </div>
                        </div>

                        <!-- Communication row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('common.email') }}</label>
                                <div class="relative">
                                    <EnvelopeIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        class="form-input pl-9"
                                        :class="{ 'border-red-300 focus:ring-red-500': errors.email }"
                                        :placeholder="t('suppliers.emailPlaceholder')"
                                    />
                                </div>
                                <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
                            </div>
                            <div>
                                <label class="form-label">{{ t('common.phone') }}</label>
                                <div class="relative">
                                    <PhoneIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    <input v-model="form.phone" type="text" class="form-input pl-9" placeholder="+49 30 12345678" />
                                </div>
                            </div>
                        </div>

                        <!-- Location row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('suppliers.city') }}</label>
                                <input v-model="form.city" type="text" class="form-input" placeholder="Berlin" />
                            </div>
                            <div>
                                <label class="form-label">{{ t('suppliers.country') }}</label>
                                <input v-model="form.country" type="text" class="form-input" placeholder="Deutschland" />
                            </div>
                        </div>

                        <!-- Address -->
                        <div>
                            <label class="form-label">{{ t('appSettings.address') }}</label>
                            <input v-model="form.address" type="text" class="form-input" :placeholder="t('suppliers.addressPlaceholder')" />
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="form-label">{{ t('suppliers.notes') }}</label>
                            <textarea v-model="form.notes" rows="2" class="form-input resize-none" :placeholder="t('suppliers.notesPlaceholder')" />
                        </div>

                        <!-- Status -->
                        <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700">{{ t('common.status') }}</p>
                                <p class="text-xs text-gray-400">{{ form.is_active ? t('suppliers.activeHint') : t('suppliers.inactiveHint') }}</p>
                            </div>
                            <button
                                type="button"
                                @click="form.is_active = !form.is_active"
                                :class="['relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2', form.is_active ? 'bg-indigo-600' : 'bg-gray-200']"
                            >
                                <span :class="['pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200', form.is_active ? 'translate-x-5' : 'translate-x-0']" />
                            </button>
                        </div>

                        <!-- Server error -->
                        <p v-if="serverError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ serverError }}</p>
                    </form>

                    <!-- Footer -->
                    <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/80 rounded-b-2xl flex-shrink-0">
                        <div v-if="isEdit" class="text-xs text-gray-400">
                            {{ t('suppliers.codeLabel') }}: <span class="font-mono font-medium text-gray-600">{{ props.supplier?.code }}</span>
                        </div>
                        <div v-else />
                        <div class="flex gap-3">
                            <button type="button" @click="$emit('update:open', false)" class="btn-secondary">
                                {{ t('common.cancel') }}
                            </button>
                            <button @click="submit" :disabled="saving" class="btn-primary">
                                <CheckIcon v-if="!saving" class="w-4 h-4" />
                                <span v-if="saving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                {{ saving ? t('common.saving') : (isEdit ? t('common.saveChanges') : t('suppliers.createSupplier')) }}
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { supplierService } from '@/services/supplierService';
import {
    XMarkIcon, BuildingOffice2Icon,
    EnvelopeIcon, PhoneIcon, CheckIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    open:     { type: Boolean, default: false },
    supplier: { type: Object, default: null },
});
const emit = defineEmits(['update:open', 'saved']);
const { t } = useI18n();

const isEdit = computed(() => !!props.supplier?.id);

const defaultForm = () => ({
    name: '', contact_person: '', email: '', phone: '',
    address: '', city: '', country: 'Deutschland',
    vat_number: '', notes: '', is_active: true,
});

const form        = ref(defaultForm());
const errors      = ref({});
const serverError = ref('');
const saving      = ref(false);

watch(() => props.open, (val) => {
    if (!val) return;
    errors.value      = {};
    serverError.value = '';
    form.value = props.supplier
        ? { ...defaultForm(), ...props.supplier }
        : defaultForm();
});

async function submit() {
    errors.value      = {};
    serverError.value = '';

    if (!form.value.name.trim()) {
        errors.value.name = t('common.nameRequired');
        return;
    }

    saving.value = true;
    try {
        if (isEdit.value) {
            await supplierService.update(props.supplier.id, form.value);
        } else {
            await supplierService.store(form.value);
        }
        emit('saved', isEdit.value);
        emit('update:open', false);
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            serverError.value = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.form-label   { @apply block text-xs font-medium text-gray-600 mb-1.5; }
.form-input   { @apply w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white transition-shadow; }
.form-error   { @apply mt-1 text-xs text-red-600; }
.btn-primary  { @apply flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed; }
.btn-secondary{ @apply flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors; }
.modal-enter-active, .modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .relative, .modal-leave-to .relative { transform: scale(0.96) translateY(8px); }
</style>
