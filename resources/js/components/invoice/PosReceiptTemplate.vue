<template>
    <!--
        80 mm / 58 mm thermal receipt. Built for actual cashier flow, not
        for screen aesthetics — small monospace type, dashed dividers,
        every block separated by a faint line. The header / footer
        blocks mirror what a typical Bangladeshi POS receipt looks like
        (Bill To / Bill By on one row, Order No prominent, Visit Again
        Us, software credit) so cashiers recognise the format instantly.
    -->
    <div :class="['pos-paper', `pos-${format}`]">

        <header class="pos-head">
            <img v-if="settings.logo_url" :src="settings.logo_url" alt="" class="pos-logo" />
            <p class="pos-business">{{ settings.company_name || '—' }}</p>
            <p v-if="settings.address" class="pos-line">{{ settings.address }}</p>
            <p v-if="settings.phone"   class="pos-line"><strong>{{ t('invoice.mobile', 'Mobile') }}:</strong> {{ settings.phone }}</p>
            <p v-if="settings.email"   class="pos-line">{{ settings.email }}</p>
        </header>

        <hr class="pos-rule">

        <!-- Receipt meta — label on left, value right after. Date and
             time are intentionally on separate lines, matches how a
             cashier reads the slip. -->
        <section class="pos-meta">
            <p class="pos-line pos-strong">{{ kindLabel }}</p>
            <p class="pos-line"><strong>{{ t('invoice.date', 'Date') }}:</strong> {{ doc.date }}</p>
            <p class="pos-line"><strong>{{ t('invoice.time', 'Time') }}:</strong> {{ doc.time }}</p>
        </section>

        <hr class="pos-rule">

        <!-- Items table — name on the left, line total flush right. The
             qty × rate sub-line sits underneath so the column doesn't
             have to grow on narrow paper. -->
        <section class="pos-items">
            <div class="pos-row pos-row-head">
                <span class="pos-col-name">{{ t('invoice.item', 'Item') }}</span>
                <span class="pos-col-total">{{ t('invoice.total', 'Total') }}</span>
            </div>

            <template v-for="(item, i) in doc.items" :key="i">
                <div class="pos-row">
                    <span class="pos-col-name pos-strong">{{ item.name }}</span>
                    <span class="pos-col-total">
                        {{ fmt(item.unit_price) }}
                        <template v-if="item.quantity != null"> × {{ item.quantity }}</template>
                    </span>
                </div>
                <div class="pos-row pos-row-sub">
                    <span class="pos-col-name" />
                    <span class="pos-col-total pos-strong">{{ fmt(item.line_total) }}</span>
                </div>
            </template>
        </section>

        <hr class="pos-rule">

        <!-- Totals stack. Discount / VAT only render when they actually
             have a value — keeps the receipt short for cash-only sales. -->
        <section class="pos-totals">
            <div class="pos-row pos-strong">
                <span class="pos-col-name">{{ t('invoice.subtotal', 'Subtotal') }}</span>
                <span class="pos-col-total">{{ fmt(doc.subtotal) }}</span>
            </div>
            <div v-if="doc.discount" class="pos-row">
                <span class="pos-col-name">{{ t('invoice.discount', 'Discount') }}</span>
                <span class="pos-col-total">−{{ fmt(doc.discount) }}</span>
            </div>
            <div v-if="doc.vat" class="pos-row">
                <span class="pos-col-name">{{ t('invoice.vat', 'VAT') }}</span>
                <span class="pos-col-total">{{ fmt(doc.vat) }}</span>
            </div>

            <div class="pos-row pos-row-grand">
                <span class="pos-col-name">{{ t('invoice.grandTotal', 'Grand Total') }}</span>
                <span class="pos-col-total">{{ fmt(doc.grand_total) }}</span>
            </div>

            <template v-if="doc.cash_paid != null || doc.card_paid != null">
                <div v-if="doc.cash_paid != null" class="pos-row">
                    <span class="pos-col-name">{{ t('invoice.cashPaid', 'Cash Paid') }}</span>
                    <span class="pos-col-total">{{ fmt(doc.cash_paid) }}</span>
                </div>
                <div v-if="doc.card_paid != null && doc.card_paid > 0" class="pos-row">
                    <span class="pos-col-name">{{ t('invoice.cardPaid', 'Card Paid') }}</span>
                    <span class="pos-col-total">{{ fmt(doc.card_paid) }}</span>
                </div>
                <div v-if="doc.change_due != null && doc.change_due > 0" class="pos-row">
                    <span class="pos-col-name">{{ t('invoice.changeDue', 'Change') }}</span>
                    <span class="pos-col-total">{{ fmt(doc.change_due) }}</span>
                </div>
                <div v-if="doc.due_amount != null && doc.due_amount > 0" class="pos-row pos-strong">
                    <span class="pos-col-name">{{ t('invoice.dueAmount', 'Due') }}</span>
                    <span class="pos-col-total">{{ fmt(doc.due_amount) }}</span>
                </div>
            </template>
        </section>

        <hr class="pos-rule">

        <!-- Operational block — who the receipt is for, who issued it,
             the receipt number itself (prominent so it survives a
             return lookup) and the payment status badge. -->
        <section class="pos-ops">
            <div class="pos-row">
                <span class="pos-col-name">
                    <strong>{{ t('invoice.billTo', 'Bill To') }}:</strong> {{ doc.counterparty_name }}
                </span>
                <span v-if="doc.cashier_name" class="pos-col-total">
                    <strong>{{ t('invoice.billBy', 'Bill By') }}:</strong> {{ doc.cashier_name }}
                </span>
            </div>
            <p class="pos-line pos-center pos-strong pos-receipt-no">
                {{ t('invoice.receiptNo', 'Order No.') }}: {{ doc.number }}
            </p>
            <p class="pos-line pos-center pos-strong">
                {{ t('invoice.paymentStatus', 'Payment Status') }}: {{ paymentStatusLabel }}
            </p>
        </section>

        <hr class="pos-rule">

        <!-- Footer — the "thank you" line first because that's what the
             customer reads first, then the operator-defined footer
             (bank info / tax id), then the software credit. -->
        <footer class="pos-foot">
            <p class="pos-line pos-center pos-strong">
                {{ t('invoice.visitAgain', 'Visit Again') }}
            </p>
            <p v-if="settings.invoice_footer" class="pos-line pos-center pos-tiny">{{ settings.invoice_footer }}</p>
            <p class="pos-line pos-center pos-tiny pos-muted">
                {{ t('invoice.softwareBy', 'Software by') }}: POSmeister
            </p>
            <p class="pos-line pos-center pos-tiny pos-muted">{{ doc.printed_at }}</p>
        </footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';

const props = defineProps({
    /**
     * Flattened invoice payload — the parent view (sale / purchase /
     * return) normalises its domain object into this shape so the
     * template stays domain-agnostic.
     *
     * {
     *   number, date, time, kind,
     *   counterparty_label, counterparty_name, cashier_name, branch_name,
     *   subtotal, discount, vat, grand_total,
     *   cash_paid, card_paid, change_due, due_amount,
     *   items: [{name, quantity, unit, unit_price, line_total}],
     *   printed_at,
     * }
     */
    doc:    { type: Object, required: true },
    format: { type: String, default: 'pos80' },   // pos80 | pos58
});

const { t } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.settings || {});

const kindLabel = computed(() => ({
    sale:             t('invoice.kind.sale',             'SALES RECEIPT'),
    purchase:         t('invoice.kind.purchase',         'PURCHASE INVOICE'),
    sale_return:      t('invoice.kind.saleReturn',       'SALE RETURN'),
    purchase_return:  t('invoice.kind.purchaseReturn',   'PURCHASE RETURN'),
})[props.doc.kind] || t('invoice.kind.sale', 'SALES RECEIPT'));

// "Paid" if the whole grand total is covered, otherwise "Due". Keeping
// it boolean-ish so the cashier can see at a glance whether to chase.
const paymentStatusLabel = computed(() => {
    const paid = Number(props.doc.cash_paid ?? 0) + Number(props.doc.card_paid ?? 0);
    const total = Number(props.doc.grand_total ?? 0);
    return paid >= total
        ? t('invoice.paid', 'Paid')
        : t('invoice.unpaid', 'Due');
});

function fmt(value) {
    if (value == null) return '';
    const code = settings.value?.currency_code ?? 'EUR';
    try {
        return new Intl.NumberFormat(intlLocale.value || 'en-US', {
            style: 'currency',
            currency: code,
            maximumFractionDigits: 2,
        }).format(Number(value) || 0);
    } catch {
        const symbol = settings.value?.currency_symbol ?? code;
        return `${symbol} ${(Number(value) || 0).toFixed(2)}`;
    }
}
</script>

<style scoped>
/*
 * Thermal heads can't render fine type — everything is monospace, tight
 * line-height, and the dashes-as-rules trick instead of borders so the
 * print survives low-DPI paper. 80 mm and 58 mm share the same template
 * with just a width + base font-size knob.
 */
.pos-paper {
    margin: 0 auto;
    padding: 6px 4px;
    background: #fff;
    color: #000;
    font-family: ui-monospace, 'SF Mono', Menlo, 'Liberation Mono', Consolas, monospace;
    font-size: 11px;
    line-height: 1.3;
}
.pos-pos80 { width: 80mm; }
.pos-pos58 { width: 58mm; font-size: 10px; }

.pos-rule {
    border: none;
    border-top: 1px dashed #000;
    margin: 3px 0;
    height: 0;
}

.pos-line {
    margin: 0;
    word-break: break-word;
}
.pos-center  { text-align: center; }
.pos-muted   { color: #555; }
.pos-tiny    { font-size: 9px; }
.pos-strong  { font-weight: 700; }

.pos-head {
    text-align: center;
}
.pos-logo {
    max-width: 40mm;
    max-height: 12mm;
    object-fit: contain;
    margin: 0 auto 2px;
    display: block;
}
.pos-business {
    font-weight: 800;
    font-size: 13px;
    margin: 0;
    letter-spacing: 0.3px;
}

.pos-meta .pos-line { margin: 0; }
.pos-meta .pos-strong { letter-spacing: 0.4px; }

.pos-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 6px;
}
.pos-row-head {
    font-weight: 700;
    border-bottom: 1px dashed #000;
    padding-bottom: 1px;
    margin-bottom: 2px;
}
.pos-row-sub .pos-col-total {
    font-weight: 700;
}
.pos-row-grand {
    font-weight: 800;
    font-size: 12.5px;
    border-top: 1px solid #000;
    border-bottom: 1px solid #000;
    padding: 2px 0;
    margin: 2px 0;
}

.pos-col-name {
    flex: 1 1 auto;
    min-width: 0;
    word-break: break-word;
}
.pos-col-total {
    flex: 0 0 auto;
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.pos-receipt-no {
    font-size: 12px;
    margin-top: 2px;
}

.pos-foot { margin-top: 2px; }
.pos-foot .pos-line { margin: 0; }
</style>
