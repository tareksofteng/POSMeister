<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.coa.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.coa.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('accounting.coa.add') }}
            </button>
        </header>

        <div class="card flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-56">
                <label class="lbl">{{ t('common.search') }}</label>
                <input v-model="filters.search" @input="loadDebounced" class="ctrl w-full" :placeholder="t('accounting.coa.searchPh')" />
            </div>
            <div>
                <label class="lbl">{{ t('accounting.fields.accountType') }}</label>
                <select v-model="filters.type" @change="load" class="ctrl">
                    <option value="">{{ t('common.all') }}</option>
                    <option value="asset">{{ t('accounting.types.asset') }}</option>
                    <option value="liability">{{ t('accounting.types.liability') }}</option>
                    <option value="equity">{{ t('accounting.types.equity') }}</option>
                    <option value="revenue">{{ t('accounting.types.revenue') }}</option>
                    <option value="expense">{{ t('accounting.types.expense') }}</option>
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 pb-2">
                <input type="checkbox" v-model="filters.activeOnly" @change="load" class="rounded border-slate-300" />
                {{ t('common.activeOnly') }}
            </label>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('accounting.fields.accountCode') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.accountName') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.accountType') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.normalBalance') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ t('common.status') }}</th>
                        <th class="px-4 py-2.5 text-right w-28"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="a in rows" :key="a.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-slate-800 font-semibold">{{ a.account_code }}</td>
                        <td class="px-4 py-2 text-slate-800">
                            {{ a.account_name }}
                            <span v-if="a.is_system" class="ml-2 text-[10px] uppercase tracking-wider text-indigo-600 font-semibold">
                                {{ t('accounting.coa.systemBadge') }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="text-xs px-2 py-0.5 rounded-md" :class="typeClass(a.account_type)">
                                {{ t('accounting.types.' + a.account_type) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ t('accounting.balance.' + a.normal_balance) }}</td>
                        <td class="px-4 py-2 text-center">
                            <span :class="['inline-block w-2 h-2 rounded-full', a.is_active ? 'bg-emerald-500' : 'bg-slate-300']"></span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button @click="openEdit(a)" class="text-xs text-indigo-600 hover:underline mr-3">
                                {{ t('common.edit') }}
                            </button>
                            <button v-if="!a.is_system" @click="destroy(a)" class="text-xs text-rose-600 hover:underline">
                                {{ t('common.delete') }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('accounting.coa.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="formOpen" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-xl">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ editing?.id ? t('accounting.coa.editTitle') : t('accounting.coa.createTitle') }}
                    </h3>
                    <button @click="formOpen = false"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
                </header>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label class="lbl">{{ t('accounting.fields.accountCode') }}</label>
                        <input v-model="form.account_code" class="ctrl w-full" placeholder="z.B. 1000" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('accounting.fields.accountName') }}</label>
                        <input v-model="form.account_name" class="ctrl w-full" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">{{ t('accounting.fields.accountType') }}</label>
                            <select v-model="form.account_type" @change="syncNormalBalance" class="ctrl w-full">
                                <option value="asset">{{ t('accounting.types.asset') }}</option>
                                <option value="liability">{{ t('accounting.types.liability') }}</option>
                                <option value="equity">{{ t('accounting.types.equity') }}</option>
                                <option value="revenue">{{ t('accounting.types.revenue') }}</option>
                                <option value="expense">{{ t('accounting.types.expense') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl">{{ t('accounting.fields.normalBalance') }}</label>
                            <select v-model="form.normal_balance" class="ctrl w-full">
                                <option value="debit">{{ t('accounting.balance.debit') }}</option>
                                <option value="credit">{{ t('accounting.balance.credit') }}</option>
                            </select>
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
import { chartOfAccountsService } from '@/services/accountingService';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { confirm, toast } = useAlert();

const rows = ref([]);
const loading = ref(false);
const filters = reactive({ search: '', type: '', activeOnly: true });

let debounceTimer = null;
function loadDebounced() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(load, 300);
}

const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = reactive({
    account_code: '', account_name: '',
    account_type: 'asset', normal_balance: 'debit',
    is_active: true,
});

function syncNormalBalance() {
    form.normal_balance = ['asset', 'expense'].includes(form.account_type) ? 'debit' : 'credit';
}

function typeClass(type) {
    return {
        asset:     'bg-indigo-50 text-indigo-700',
        liability: 'bg-rose-50 text-rose-700',
        equity:    'bg-amber-50 text-amber-700',
        revenue:   'bg-emerald-50 text-emerald-700',
        expense:   'bg-slate-100 text-slate-700',
    }[type] ?? 'bg-slate-100 text-slate-700';
}

function openCreate() {
    editing.value = null;
    Object.assign(form, { account_code: '', account_name: '', account_type: 'asset', normal_balance: 'debit', is_active: true });
    formOpen.value = true;
}

function openEdit(a) {
    editing.value = a;
    Object.assign(form, {
        account_code: a.account_code,
        account_name: a.account_name,
        account_type: a.account_type,
        normal_balance: a.normal_balance,
        is_active: !!a.is_active,
    });
    formOpen.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await chartOfAccountsService.index({
            search: filters.search || undefined,
            type: filters.type || undefined,
            active_only: filters.activeOnly ? 1 : 0,
        });
        rows.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await chartOfAccountsService.update(editing.value.id, form);
            toast.success(t('common.updated'));
        } else {
            await chartOfAccountsService.store(form);
            toast.success(t('common.created'));
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function destroy(a) {
    const ok = await confirm(t('accounting.coa.deleteConfirm', { name: a.account_name }));
    if (!ok) return;
    await chartOfAccountsService.destroy(a.id);
    toast.success(t('common.deleted'));
    await load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
