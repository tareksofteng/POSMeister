<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('saleReturns.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('saleReturns.subtitle') }}</p>
            </div>
        </div>

        <!-- ── Invoice search ──────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                <MagnifyingGlassIcon class="w-4 h-4 text-indigo-500" />
                {{ t('saleReturns.searchSale') }}
            </h2>
            <div class="relative flex-1 min-w-[260px] max-w-xl">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="invoiceSearch"
                    type="search"
                    :placeholder="t('saleReturns.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    @focus="showDropdown = true"
                    @blur="onSearchBlur"
                />
                <!-- Dropdown results -->
                <div v-if="showDropdown && searchResults.length"
                    class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-xl z-20 overflow-hidden max-h-60 overflow-y-auto">
                    <div
                        v-for="s in searchResults"
                        :key="s.id"
                        @mousedown.prevent="selectSale(s)"
                        class="flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 cursor-pointer border-b border-gray-50 last:border-0"
                    >
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ s.sale_number }}</p>
                            <p class="text-xs text-gray-400">{{ s.customer_name ?? t('common.walkin') }} · {{ formatDate(s.sale_date) }}</p>
                        </div>
                        <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                            {{ t('sales.statusActive') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Selected sale + return form ────────────────────────────── -->
        <template v-if="returnData">
            <!-- Sale header info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Customer card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-3">
                        {{ t('pos.customer') }}
                    </p>
                    <div v-if="returnData.sale.customer" class="space-y-0.5 text-sm">
                        <p class="font-bold text-gray-900 text-base">{{ returnData.sale.customer.name }}</p>
                        <p v-if="returnData.sale.customer.phone" class="text-gray-400 text-xs">Tel.: {{ returnData.sale.customer.phone }}</p>
                    </div>
                    <p v-else class="text-sm text-gray-500 italic">{{ t('common.walkin') }}</p>
                </div>

                <!-- Sale meta card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-3">
                        {{ t('saleReturns.saleDetails') }}
                    </p>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ t('sales.number') }}</dt>
                            <dd class="font-mono font-semibold text-gray-900">{{ returnData.sale.sale_number }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ t('sales.date') }}</dt>
                            <dd class="text-gray-700">{{ formatDate(returnData.sale.sale_date) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ t('branches.title') }}</dt>
                            <dd class="text-gray-700">{{ returnData.sale.branch_name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Items table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
                    <h3 class="text-sm font-semibold text-gray-700">{{ t('saleReturns.returnItems') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 w-8">#</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('saleReturns.product') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-24">{{ t('saleReturns.originalQty') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('saleReturns.alreadyReturned') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-24">{{ t('saleReturns.available') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('saleReturns.returnQty') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('saleReturns.unitPrice') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('saleReturns.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(item, idx) in returnData.items" :key="item.sale_item_id"
                                :class="['hover:bg-gray-50/60 transition-colors', item.return_qty > 0 ? 'bg-emerald-50/40' : '']">
                                <td class="px-4 py-2.5 text-gray-400 text-xs">{{ idx + 1 }}</td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-lg overflow-hidden bg-gray-100 border border-gray-100 flex-shrink-0">
                                            <img v-if="item.image_url" :src="item.image_url" :alt="item.product_name" class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center">
                                                <PhotoIcon class="w-4 h-4 text-gray-300" />
                                            </div>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ item.product_name }}</p>
                                            <p class="text-xs text-gray-400 font-mono">{{ item.product_sku }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ item.original_qty }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    <span v-if="item.already_returned > 0" class="text-amber-600 font-medium">{{ item.already_returned }}</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums"
                                    :class="item.available_to_return > 0 ? 'text-emerald-600' : 'text-red-400'">
                                    {{ item.available_to_return }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <input
                                        type="number"
                                        min="0"
                                        :max="item.available_to_return"
                                        step="0.01"
                                        v-model.number="item.return_qty"
                                        @input="recalcItem(item)"
                                        class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 tabular-nums"
                                        :class="item.return_qty > item.available_to_return ? 'border-red-400 bg-red-50' : ''"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model.number="item.unit_price"
                                        @input="recalcItem(item)"
                                        class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 tabular-nums"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right font-bold text-gray-900 tabular-nums">
                                    {{ formatCurrency(item.line_total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer: total + date + note + save -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-col lg:flex-row gap-6 items-start">
                    <!-- Left: date + note -->
                    <div class="flex-1 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                {{ t('saleReturns.returnDate') }}
                            </label>
                            <input v-model="form.return_date" type="date"
                                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                {{ t('saleReturns.note') }}
                            </label>
                            <textarea v-model="form.note" rows="2"
                                :placeholder="t('saleReturns.notePlaceholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>
                    </div>

                    <!-- Right: total + save -->
                    <div class="flex-shrink-0 text-right space-y-4 min-w-[220px]">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-6 py-4">
                            <p class="text-xs text-emerald-600 font-semibold uppercase tracking-wider">{{ t('saleReturns.totalReturn') }}</p>
                            <p class="text-2xl font-bold text-emerald-700 tabular-nums mt-1">{{ formatCurrency(grandTotal) }}</p>
                            <p class="text-xs text-emerald-500 mt-1">+ {{ t('saleReturns.stockRestored') }}</p>
                        </div>
                        <div v-if="saveError" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2 text-left">{{ saveError }}</div>
                        <button
                            @click="saveReturn"
                            :disabled="saving || grandTotal === 0"
                            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors shadow-md"
                        >
                            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <ArrowUturnRightIcon v-else class="w-4 h-4" />
                            {{ saving ? t('common.saving') : t('saleReturns.saveReturn') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Recent returns list ─────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/80 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">{{ t('saleReturns.recentReturns') }}</h3>
                <span v-if="listMeta" class="text-xs text-gray-400">{{ listMeta.total }} {{ t('common.total') }}</span>
            </div>
            <div v-if="listLoading" class="flex items-center justify-center py-10">
                <div class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
            </div>
            <div v-else-if="!returns.length" class="text-center py-10">
                <ArrowUturnRightIcon class="w-10 h-10 text-gray-200 mx-auto mb-2" />
                <p class="text-sm text-gray-400">{{ t('saleReturns.noReturns') }}</p>
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('saleReturns.returnNumber') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('saleReturns.returnDate') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('pos.customer') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('sales.number') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">{{ t('saleReturns.amount') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">{{ t('saleReturns.positions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in returns" :key="r.id" class="hover:bg-emerald-50/30 transition-colors">
                        <td class="px-4 py-2.5 font-mono font-semibold text-emerald-700">{{ r.return_number }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ formatDate(r.return_date) }}</td>
                        <td class="px-4 py-2.5 text-gray-700">{{ r.customer?.name ?? t('common.walkin') }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ r.sale?.sale_number ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-900 tabular-nums">{{ formatCurrency(r.total_amount) }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-500">{{ r.items_count ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { useAlert } from '@/composables/useAlert';
import { saleService } from '@/services/saleService';
import { refDebounced } from '@vueuse/core';
import { MagnifyingGlassIcon, PhotoIcon, ArrowUturnRightIcon } from '@heroicons/vue/24/outline';

const { t }       = useI18n();
const { toast }   = useAlert();
const settingsStore = useSettingsStore();

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value);
}
function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });
}

// ── Invoice search ────────────────────────────────────────────────────────
const invoiceSearch  = ref('');
const searchResults  = ref([]);
const showDropdown   = ref(false);
const debouncedQuery = refDebounced(invoiceSearch, 300);

watch(debouncedQuery, async (q) => {
    if (!q.trim()) { searchResults.value = []; return; }
    try {
        const { data } = await saleService.index({ search: q, status: 'active', per_page: 10 });
        searchResults.value = data.data ?? [];
    } catch {
        searchResults.value = [];
    }
});

function onSearchBlur() {
    setTimeout(() => { showDropdown.value = false; }, 200);
}

// ── Return form state ─────────────────────────────────────────────────────
const returnData = ref(null);
const form       = ref({ return_date: new Date().toISOString().substring(0, 10), note: '' });
const saving     = ref(false);
const saveError  = ref('');

const grandTotal = computed(() =>
    (returnData.value?.items ?? []).reduce((s, i) => s + (i.line_total ?? 0), 0)
);

async function selectSale(sale) {
    showDropdown.value  = false;
    invoiceSearch.value = sale.sale_number;
    returnData.value    = null;
    saveError.value     = '';
    try {
        const { data } = await saleService.returnDetails(sale.id);
        returnData.value = data;
        if (!data.items.length) {
            toast('warning', t('saleReturns.allReturned'));
        }
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

function recalcItem(item) {
    const qty   = Math.max(0, item.return_qty  ?? 0);
    const price = Math.max(0, item.unit_price ?? 0);
    if (qty > item.available_to_return) {
        item.return_qty = item.available_to_return;
    }
    item.line_total = Math.round(Math.max(0, item.return_qty ?? 0) * price * 100) / 100;
}

async function saveReturn() {
    saveError.value = '';
    saving.value    = true;
    try {
        await saleService.storeReturn({
            sale_id:     returnData.value.sale.id,
            return_date: form.value.return_date,
            note:        form.value.note || null,
            items: returnData.value.items.map(i => ({
                sale_item_id: i.sale_item_id,
                product_id:   i.product_id,
                return_qty:   i.return_qty ?? 0,
                unit_price:   i.unit_price ?? 0,
            })),
        });
        toast('success', t('saleReturns.savedSuccess'));
        returnData.value    = null;
        invoiceSearch.value = '';
        form.value.note     = '';
        loadReturns();
    } catch (err) {
        saveError.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        saving.value = false;
    }
}

// ── Recent returns list ───────────────────────────────────────────────────
const returns     = ref([]);
const listMeta    = ref(null);
const listLoading = ref(false);

async function loadReturns() {
    listLoading.value = true;
    try {
        const { data } = await saleService.indexReturns({ per_page: 15 });
        returns.value  = data.data ?? [];
        listMeta.value = data.meta ?? null;
    } catch { /* non-critical */ }
    finally { listLoading.value = false; }
}

onMounted(loadReturns);
</script>

<style scoped>
@reference '../../../css/app.css';
</style>
