<template>
    <div class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl max-h-[90vh] flex flex-col">
            <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900">{{ t('accounting.journal.manualTitle') }}</h3>
                <button @click="$emit('close')"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
            </header>
            <div class="px-5 py-4 overflow-y-auto space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl">{{ t('accounting.fields.entryDate') }}</label>
                        <input v-model="form.entry_date" type="date" class="ctrl w-full" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('accounting.fields.referenceNumber') }}</label>
                        <input v-model="form.reference_number" class="ctrl w-full" placeholder="Optional" />
                    </div>
                </div>
                <div>
                    <label class="lbl">{{ t('accounting.fields.narration') }}</label>
                    <input v-model="form.narration" class="ctrl w-full" :placeholder="t('accounting.journal.narrationPh')" />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('accounting.journal.lines') }}</h4>
                        <button @click="addLine" class="text-xs text-indigo-600 hover:underline">+ {{ t('accounting.journal.addLine') }}</button>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                                <th class="py-1.5">{{ t('accounting.fields.account') }}</th>
                                <th class="py-1.5 text-right w-28">{{ t('accounting.fields.debit') }}</th>
                                <th class="py-1.5 text-right w-28">{{ t('accounting.fields.credit') }}</th>
                                <th class="py-1.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, i) in form.lines" :key="i" class="border-b border-slate-50">
                                <td class="py-1">
                                    <select v-model="line.account_id" class="ctrl w-full">
                                        <option :value="null">{{ t('accounting.journal.selectAccount') }}</option>
                                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                                            {{ a.account_code }} — {{ a.account_name }}
                                        </option>
                                    </select>
                                </td>
                                <td class="py-1">
                                    <input v-model.number="line.debit" type="number" step="0.01" class="ctrl w-full text-right font-mono" />
                                </td>
                                <td class="py-1">
                                    <input v-model.number="line.credit" type="number" step="0.01" class="ctrl w-full text-right font-mono" />
                                </td>
                                <td class="py-1 text-right">
                                    <button @click="removeLine(i)" class="text-xs text-rose-600 hover:underline">×</button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200">
                                <td class="py-2 text-sm font-semibold text-slate-700">{{ t('accounting.fields.totals') }}</td>
                                <td class="py-2 text-right font-mono font-bold">{{ totals.debit.toFixed(2) }}</td>
                                <td class="py-2 text-right font-mono font-bold">{{ totals.credit.toFixed(2) }}</td>
                                <td></td>
                            </tr>
                            <tr v-if="!balanced">
                                <td colspan="4" class="py-1 text-rose-700 text-xs">
                                    {{ t('accounting.journal.notBalanced', { diff: Math.abs(totals.debit - totals.credit).toFixed(2) }) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <footer class="px-5 py-3 border-t border-slate-100 flex justify-between items-center">
                <span class="text-xs text-slate-500">
                    {{ t('accounting.journal.balanceHint') }}
                </span>
                <div class="flex gap-2">
                    <button @click="$emit('close')" class="btn-soft">{{ t('common.cancel') }}</button>
                    <button @click="save" :disabled="!balanced || saving" class="btn-primary">
                        {{ t('common.post') }}
                    </button>
                </div>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { chartOfAccountsService, journalEntryService } from '@/services/accountingService';
import { useAlert } from '@/composables/useAlert';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const emit = defineEmits(['close', 'saved']);
const { t } = useI18n();
const { toast } = useAlert();

const accounts = ref([]);
const saving = ref(false);

const form = reactive({
    entry_date: new Date().toISOString().slice(0, 10),
    reference_number: '',
    narration: '',
    lines: [
        { account_id: null, debit: 0, credit: 0 },
        { account_id: null, debit: 0, credit: 0 },
    ],
});

const totals = computed(() => {
    const d = form.lines.reduce((s, l) => s + (parseFloat(l.debit)  || 0), 0);
    const c = form.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0);
    return { debit: d, credit: c };
});

const balanced = computed(() => {
    return totals.value.debit > 0 && Math.abs(totals.value.debit - totals.value.credit) < 0.01;
});

function addLine() { form.lines.push({ account_id: null, debit: 0, credit: 0 }); }
function removeLine(i) { if (form.lines.length > 2) form.lines.splice(i, 1); }

async function save() {
    if (!balanced.value) return;
    saving.value = true;
    try {
        const payload = {
            entry_date: form.entry_date,
            reference_type: 'manual',
            reference_number: form.reference_number || null,
            narration: form.narration || null,
            status: 'posted',
            lines: form.lines
                .filter(l => l.account_id && ((parseFloat(l.debit) || 0) > 0 || (parseFloat(l.credit) || 0) > 0))
                .map(l => ({
                    account_id: l.account_id,
                    debit:  parseFloat(l.debit)  || 0,
                    credit: parseFloat(l.credit) || 0,
                })),
        };
        await journalEntryService.store(payload);
        emit('saved');
    } catch (e) {
        toast.error(e?.response?.data?.message ?? 'Save failed');
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    const { data } = await chartOfAccountsService.index({ active_only: 1 });
    accounts.value = data.data ?? [];
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
