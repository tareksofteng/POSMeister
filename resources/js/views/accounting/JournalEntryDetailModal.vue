<template>
    <div class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl max-h-[90vh] overflow-hidden flex flex-col">
            <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">{{ entry.entry_number }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ formatDate(entry.entry_date) }} · {{ t('accounting.status.' + entry.status) }}
                    </p>
                </div>
                <button @click="$emit('close')"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
            </header>
            <div class="px-5 py-4 overflow-y-auto">
                <div v-if="entry.narration" class="text-sm text-slate-700 mb-3">{{ entry.narration }}</div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                            <th class="py-2">{{ t('accounting.fields.account') }}</th>
                            <th class="py-2">{{ t('accounting.fields.narration') }}</th>
                            <th class="py-2 text-right">{{ t('accounting.fields.debit') }}</th>
                            <th class="py-2 text-right">{{ t('accounting.fields.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="l in entry.lines" :key="l.id">
                            <td class="py-2">
                                <span class="font-mono text-xs text-slate-500">{{ l.account?.account_code }}</span>
                                <span class="text-slate-800 ml-2">{{ l.account?.account_name }}</span>
                            </td>
                            <td class="py-2 text-slate-600">{{ l.narration }}</td>
                            <td class="py-2 text-right font-mono">{{ l.debit > 0 ? fmt(l.debit) : '' }}</td>
                            <td class="py-2 text-right font-mono">{{ l.credit > 0 ? fmt(l.credit) : '' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-200 font-bold">
                            <td colspan="2" class="py-2 text-slate-700">{{ t('accounting.fields.totals') }}</td>
                            <td class="py-2 text-right font-mono">{{ fmt(entry.total_debit) }}</td>
                            <td class="py-2 text-right font-mono">{{ fmt(entry.total_credit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <footer class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">
                <button @click="$emit('close')" class="btn-soft">{{ t('common.close') }}</button>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/composables/useCurrency';
import { XMarkIcon } from '@heroicons/vue/24/outline';

defineProps({ entry: Object });
defineEmits(['close']);

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

function fmt(v) { return fmtCurrency(v); }
function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-soft { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
</style>
