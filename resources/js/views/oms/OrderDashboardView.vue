<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('oms.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('oms.dashboard.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('oms.dashboard.subtitle') }}</p>
            </div>
            <button @click="load" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" /> {{ t('common.refresh') }}
            </button>
        </header>

        <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <KpiCard :label="t('oms.kpi.totalMonth')"      :value="d?.total_month ?? '—'"        tone="indigo" :icon="ShoppingCartIcon" />
            <KpiCard :label="t('oms.kpi.openOrders')"      :value="d?.open_orders ?? '—'"        tone="amber"  :icon="ClockIcon" />
            <KpiCard :label="t('oms.kpi.delivered')"       :value="d?.delivered_month ?? '—'"    tone="emerald" />
            <KpiCard :label="t('oms.kpi.cancelled')"       :value="d?.cancelled_month ?? '—'"    tone="rose" />
            <KpiCard :label="t('oms.kpi.delayed')"         :value="d?.delayed_orders ?? '—'"     tone="rose" :icon="ExclamationTriangleIcon" />
            <KpiCard :label="t('oms.kpi.avgDelivery')"     :value="d?.avg_delivery_hours !== null && d?.avg_delivery_hours !== undefined ? d.avg_delivery_hours + ' h' : '—'" tone="indigo" />
            <KpiCard :label="t('oms.kpi.fulfilmentRate')"  :value="d ? d.fulfilment_rate + '%' : '—'" tone="emerald" />
            <KpiCard :label="t('oms.kpi.returnRate')"      :value="d ? d.return_rate + '%' : '—'"     tone="amber" />
        </section>

        <section v-if="d?.by_status" class="card">
            <h3 class="card-title">{{ t('oms.dashboard.statusBreakdown') }}</h3>
            <div class="space-y-2">
                <div v-for="status in statuses" :key="status" class="grid grid-cols-12 items-center gap-3 text-xs">
                    <span class="col-span-3 text-slate-700 font-medium">{{ t('oms.status.' + status) }}</span>
                    <div class="col-span-7 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full" :class="statusBar(status)"
                             :style="{ width: barPct(d.by_status[status] ?? 0) + '%' }"></div>
                    </div>
                    <span class="col-span-2 text-right text-slate-700 font-mono">{{ d.by_status[status] ?? 0 }}</span>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="card-title mb-0">{{ t('oms.dashboard.recent') }}</h3>
                <div class="flex items-center gap-2 text-xs">
                    <select v-model="statusFilter" @change="loadOrders" class="ctrl">
                        <option value="">{{ t('common.all') }}</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ t('oms.status.' + s) }}</option>
                    </select>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('oms.fields.orderNumber') }}</th>
                        <th class="py-2">{{ t('oms.fields.customer') }}</th>
                        <th class="py-2">{{ t('oms.fields.source') }}</th>
                        <th class="py-2">{{ t('oms.fields.status') }}</th>
                        <th class="py-2 text-right">{{ t('oms.fields.total') }}</th>
                        <th class="py-2">{{ t('oms.fields.placedAt') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="o in orders" :key="o.id" class="hover:bg-slate-50/60">
                        <td class="py-2 font-mono text-xs text-indigo-600">{{ o.order_number }}</td>
                        <td class="py-2 text-slate-800">{{ o.customer?.name || o.customer_name || '—' }}</td>
                        <td class="py-2 text-xs">
                            <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md text-[10px] uppercase tracking-wider">{{ o.source }}</span>
                        </td>
                        <td class="py-2">
                            <span :class="statusBadge(o.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ t('oms.status.' + o.status) }}
                            </span>
                        </td>
                        <td class="py-2 text-right font-mono">{{ fmtCurrency(o.total) }}</td>
                        <td class="py-2 text-xs text-slate-600">{{ formatDate(o.placed_at) }}</td>
                    </tr>
                    <tr v-if="!loading && orders.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('oms.dashboard.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { orderService } from '@/services/omsService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowPathIcon, ShoppingCartIcon, ClockIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const d = ref(null);
const orders = ref([]);
const loading = ref(false);
const statusFilter = ref('');
const statuses = ['pending', 'confirmed', 'packed', 'shipped', 'delivered', 'cancelled', 'returned'];

function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'de-DE',
        { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
}

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200',
        rose:    'border-rose-200',
        indigo:  'border-indigo-200',
        amber:   'border-amber-200',
    }[props.tone] ?? 'border-slate-200';
    const iconColor = {
        emerald: 'text-emerald-500',
        rose:    'text-rose-500',
        indigo:  'text-indigo-500',
        amber:   'text-amber-500',
    }[props.tone] ?? 'text-slate-400';
    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('div', { class: 'flex items-start justify-between gap-2' }, [
            h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            props.icon ? h(props.icon, { class: `w-4 h-4 ${iconColor}` }) : null,
        ]),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone', 'icon'];

function statusBadge(status) {
    return {
        pending:   'bg-slate-100 text-slate-700',
        confirmed: 'bg-indigo-100 text-indigo-800',
        packed:    'bg-amber-100 text-amber-800',
        shipped:   'bg-amber-100 text-amber-800',
        delivered: 'bg-emerald-100 text-emerald-800',
        cancelled: 'bg-rose-100 text-rose-800',
        returned:  'bg-rose-100 text-rose-800',
    }[status] ?? 'bg-slate-100 text-slate-700';
}
function statusBar(status) {
    return {
        pending:   'bg-slate-400',
        confirmed: 'bg-indigo-500',
        packed:    'bg-amber-400',
        shipped:   'bg-amber-500',
        delivered: 'bg-emerald-500',
        cancelled: 'bg-rose-500',
        returned:  'bg-rose-600',
    }[status] ?? 'bg-slate-300';
}
function barPct(value) {
    if (!d.value?.by_status) return 0;
    const max = Math.max(...Object.values(d.value.by_status), 1);
    return Math.max(2, (value / max) * 100);
}

async function load() {
    loading.value = true;
    try {
        const [{ data: dash }] = await Promise.all([orderService.dashboard()]);
        d.value = dash.data;
        await loadOrders();
    } finally {
        loading.value = false;
    }
}

async function loadOrders() {
    const { data } = await orderService.index({
        status: statusFilter.value || undefined,
        per_page: 15,
    });
    orders.value = data.data ?? [];
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
