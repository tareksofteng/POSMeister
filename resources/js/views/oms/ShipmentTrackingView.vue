<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('oms.shipment.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('oms.shipment.subtitle') }}</p>
        </header>

        <div class="card flex flex-wrap items-end gap-3">
            <div>
                <label class="lbl">{{ t('common.status') }}</label>
                <select v-model="statusFilter" @change="load" class="ctrl w-44">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ t('oms.shipmentStatus.' + s) }}</option>
                </select>
            </div>
            <div class="flex-1 min-w-56">
                <label class="lbl">{{ t('oms.shipment.searchTracking') }}</label>
                <input v-model="search" @input="loadDebounced" class="ctrl w-full" :placeholder="t('oms.shipment.trackingPh')" />
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('oms.shipment.tracking') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.shipment.order') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.shipment.courier') }}</th>
                        <th class="px-4 py-2.5">{{ t('common.status') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('oms.shipment.cost') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.shipment.dispatched') }}</th>
                        <th class="px-4 py-2.5 text-right w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="s in rows" :key="s.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-xs text-indigo-600">{{ s.tracking_number || '—' }}</td>
                        <td class="px-4 py-2 text-xs">{{ s.order?.order_number }}</td>
                        <td class="px-4 py-2 text-xs text-slate-700">{{ s.courier?.name || '—' }}</td>
                        <td class="px-4 py-2">
                            <span :class="statusBadge(s.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ t('oms.shipmentStatus.' + s.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(s.shipping_cost) }}</td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ formatDate(s.dispatched_at) }}</td>
                        <td class="px-4 py-2 text-right text-xs space-x-2">
                            <button @click="refresh(s)" class="text-indigo-600 hover:underline">{{ t('oms.shipment.poll') }}</button>
                            <button v-if="canCancel(s)" @click="cancelShipment(s)" class="text-rose-600 hover:underline">{{ t('common.cancel') }}</button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="7" class="py-10 text-center text-sm text-slate-400">{{ t('oms.shipment.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { shipmentService } from '@/services/omsService';
import { useCurrency } from '@/composables/useCurrency';
import { useAlert } from '@/composables/useAlert';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const { toast, confirm } = useAlert();

const rows = ref([]);
const loading = ref(false);
const statusFilter = ref('');
const search = ref('');
const statuses = ['pending', 'created', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'cancelled', 'failed'];

let debounceTimer = null;
function loadDebounced() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(load, 300);
}

function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'de-DE',
        { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
}

function statusBadge(status) {
    return {
        pending:          'bg-slate-100 text-slate-700',
        created:          'bg-indigo-100 text-indigo-800',
        in_transit:       'bg-amber-100 text-amber-800',
        out_for_delivery: 'bg-amber-100 text-amber-800',
        delivered:        'bg-emerald-100 text-emerald-800',
        returned:         'bg-rose-100 text-rose-800',
        cancelled:        'bg-slate-100 text-slate-700',
        failed:           'bg-rose-100 text-rose-800',
    }[status] ?? 'bg-slate-100 text-slate-700';
}
function canCancel(s) { return !['delivered', 'cancelled', 'returned'].includes(s.status); }

async function load() {
    loading.value = true;
    try {
        const { data } = await shipmentService.index({
            status: statusFilter.value || undefined,
            search: search.value || undefined,
        });
        rows.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function refresh(s) {
    await shipmentService.refresh(s.id);
    toast.success(t('oms.shipment.refreshed'));
    load();
}

async function cancelShipment(s) {
    if (!(await confirm(t('oms.shipment.cancelConfirm')))) return;
    await shipmentService.cancel(s.id);
    toast.success(t('oms.shipment.cancelled'));
    load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.lbl  { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
