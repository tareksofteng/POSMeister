<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.bank.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.bank.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('accounting.bank.add') }}
            </button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="b in rows" :key="b.id" class="card hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900 truncate">{{ b.name }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ b.bank_name || '—' }}</p>
                    </div>
                    <BuildingLibraryIcon class="w-6 h-6 text-indigo-400" />
                </div>

                <div class="text-xs text-slate-500 space-y-1 mb-3">
                    <p v-if="b.iban"><span class="font-medium text-slate-600">IBAN:</span> <span class="font-mono">{{ b.iban }}</span></p>
                    <p v-if="b.account_number"><span class="font-medium text-slate-600">{{ t('accounting.bank.accountNumber') }}:</span> <span class="font-mono">{{ b.account_number }}</span></p>
                    <p v-if="b.account">
                        <span class="font-medium text-slate-600">{{ t('accounting.bank.coa') }}:</span>
                        <span class="font-mono">{{ b.account.account_code }} — {{ b.account.account_name }}</span>
                    </p>
                </div>

                <div class="border-t border-slate-100 pt-3 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ t('accounting.bank.currentBalance') }}</p>
                        <p class="text-lg font-bold font-mono mt-0.5"
                           :class="(b.current_balance ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ fmt(b.current_balance) }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1 text-xs">
                        <button @click="openEdit(b)" class="text-indigo-600 hover:underline">{{ t('common.edit') }}</button>
                        <button @click="destroy(b)" class="text-rose-600 hover:underline">{{ t('common.delete') }}</button>
                    </div>
                </div>
            </div>

            <div v-if="!loading && rows.length === 0" class="col-span-full text-center text-sm text-slate-400 py-12">
                {{ t('accounting.bank.empty') }}
            </div>
        </div>

        <div v-if="formOpen" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-xl">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ editing?.id ? t('accounting.bank.editTitle') : t('accounting.bank.createTitle') }}
                    </h3>
                    <button @click="formOpen = false"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
                </header>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label class="lbl">{{ t('accounting.bank.fields.name') }}</label>
                        <input v-model="form.name" class="ctrl w-full" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">{{ t('accounting.bank.fields.bankName') }}</label>
                            <input v-model="form.bank_name" class="ctrl w-full" />
                        </div>
                        <div>
                            <label class="lbl">{{ t('accounting.bank.fields.accountNumber') }}</label>
                            <input v-model="form.account_number" class="ctrl w-full" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">IBAN</label>
                            <input v-model="form.iban" class="ctrl w-full font-mono" placeholder="DE00 0000 0000 0000 0000 00" />
                        </div>
                        <div>
                            <label class="lbl">BIC</label>
                            <input v-model="form.bic" class="ctrl w-full font-mono" />
                        </div>
                    </div>
                    <div>
                        <label class="lbl">{{ t('accounting.bank.fields.coa') }}</label>
                        <select v-model="form.coa_account_id" class="ctrl w-full">
                            <option :value="null">—</option>
                            <option v-for="a in bankAccounts" :key="a.id" :value="a.id">
                                {{ a.account_code }} — {{ a.account_name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">{{ t('accounting.bank.fields.openingBalance') }}</label>
                            <input v-model.number="form.opening_balance" type="number" step="0.01" class="ctrl w-full font-mono" />
                        </div>
                        <div>
                            <label class="lbl">{{ t('accounting.bank.fields.openingDate') }}</label>
                            <input v-model="form.opening_date" type="date" class="ctrl w-full" />
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300" />
                        {{ t('common.active') }}
                    </label>
                </div>
                <footer class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="formOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                    <button @click="save" :disabled="saving" class="btn-primary">{{ t('common.save') }}</button>
                </footer>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { bankAccountService, chartOfAccountsService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, XMarkIcon, BuildingLibraryIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();
const { toast, confirm } = useAlert();

const rows = ref([]);
const bankAccounts = ref([]);
const loading = ref(false);

const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = reactive({
    name: '', bank_name: '', account_number: '', iban: '', bic: '',
    coa_account_id: null, opening_balance: 0, opening_date: null, is_active: true,
});

function fmt(v) { return v === null || v === undefined ? '—' : fmtCurrency(v); }

async function load() {
    loading.value = true;
    try {
        const { data } = await bankAccountService.index();
        rows.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    Object.assign(form, {
        name: '', bank_name: '', account_number: '', iban: '', bic: '',
        coa_account_id: null, opening_balance: 0, opening_date: null, is_active: true,
    });
    formOpen.value = true;
}

function openEdit(b) {
    editing.value = b;
    Object.assign(form, {
        name: b.name,
        bank_name: b.bank_name,
        account_number: b.account_number,
        iban: b.iban,
        bic: b.bic,
        coa_account_id: b.coa_account_id,
        opening_balance: parseFloat(b.opening_balance) || 0,
        opening_date: b.opening_date,
        is_active: !!b.is_active,
    });
    formOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await bankAccountService.update(editing.value.id, form);
            toast.success(t('common.updated'));
        } else {
            await bankAccountService.store(form);
            toast.success(t('common.created'));
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function destroy(b) {
    const ok = await confirm(t('accounting.bank.deleteConfirm', { name: b.name }));
    if (!ok) return;
    await bankAccountService.destroy(b.id);
    toast.success(t('common.deleted'));
    await load();
}

onMounted(async () => {
    const { data } = await chartOfAccountsService.index({ active_only: 1, type: 'asset' });
    bankAccounts.value = (data.data ?? []).filter(a => a.account_code.startsWith('11'));
    await load();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
