<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-3xl">

        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('appSettings.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('appSettings.subtitle') }}</p>
        </div>

        <!-- Save feedback -->
        <div v-if="saveSuccess" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 flex items-center gap-2">
            <CheckCircleIcon class="w-4 h-4 flex-shrink-0" />
            {{ t('appSettings.savedSuccess') }}
        </div>
        <div v-if="saveError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ saveError }}
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-4">
            <div v-for="i in 4" :key="i" class="animate-pulse h-14 bg-gray-100 rounded-xl" />
        </div>

        <form v-else @submit.prevent="save" class="space-y-6">

            <!-- ── Section 1: Company Info ─────────────────────────────── -->
            <SectionCard :title="t('appSettings.sectionCompany')">

                <!-- Logo -->
                <div class="flex items-start gap-6">
                    <div class="flex-shrink-0 w-20 h-20 rounded-xl border border-gray-200 overflow-hidden bg-gray-50 flex items-center justify-center">
                        <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-contain p-1" alt="logo" />
                        <BuildingOffice2Icon v-else class="w-8 h-8 text-gray-300" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <p class="text-sm font-medium text-gray-700">{{ t('appSettings.logo') }}</p>
                        <p class="text-xs text-gray-400">{{ t('appSettings.logoHint') }}</p>
                        <div class="flex gap-2 flex-wrap">
                            <label class="btn-secondary cursor-pointer">
                                <input type="file" class="sr-only" accept="image/*" @change="onLogoSelect" />
                                <ArrowUpTrayIcon class="w-3.5 h-3.5" />
                                {{ t('appSettings.uploadLogo') }}
                            </label>
                            <button
                                v-if="logoPreview"
                                type="button"
                                @click="removeLogo"
                                class="btn-danger-outline"
                            >
                                <TrashIcon class="w-3.5 h-3.5" />
                                {{ t('appSettings.removeLogo') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormField :label="t('appSettings.companyName')" required>
                        <input v-model="form.company_name" type="text" class="field-input" required maxlength="120" />
                    </FormField>
                    <FormField :label="t('appSettings.phone')">
                        <input v-model="form.phone" type="tel" class="field-input" maxlength="30" />
                    </FormField>
                    <FormField :label="t('appSettings.email')">
                        <input v-model="form.email" type="email" class="field-input" maxlength="120" />
                    </FormField>
                </div>

                <FormField :label="t('appSettings.address')">
                    <textarea v-model="form.address" rows="2" class="field-input resize-none" maxlength="500" />
                </FormField>
            </SectionCard>

            <!-- ── Section 2: Currency & Tax ──────────────────────────── -->
            <SectionCard :title="t('appSettings.sectionCurrency')">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <FormField :label="t('appSettings.currencyCode')" required>
                        <input v-model="form.currency_code" type="text" class="field-input" required maxlength="10" placeholder="EUR" />
                    </FormField>
                    <FormField :label="t('appSettings.currencySymbol')" required>
                        <input v-model="form.currency_symbol" type="text" class="field-input" required maxlength="10" placeholder="€" />
                    </FormField>
                    <FormField :label="t('appSettings.vatDefault')" required>
                        <select v-model="form.vat_default" class="field-input">
                            <option :value="0">0% — {{ t('appSettings.vatFree') }}</option>
                            <option :value="7">7% — {{ t('appSettings.vatReduced') }}</option>
                            <option :value="19">19% — {{ t('appSettings.vatStandard') }}</option>
                        </select>
                    </FormField>
                </div>
            </SectionCard>

            <!-- ── Section 3: Invoice Settings ───────────────────────── -->
            <SectionCard :title="t('appSettings.sectionInvoice')">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormField :label="t('appSettings.invoicePrefix')" required>
                        <input v-model="form.invoice_prefix" type="text" class="field-input" required maxlength="20" placeholder="INV-" />
                    </FormField>
                    <FormField :label="t('appSettings.dateFormat')" required>
                        <select v-model="form.date_format" class="field-input">
                            <option value="d.m.Y">DD.MM.YYYY (German)</option>
                            <option value="m/d/Y">MM/DD/YYYY (US)</option>
                            <option value="Y-m-d">YYYY-MM-DD (ISO)</option>
                        </select>
                    </FormField>
                </div>
                <FormField :label="t('appSettings.invoiceFooter')">
                    <textarea
                        v-model="form.invoice_footer"
                        rows="3"
                        class="field-input resize-none"
                        maxlength="1000"
                        :placeholder="t('appSettings.invoiceFooterPlaceholder')"
                    />
                </FormField>
            </SectionCard>

            <!-- Save button -->
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="saving">
                    <ArrowPathIcon v-if="saving" class="w-4 h-4 animate-spin" />
                    <CheckIcon v-else class="w-4 h-4" />
                    {{ saving ? t('common.saving') : t('common.saveChanges') }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, defineComponent, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { settingsService } from '@/services/settingsService';
import { useSettingsStore } from '@/stores/settings';
import {
    CheckCircleIcon, ArrowUpTrayIcon, TrashIcon,
    ArrowPathIcon, CheckIcon, BuildingOffice2Icon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const settingsStore = useSettingsStore();

// ── Form state ─────────────────────────────────────────────────────────────
const loading     = ref(true);
const saving      = ref(false);
const saveSuccess = ref(false);
const saveError   = ref('');

const form = reactive({
    company_name:    '',
    address:         '',
    phone:           '',
    email:           '',
    currency_code:   'EUR',
    currency_symbol: '€',
    vat_default:     19,
    invoice_prefix:  'INV-',
    invoice_footer:  '',
    date_format:     'd.m.Y',
});

// ── Logo handling ──────────────────────────────────────────────────────────
const logoPreview   = ref(null);
const selectedLogo  = ref(null);
const removingLogo  = ref(false);

function onLogoSelect(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    selectedLogo.value = file;
    logoPreview.value  = URL.createObjectURL(file);
    removingLogo.value = false;
}

function removeLogo() {
    selectedLogo.value = null;
    logoPreview.value  = null;
    removingLogo.value = true;
}

// ── Load ───────────────────────────────────────────────────────────────────
onMounted(async () => {
    try {
        const { data } = await settingsService.get();
        const s = data.data ?? data;
        Object.assign(form, {
            company_name:    s.company_name    ?? '',
            address:         s.address         ?? '',
            phone:           s.phone           ?? '',
            email:           s.email           ?? '',
            currency_code:   s.currency_code   ?? 'EUR',
            currency_symbol: s.currency_symbol ?? '€',
            vat_default:     s.vat_default     ?? 19,
            invoice_prefix:  s.invoice_prefix  ?? 'INV-',
            invoice_footer:  s.invoice_footer  ?? '',
            date_format:     s.date_format     ?? 'd.m.Y',
        });
        logoPreview.value = s.logo_url ?? null;
    } finally {
        loading.value = false;
    }
});

// ── Save ───────────────────────────────────────────────────────────────────
async function save() {
    saving.value      = true;
    saveSuccess.value = false;
    saveError.value   = '';

    try {
        // 1. Save text settings
        const { data } = await settingsService.update({ ...form });
        const updated  = data.data ?? data;
        settingsStore.patch(updated);

        // 2. Upload logo if a new file was picked
        if (selectedLogo.value) {
            const { data: ld } = await settingsService.uploadLogo(selectedLogo.value);
            settingsStore.patch(ld.data ?? ld);
            selectedLogo.value = null;
        }

        // 3. Remove logo if user clicked Remove
        if (removingLogo.value) {
            await settingsService.deleteLogo();
            settingsStore.patch({ ...settingsStore.settings, logo: null, logo_url: null });
            removingLogo.value = false;
        }

        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);

    } catch (err) {
        saveError.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        saving.value = false;
    }
}

// ── Sub-components ─────────────────────────────────────────────────────────
const SectionCard = defineComponent({
    props: { title: String },
    setup(props, { slots }) {
        return () => h('div', { class: 'bg-white rounded-xl border border-gray-200 overflow-hidden' }, [
            h('div', { class: 'px-5 py-3 border-b border-gray-100 bg-gray-50' },
                h('h2', { class: 'text-sm font-semibold text-gray-700' }, props.title)
            ),
            h('div', { class: 'p-5 space-y-4' }, slots.default?.()),
        ]);
    },
});

const FormField = defineComponent({
    props: { label: String, required: Boolean },
    setup(props, { slots }) {
        return () => h('div', { class: 'space-y-1' }, [
            h('label', { class: 'block text-sm font-medium text-gray-700' }, [
                props.label,
                props.required ? h('span', { class: 'ml-0.5 text-red-500' }, '*') : null,
            ]),
            slots.default?.(),
        ]);
    },
});
</script>

<style scoped>
@reference '../../../css/app.css';

.field-input {
    @apply w-full px-3 py-2 text-sm border border-gray-300 rounded-lg
           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
           bg-white text-gray-900 placeholder-gray-400;
}
.btn-primary {
    @apply flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white
           bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm
           disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-secondary {
    @apply flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700
           border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors;
}
.btn-danger-outline {
    @apply flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600
           border border-red-300 rounded-lg hover:bg-red-50 transition-colors;
}
</style>
