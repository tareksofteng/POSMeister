<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-3xl mx-auto">

        <header>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('crm.loyaltySettings.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('crm.loyaltySettings.subtitle') }}</p>
        </header>

        <form v-if="settings" @submit.prevent="save" class="space-y-5">

            <section class="card">
                <h3 class="card-title">{{ t('crm.loyaltySettings.general') }}</h3>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 mb-3">
                    <input type="checkbox" v-model="settings.enabled" class="rounded border-slate-300" />
                    {{ t('crm.loyaltySettings.enable') }}
                </label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="lbl">{{ t('crm.loyaltySettings.earnPer') }}</label>
                        <input v-model.number="settings.earn_per_currency" type="number" step="0.0001" class="ctrl w-full font-mono" />
                        <p class="text-[10px] text-slate-500 mt-1">{{ t('crm.loyaltySettings.earnHint') }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('crm.loyaltySettings.redeemRatio') }}</label>
                        <input v-model.number="settings.redeem_points_per_currency" type="number" min="1" class="ctrl w-full font-mono" />
                        <p class="text-[10px] text-slate-500 mt-1">{{ t('crm.loyaltySettings.redeemHint') }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('crm.loyaltySettings.minRedeem') }}</label>
                        <input v-model.number="settings.min_redeem_points" type="number" min="0" class="ctrl w-full font-mono" />
                    </div>
                </div>
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('crm.loyaltySettings.tierThresholds') }}</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                            <th class="py-2">{{ t('crm.fields.tier') }}</th>
                            <th class="py-2 text-right">{{ t('crm.loyaltySettings.minSpend') }}</th>
                            <th class="py-2 text-right">{{ t('crm.loyaltySettings.discountPct') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="tier in ['silver', 'gold', 'platinum', 'vip']" :key="tier">
                            <td class="py-2 text-slate-800 font-semibold capitalize">{{ tier }}</td>
                            <td class="py-2 text-right">
                                <input v-model.number="settings[`tier_${tier}_min`]" type="number" step="0.01" class="ctrl w-32 text-right font-mono" />
                            </td>
                            <td class="py-2 text-right">
                                <input v-model.number="settings[`tier_${tier}_discount`]" type="number" step="0.01" class="ctrl w-24 text-right font-mono" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 mt-3">
                    <input type="checkbox" v-model="settings.auto_downgrade" class="rounded border-slate-300" />
                    {{ t('crm.loyaltySettings.autoDowngrade') }}
                </label>
            </section>

            <div class="flex justify-end">
                <button type="submit" :disabled="saving" class="btn-primary">
                    <CheckIcon class="w-4 h-4" /> {{ t('common.save') }}
                </button>
            </div>
        </form>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { loyaltyService } from '@/services/crmService';
import { useAlert } from '@/composables/useAlert';
import { CheckIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast } = useAlert();

const settings = ref(null);
const saving = ref(false);

async function load() {
    const { data } = await loyaltyService.settings();
    settings.value = data.data;
}

async function save() {
    saving.value = true;
    try {
        await loyaltyService.saveSettings(settings.value);
        toast.success(t('common.updated'));
        load();
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-primary { @apply inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
