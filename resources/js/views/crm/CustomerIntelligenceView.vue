<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header>
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('crm.module') }}</p>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('crm.intelligence.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('crm.intelligence.subtitle') }}</p>
        </header>

        <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
            <KpiCard :label="t('crm.kpi.active')"        :value="d ? d.total_active_customers : '—'" tone="indigo" :icon="UsersIcon" />
            <KpiCard :label="t('crm.kpi.newMonth')"      :value="d ? d.new_this_month : '—'" tone="emerald" :icon="UserPlusIcon" />
            <KpiCard :label="t('crm.kpi.repeatPct')"     :value="d ? d.repeat_customer_pct + '%' : '—'" tone="indigo" />
            <KpiCard :label="t('crm.kpi.aov')"           :value="d ? fmtCurrency(d.avg_order_value) : '—'" tone="emerald" />
            <KpiCard :label="t('crm.kpi.clv')"           :value="d ? fmtCurrency(d.avg_lifetime_value) : '—'" tone="indigo" />
            <KpiCard :label="t('crm.kpi.inactive')"      :value="d ? d.inactive_count : '—'" tone="amber" :icon="ClockIcon" />
            <KpiCard :label="t('crm.kpi.recentOrders')"  :value="d ? d.orders_recent : '—'" tone="slate" />
            <KpiCard :label="t('crm.kpi.recentRevenue')" :value="d ? fmtCurrency(d.revenue_recent) : '—'" tone="emerald" />
            <KpiCard :label="t('crm.kpi.pointsOut')"     :value="d ? Math.round(d.loyalty_points_outstanding) : '—'" tone="amber" :sub="t('crm.kpi.points')" />
            <KpiCard :label="t('crm.kpi.liability')"     :value="d ? fmtCurrency(d.loyalty_liability_value) : '—'" tone="rose" :icon="BanknotesIcon" />
        </section>

        <section v-if="d?.segment_counts" class="card">
            <h3 class="card-title">{{ t('crm.segments.title') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <RouterLink v-for="(count, name) in d.segment_counts" :key="name"
                            :to="{ name: 'crm-segments', query: { name } }"
                            class="border border-slate-200 rounded-lg p-3 hover:bg-indigo-50/40 hover:border-indigo-200 transition-colors">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500 font-medium">{{ t('crm.segments.' + name) }}</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1 font-mono">{{ count }}</p>
                </RouterLink>
            </div>
        </section>

        <section class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="card-title mb-0">{{ t('crm.intelligence.topSpending') }}</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('crm.fields.customer') }}</th>
                        <th class="py-2">{{ t('crm.fields.tier') }}</th>
                        <th class="py-2 text-right">{{ t('crm.fields.visits') }}</th>
                        <th class="py-2 text-right">{{ t('crm.fields.lifetimeSpent') }}</th>
                        <th class="py-2 text-right w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="c in d?.top_spending ?? []" :key="c.customer_id" class="hover:bg-slate-50/60">
                        <td class="py-2 text-slate-800 font-medium">{{ c.name }}</td>
                        <td class="py-2">
                            <TierBadge :tier="c.tier" />
                        </td>
                        <td class="py-2 text-right font-mono">{{ c.lifetime_visits }}</td>
                        <td class="py-2 text-right font-mono font-semibold">{{ fmtCurrency(c.lifetime_spent) }}</td>
                        <td class="py-2 text-right">
                            <RouterLink :to="{ name: 'crm-customer-profile', params: { id: c.customer_id } }"
                                        class="text-xs text-indigo-600 hover:underline">{{ t('common.view') }}</RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h3 class="card-title">{{ t('crm.intelligence.quickActions') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <RouterLink :to="{ name: 'crm-segments' }" class="quick-link">
                    <UserGroupIcon class="w-5 h-5 text-indigo-500" />
                    <span>{{ t('crm.nav.segments') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'crm-wallets' }" class="quick-link">
                    <CreditCardIcon class="w-5 h-5 text-emerald-500" />
                    <span>{{ t('crm.nav.wallets') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'crm-loyalty-settings' }" class="quick-link">
                    <Cog6ToothIcon class="w-5 h-5 text-amber-500" />
                    <span>{{ t('crm.nav.loyaltySettings') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'customers' }" class="quick-link">
                    <UsersIcon class="w-5 h-5 text-slate-500" />
                    <span>{{ t('crm.nav.customers') }}</span>
                </RouterLink>
            </div>
        </section>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { customerIntelligenceService } from '@/services/crmService';
import { useCurrency } from '@/composables/useCurrency';
import {
    UsersIcon, UserPlusIcon, ClockIcon, BanknotesIcon,
    UserGroupIcon, CreditCardIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();
const d = ref(null);

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
        props.sub ? h('p', { class: 'text-[11px] mt-1 text-slate-500' }, props.sub) : null,
    ]);
};
KpiCard.props = ['label', 'value', 'tone', 'icon', 'sub'];

const TierBadge = (props) => {
    const palette = {
        silver:   'bg-slate-100 text-slate-700',
        gold:     'bg-amber-100 text-amber-800',
        platinum: 'bg-indigo-100 text-indigo-800',
        vip:      'bg-rose-100 text-rose-800',
    }[props.tier] ?? 'bg-slate-100 text-slate-700';
    return h('span', { class: `text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold ${palette}` }, props.tier);
};
TierBadge.props = ['tier'];

async function load() {
    const { data } = await customerIntelligenceService.dashboard();
    d.value = data.data;
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.quick-link  { @apply flex items-center gap-2 px-4 py-3 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-200 text-sm font-medium text-slate-700 transition-colors; }
</style>
