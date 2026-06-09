<template>
    <!--
        SupplierFormModal — refactored on Phase AA Round 4 design system.
        Modal chrome via .form-modal-* utilities (single language with
        every other form modal), fields grouped via <FormSection>, all
        inputs use the unified .form-input rhythm, save/cancel use the
        <Button> primitive. Visual rewrite only — every event, prop, and
        emit is unchanged.
    -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="form-modal-overlay">
                <div class="absolute inset-0" @click="$emit('update:open', false)" />

                <div class="form-modal-shell">

                    <header class="form-modal-header">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                <BuildingOffice2Icon class="w-5 h-5 text-indigo-600 dark:text-indigo-300" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 leading-tight">
                                    {{ isEdit ? t('suppliers.editTitle') : t('suppliers.createTitle') }}
                                </h2>
                                <p class="t-caption mt-0.5">
                                    {{ isEdit ? t('suppliers.editSubtitle') : t('suppliers.createSubtitle') }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="$emit('update:open', false)"
                            class="row-action"
                            :aria-label="t('common.close', 'Close')"
                        >
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </header>

                    <form @submit.prevent="submit" class="form-modal-body">

                        <FormSection
                            :title="t('suppliers.section.basics', 'Company')"
                            :description="t('suppliers.section.basicsDesc', 'Legal name and tax identification.')"
                        >
                            <FormField id="sup-name" :label="t('suppliers.companyName')" :error="errors.name" required>
                                <input
                                    id="sup-name"
                                    v-model="form.name"
                                    type="text"
                                    autofocus
                                    :class="['form-input', { 'is-invalid': errors.name }]"
                                    :placeholder="t('suppliers.namePlaceholder')"
                                />
                            </FormField>
                        </FormSection>

                        <FormSection
                            :title="t('suppliers.section.contact', 'Contact')"
                            :description="t('suppliers.section.contactDesc', 'Who we reach when an order needs confirming.')"
                            cols="2"
                        >
                            <FormField :label="t('suppliers.contactPerson')">
                                <input v-model="form.contact_person" type="text" class="form-input" :placeholder="t('suppliers.contactPersonPlaceholder')" />
                            </FormField>
                            <FormField :label="t('suppliers.vatNumber')">
                                <input v-model="form.vat_number" type="text" class="form-input" placeholder="DE123456789" />
                            </FormField>
                            <FormField :label="t('common.email')" :error="errors.email">
                                <div class="form-input-group">
                                    <EnvelopeIcon class="form-input-icon-left" />
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        :class="['form-input form-input-with-icon-left', { 'is-invalid': errors.email }]"
                                        :placeholder="t('suppliers.emailPlaceholder')"
                                    />
                                </div>
                            </FormField>
                            <FormField :label="t('common.phone')">
                                <div class="form-input-group">
                                    <PhoneIcon class="form-input-icon-left" />
                                    <input v-model="form.phone" type="text" class="form-input form-input-with-icon-left" placeholder="+49 30 12345678" />
                                </div>
                            </FormField>
                        </FormSection>

                        <FormSection
                            :title="t('suppliers.section.address', 'Address')"
                            cols="2"
                        >
                            <FormField :label="t('suppliers.city')">
                                <input v-model="form.city" type="text" class="form-input" placeholder="Berlin" />
                            </FormField>
                            <FormField :label="t('suppliers.country')">
                                <input v-model="form.country" type="text" class="form-input" placeholder="Deutschland" />
                            </FormField>
                            <div class="sm:col-span-2">
                                <FormField :label="t('appSettings.address')">
                                    <input v-model="form.address" type="text" class="form-input" :placeholder="t('suppliers.addressPlaceholder')" />
                                </FormField>
                            </div>
                        </FormSection>

                        <FormSection :title="t('suppliers.section.notes', 'Notes')">
                            <FormField :label="t('suppliers.notes')" :hint="t('suppliers.notesHint', 'Internal — not shown on documents.')">
                                <textarea v-model="form.notes" rows="2" class="form-textarea" :placeholder="t('suppliers.notesPlaceholder')" />
                            </FormField>

                            <div class="form-toggle">
                                <div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ t('common.status') }}</p>
                                    <p class="t-caption mt-0.5">{{ form.is_active ? t('suppliers.activeHint') : t('suppliers.inactiveHint') }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="form.is_active = !form.is_active"
                                    :class="['form-switch', { 'is-on': form.is_active }]"
                                    :aria-pressed="form.is_active"
                                    :aria-label="t('common.status')"
                                />
                            </div>
                        </FormSection>

                        <div v-if="serverError" class="card card-alert card-alert-danger text-sm">{{ serverError }}</div>
                    </form>

                    <footer class="form-modal-footer with-meta">
                        <div v-if="isEdit" class="t-caption">
                            {{ t('suppliers.codeLabel') }}: <span class="font-mono font-medium text-slate-600 dark:text-slate-300">{{ props.supplier?.code }}</span>
                        </div>
                        <div v-else />
                        <div class="flex items-center gap-2">
                            <Button variant="secondary" @click="$emit('update:open', false)">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button
                                variant="primary"
                                :loading="saving"
                                :leading-icon="saving ? null : CheckIcon"
                                @click="submit"
                            >
                                {{ saving ? t('common.saving') : (isEdit ? t('common.saveChanges') : t('suppliers.createSupplier')) }}
                            </Button>
                        </div>
                    </footer>

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
import FormField   from '@/components/ui/FormField.vue';
import FormSection from '@/components/ui/FormSection.vue';
import Button      from '@/components/ui/Button.vue';

const props = defineProps({
    open:     { type: Boolean, default: false },
    supplier: { type: Object, default: null },
});
const emit = defineEmits(['update:open', 'saved']);
const { t } = useI18n();

const isEdit = computed(() => !!props.supplier?.id);

const defaultForm = () => ({
    name: '', contact_person: '', email: '', phone: '',
    address: '', city: '', country: 'Bangladesh',
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

/* Local-only — the modal enter/leave timing. All form chrome
   (.form-input, .form-label, .form-error, .form-modal-*, .btn-*) now
   lives in app.css and is shared with every other form. */
.modal-enter-active, .modal-leave-active { transition: opacity var(--motion-base) var(--motion-out); }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .form-modal-shell,
.modal-leave-active .form-modal-shell {
    transition: transform var(--motion-base) var(--motion-spring);
}
.modal-enter-from .form-modal-shell,
.modal-leave-to   .form-modal-shell { transform: scale(0.97) translateY(8px); }
</style>
