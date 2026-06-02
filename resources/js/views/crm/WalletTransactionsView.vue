<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('crm.wallet.recentTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('crm.wallet.recentSubtitle') }}</p>
        </header>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('crm.fields.dateTime') }}</th>
                        <th class="px-4 py-2.5">{{ t('crm.fields.customer') }}</th>
                        <th class="px-4 py-2.5">{{ t('crm.wallet.type') }}</th>
                        <th class="px-4 py-2.5">{{ t('crm.wallet.note') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('crm.wallet.amount') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('crm.wallet.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ formatDateTime(r.created_at) }}</td>
                        <td class="px-4 py-2">
                            <RouterLink v-if="r.customer" :to="{ name: 'crm-customer-profile', params: { id: r.customer.id } }"
                                        class="text-indigo-600 hover:underline">{{ r.customer.name }}</RouterLink>
                        </td>
                        <td class="px-4 py-2">
                            <span :class="typeBadge(r.type)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ t('crm.wallet.types.' + r.type) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-slate-700 truncate max-w-md">{{ r.note }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold"
                            :class="r.amount >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ r.amount >= 0 ? '+' : '' }}{{ fmtCurrency(r.amount) }}
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(r.balance_after) }}</td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('crm.wallet.empty') }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs">
                <span class="text-slate-500">
                    {{ t('common.page') }} {{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }} {{ t('common.records') }}
                </span>
                <div class="flex items-center gap-1">
                    <button @click="loadPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-40">‹</button>
                    <button @click="loadPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-40">›</button>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { walletService } from '@/services/crmService';
import { useCurrency } from '@/composables/useCurrency';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);

function formatDateTime(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'en-US',
        { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
}

function typeBadge(type) {
    return {
        credit:   'bg-emerald-100 text-emerald-800',
        debit:    'bg-rose-100 text-rose-800',
        refund:   'bg-amber-100 text-amber-800',
        cashback: 'bg-indigo-100 text-indigo-800',
        deposit:  'bg-emerald-100 text-emerald-800',
        adjust:   'bg-slate-100 text-slate-700',
    }[type] ?? 'bg-slate-100 text-slate-700';
}

async function load(page = 1) {
    loading.value = true;
    try {
        const { data } = await walletService.recent({ page, per_page: 25 });
        rows.value = data.data ?? [];
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

function loadPage(p) { load(p); }
onMounted(() => load());
</script>

<style scoped>
@reference '../../../css/app.css';
.card { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
</style>
