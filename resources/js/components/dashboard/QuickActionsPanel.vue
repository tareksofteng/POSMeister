<template>
    <!--
        Quick actions panel. The shop floor needs a single tap to open POS,
        log a purchase or take a payment — so the actions live above the
        fold on the dashboard, sorted by frequency. Each card carries a
        tone-matched icon and a one-line hint so a new cashier never has
        to guess what a button does.
    -->
    <section class="card quick-actions">
        <header class="qa-head">
            <p class="t-overline">{{ t('dashboard.quickAccess.title') }}</p>
            <p class="t-caption mt-0.5">{{ t('dashboard.quickAccess.subtitle', 'Frequent operations, one tap away.') }}</p>
        </header>

        <div class="qa-grid">
            <RouterLink
                v-for="a in actions"
                :key="a.label"
                :to="a.to"
                :class="['qa-card', `qa-tone-${a.tone}`]"
            >
                <span :class="['qa-icon-disc', `qa-icon-${a.tone}`]">
                    <component :is="a.icon" class="w-5 h-5" />
                </span>
                <div class="qa-body">
                    <p class="qa-label">{{ a.label }}</p>
                    <p class="qa-desc">{{ a.desc }}</p>
                </div>
                <ArrowLongRightIcon class="qa-chevron" />
            </RouterLink>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import {
    ArrowLongRightIcon, BanknotesIcon, BookOpenIcon, ClipboardDocumentListIcon,
    CurrencyDollarIcon, ShoppingCartIcon, TagIcon, TruckIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();

const actions = computed(() => [
    {
        label: t('dashboard.quickAccess.openPOS'),
        desc:  t('dashboard.quickAccess.openPOSDesc'),
        to:    { name: 'pos' },
        icon:  ShoppingCartIcon,
        tone:  'emerald',
    },
    {
        label: t('dashboard.quickAccess.newSale'),
        desc:  t('dashboard.quickAccess.newSaleDesc'),
        to:    { name: 'sales-new' },
        icon:  TagIcon,
        tone:  'indigo',
    },
    {
        label: t('dashboard.quickAccess.newPurchase'),
        desc:  t('dashboard.quickAccess.newPurchaseDesc'),
        to:    { name: 'purchase-create' },
        icon:  TruckIcon,
        tone:  'violet',
    },
    {
        label: t('dashboard.quickAccess.accounting'),
        desc:  t('dashboard.quickAccess.accountingDesc'),
        to:    { name: 'accounting-dashboard' },
        icon:  BookOpenIcon,
        tone:  'amber',
    },
    {
        label: t('dashboard.quickAccess.reorder'),
        desc:  t('dashboard.quickAccess.reorderDesc'),
        to:    { name: 'inventory-reorder' },
        icon:  ClipboardDocumentListIcon,
        tone:  'rose',
    },
]);
</script>

<style scoped>
@reference '../../../css/app.css';

.quick-actions {
    padding: 1rem 1.125rem 1.125rem;
}
.qa-head { margin-bottom: 0.875rem; }

.qa-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.qa-card {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 0.875rem;
    border-radius: 0.75rem;
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
    transition:
        background-color var(--motion-fast) var(--motion-out),
        border-color     var(--motion-fast) var(--motion-out),
        transform        var(--motion-fast) var(--motion-out),
        box-shadow       var(--motion-fast) var(--motion-out);
}
.qa-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--elev-2);
    border-color: var(--border-strong);
}
.qa-card:active { transform: translateY(0); }

.qa-icon-disc {
    width: 38px; height: 38px;
    border-radius: 0.625rem;
    display: grid; place-items: center;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
    transition: transform var(--motion-fast) var(--motion-spring);
}
.qa-card:hover .qa-icon-disc { transform: scale(1.05); }

.qa-icon-emerald { background: linear-gradient(135deg, rgb(209 250 229), rgb(167 243 208)); color: rgb(4 120 87); }
.qa-icon-indigo  { background: linear-gradient(135deg, rgb(224 231 255), rgb(199 210 254)); color: rgb(67 56 202); }
.qa-icon-violet  { background: linear-gradient(135deg, rgb(237 233 254), rgb(221 214 254)); color: rgb(109 40 217); }
.qa-icon-amber   { background: linear-gradient(135deg, rgb(254 243 199), rgb(253 230 138)); color: rgb(146 64 14); }
.qa-icon-rose    { background: linear-gradient(135deg, rgb(255 228 230), rgb(254 205 211)); color: rgb(190 18 60); }

html.dark .qa-icon-emerald { background: linear-gradient(135deg, rgb(6 95 70 / 0.4), rgb(6 78 59 / 0.4)); color: rgb(110 231 183); }
html.dark .qa-icon-indigo  { background: linear-gradient(135deg, rgb(67 56 202 / 0.4), rgb(55 48 163 / 0.4)); color: rgb(165 180 252); }
html.dark .qa-icon-violet  { background: linear-gradient(135deg, rgb(109 40 217 / 0.4), rgb(91 33 182 / 0.4)); color: rgb(196 181 253); }
html.dark .qa-icon-amber   { background: linear-gradient(135deg, rgb(146 64 14 / 0.4), rgb(120 53 15 / 0.4)); color: rgb(252 211 77); }
html.dark .qa-icon-rose    { background: linear-gradient(135deg, rgb(159 18 57 / 0.4), rgb(136 19 55 / 0.4)); color: rgb(253 164 175); }

.qa-body { min-width: 0; }
.qa-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
}
.qa-desc {
    font-size: 0.75rem;
    color: var(--text-tertiary);
    margin-top: 0.125rem;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.qa-chevron {
    width: 16px; height: 16px;
    color: var(--text-tertiary);
    transition: transform var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
    flex-shrink: 0;
}
.qa-card:hover .qa-chevron {
    color: rgb(67 56 202);
    transform: translateX(2px);
}
html.dark .qa-card:hover .qa-chevron { color: rgb(165 180 252); }

/* RTL — flip the chevron + slide direction for Arabic. */
:dir(rtl) .qa-chevron { transform: scaleX(-1); }
:dir(rtl) .qa-card:hover .qa-chevron { transform: scaleX(-1) translateX(2px); }
</style>
