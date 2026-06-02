<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">
        <div v-if="!profile" class="text-center text-slate-400 py-12 text-sm">{{ t('common.loading') }}</div>

        <template v-else>
            <header class="card flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-900">{{ profile.customer.name }}</h1>
                        <TierBadge v-if="loyalty" :tier="loyalty.tier" />
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        <span v-if="profile.customer.phone">{{ profile.customer.phone }}</span>
                        <span v-if="profile.customer.email"> · {{ profile.customer.email }}</span>
                        <span v-if="profile.customer.date_of_birth"> · {{ t('crm.fields.dob') }}: {{ formatDate(profile.customer.date_of_birth) }}</span>
                    </p>
                </div>
                <div v-if="loyalty" class="grid grid-cols-3 gap-2 text-right">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('crm.loyalty.balance') }}</p>
                        <p class="text-lg font-bold font-mono text-indigo-700">{{ loyalty.current_points }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('crm.loyalty.value') }}</p>
                        <p class="text-lg font-bold font-mono text-emerald-700">{{ fmtCurrency(loyalty.redeemable_value) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('crm.wallet.balance') }}</p>
                        <p class="text-lg font-bold font-mono" :class="(wallet?.balance ?? 0) >= 0 ? 'text-slate-900' : 'text-rose-700'">
                            {{ fmtCurrency(wallet?.balance ?? 0) }}
                        </p>
                    </div>
                </div>
            </header>

            <section v-if="loyalty?.next_tier" class="card">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="card-title mb-0">{{ t('crm.loyalty.tierProgress') }}</h3>
                    <p class="text-xs text-slate-500">
                        {{ t('crm.loyalty.toNext', { tier: loyalty.next_tier.name, amount: fmtCurrency(loyalty.next_tier.remaining_spend) }) }}
                    </p>
                </div>
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-full"
                         :style="{ width: loyalty.next_tier.progress_percent + '%' }"></div>
                </div>
            </section>

            <section v-if="profile.behavior" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <SumBox :label="t('crm.behavior.visits')"      :value="String(profile.behavior.visits)" />
                <SumBox :label="t('crm.behavior.avgBasket')"   :value="fmtCurrency(profile.behavior.avg_basket)" tone="indigo" />
                <SumBox :label="t('crm.behavior.lifetimeRev')" :value="fmtCurrency(profile.behavior.lifetime_revenue)" tone="emerald" />
                <SumBox :label="t('crm.behavior.visitsPerMonth')" :value="String(profile.behavior.visits_per_month ?? '—')" />
            </section>

            <section v-if="profile.behavior?.favourite_products?.length" class="card">
                <h3 class="card-title">{{ t('crm.behavior.favourites') }}</h3>
                <p class="text-xs text-slate-600 mb-3">
                    <span class="font-medium">{{ t('crm.behavior.category') }}:</span>
                    {{ profile.behavior.favourite_category ?? '—' }}
                </p>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="p in profile.behavior.favourite_products" :key="p.product_id">
                            <td class="py-1.5 text-slate-800">
                                {{ p.name }}
                                <span class="text-[11px] text-slate-500 font-mono ml-1">({{ p.sku }})</span>
                            </td>
                            <td class="py-1.5 text-right font-mono">{{ p.qty }}</td>
                            <td class="py-1.5 text-right font-mono">{{ fmtCurrency(p.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('crm.profile.timeline') }}</h3>
                <ol class="space-y-2.5">
                    <li v-for="(e, i) in profile.events.slice(0, 50)" :key="i"
                        class="flex items-start gap-3 text-sm py-1.5 border-b border-slate-50 last:border-0">
                        <span :class="['mt-1.5 w-2 h-2 rounded-full flex-shrink-0', kindDot(e.kind)]"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-medium text-slate-800 truncate">{{ e.title }}</p>
                                <p class="font-mono text-xs whitespace-nowrap"
                                   :class="(e.amount ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ e.amount !== undefined ? (e.amount >= 0 ? '+' : '') + (e.kind === 'loyalty' ? e.amount : fmtCurrency(e.amount)) : '' }}
                                </p>
                            </div>
                            <p class="text-[11px] text-slate-500">
                                {{ formatDate(e.at) }}
                                <span v-if="e.meta?.note"> · {{ e.meta.note }}</span>
                            </p>
                        </div>
                    </li>
                </ol>
                <p v-if="profile.events.length === 0" class="text-center text-sm text-slate-400 py-6">
                    {{ t('crm.profile.empty') }}
                </p>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, h, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { customerIntelligenceService, loyaltyService, walletService } from '@/services/crmService';
import { useCurrency } from '@/composables/useCurrency';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const route = useRoute();

const profile = ref(null);
const loyalty = ref(null);
const wallet  = ref(null);

function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

function kindDot(kind) {
    return {
        sale:    'bg-emerald-500',
        return:  'bg-rose-500',
        payment: 'bg-indigo-500',
        loyalty: 'bg-amber-500',
        wallet:  'bg-slate-500',
    }[kind] ?? 'bg-slate-300';
}

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

const SumBox = (props) => {
    const palette = {
        emerald: 'border-emerald-200 bg-emerald-50/40 text-emerald-700',
        indigo:  'border-indigo-200 bg-indigo-50/40 text-indigo-700',
    }[props.tone] ?? 'border-slate-200 bg-white text-slate-900';
    return h('div', { class: `border rounded-xl shadow-sm px-4 py-3 ${palette}` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide opacity-75 font-medium' }, props.label),
        h('p', { class: 'text-xl font-bold mt-1 font-mono' }, String(props.value)),
    ]);
};
SumBox.props = ['label', 'value', 'tone'];

async function load() {
    const id = route.params.id;
    if (!id) return;
    const [{ data: prof }, { data: ly }, { data: wt }] = await Promise.all([
        customerIntelligenceService.profile(id),
        loyaltyService.summary(id),
        walletService.summary(id),
    ]);
    profile.value = prof.data;
    loyalty.value = ly.data;
    wallet.value  = wt.data;
}

watch(() => route.params.id, load);
onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
</style>
