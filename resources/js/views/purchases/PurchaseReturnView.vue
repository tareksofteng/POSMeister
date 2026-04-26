<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('purchaseReturns.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('purchaseReturns.subtitle') }}</p>
            </div>
        </div>

        <!-- ── Invoice search ──────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                <MagnifyingGlassIcon class="w-4 h-4 text-indigo-500" />
                {{ t('purchaseReturns.searchPurchase') }}
            </h2>
            <div class="flex gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[260px]">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                    <input
                        v-model="invoiceSearch"
                        type="search"
                        :placeholder="t('purchaseReturns.searchPlaceholder')"
                        class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @focus="showDropdown = true"
                        @blur="onSearchBlur"
                    />
                    <!-- Dropdown results -->
                    <div v-if="showDropdown && searchResults.length"
                        class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-xl z-20 overflow-hidden max-h-60 overflow-y-auto">
                        <div
                            v-for="p in searchResults"
                            :key="p.id"
                            @mousedown.prevent="selectPurchase(p)"
                            class="flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 cursor-pointer border-b border-gray-50 last:border-0"
                        >
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ p.purchase_number }}</p>
                                <p class="text-xs text-gray-400">{{ p.supplier?.name ?? '—' }} · {{ formatDate(p.purchase_date) }}</p>
                            </div>
                            <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                {{ t('purchases.statusReceived') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Selected purchase + return form ────────────────────────── -->
        <template v-if="returnData">
            <!-- Purchase header info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Supplier card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-3">
                        {{ t('suppliers.title') }}
                    </p>
                    <div v-if="returnData.purchase.supplier" class="space-y-0.5 text-sm">
                        <p class="font-bold text-gray-900 text-base">{{ returnData.purchase.supplier.name }}</p>
                        <p v-if="returnData.purchase.supplier.address" class="text-gray-500 text-xs">{{ returnData.purchase.supplier.address }}</p>
                        <p v-if="returnData.purchase.supplier.phone" class="text-gray-400 text-xs">Tel.: {{ returnData.purchase.supplier.phone }}</p>
                    </div>
                    <p v-else class="text-sm text-gray-400 italic">—</p>
                </div>

                <!-- Purchase meta card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-3">
                        {{ t('purchaseReturns.purchaseDetails') }}
                    </p>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ t('purchases.invoiceNumber') }}</dt>
                            <dd class="font-mono font-semibold text-gray-900">{{ returnData.purchase.purchase_number }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ t('purchases.date') }}</dt>
                            <dd class="text-gray-700">{{ formatDate(returnData.purchase.purchase_date) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ t('branches.title') }}</dt>
                            <dd class="text-gray-700">{{ returnData.purchase.branch_name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Items table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
                    <h3 class="text-sm font-semibold text-gray-700">{{ t('purchaseReturns.returnItems') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 w-8">#</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('purchaseReturns.product') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-24">{{ t('purchaseReturns.originalQty') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('purchaseReturns.alreadyReturned') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-24">{{ t('purchaseReturns.available') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('purchaseReturns.returnQty') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('purchaseReturns.unitCost') }}</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 w-28">{{ t('purchaseReturns.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(item, idx) in returnData.items" :key="item.purchase_item_id"
                                :class="['hover:bg-gray-50/60 transition-colors', item.return_qty > 0 ? 'bg-amber-50/40' : '']">
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
                                        class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 tabular-nums"
                                        :class="item.return_qty > item.available_to_return ? 'border-red-400 bg-red-50' : ''"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model.number="item.unit_cost"
                                        @input="recalcItem(item)"
                                        class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 tabular-nums"
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
                                {{ t('purchaseReturns.returnDate') }}
                            </label>
                            <input v-model="form.return_date" type="date"
                                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                {{ t('purchaseReturns.note') }}
                            </label>
                            <textarea v-model="form.note" rows="2"
                                :placeholder="t('purchaseReturns.notePlaceholder')"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>
                    </div>

                    <!-- Right: total + save -->
                    <div class="flex-shrink-0 text-right space-y-4 min-w-[220px]">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl px-6 py-4">
                            <p class="text-xs text-amber-600 font-semibold uppercase tracking-wider">{{ t('purchaseReturns.totalReturn') }}</p>
                            <p class="text-2xl font-bold text-amber-700 tabular-nums mt-1">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                        <div v-if="saveError" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2 text-left">{{ saveError }}</div>
                        <button
                            @click="saveReturn"
                            :disabled="saving || grandTotal === 0"
                            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors shadow-md"
                        >
                            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <ArrowUturnLeftIcon v-else class="w-4 h-4" />
                            {{ saving ? t('common.saving') : t('purchaseReturns.saveReturn') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Recent returns list ─────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/80 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">{{ t('purchaseReturns.recentReturns') }}</h3>
                <span v-if="listMeta" class="text-xs text-gray-400">{{ listMeta.total }} {{ t('common.total') }}</span>
            </div>
            <div v-if="listLoading" class="flex items-center justify-center py-10">
                <div class="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
            </div>
            <div v-else-if="!returns.length" class="text-center py-10">
                <ArrowUturnLeftIcon class="w-10 h-10 text-gray-200 mx-auto mb-2" />
                <p class="text-sm text-gray-400">{{ t('purchaseReturns.noReturns') }}</p>
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('purchaseReturns.returnNumber') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('purchaseReturns.returnDate') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('suppliers.title') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">{{ t('purchases.invoiceNumber') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">{{ t('purchaseReturns.amount') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">{{ t('purchaseReturns.positions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in returns" :key="r.id" class="hover:bg-amber-50/30 transition-colors">
                        <td class="px-4 py-2.5 font-mono font-semibold text-amber-700">{{ r.return_number }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ formatDate(r.return_date) }}</td>
                        <td class="px-4 py-2.5 text-gray-700">{{ r.supplier?.name ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ r.purchase?.purchase_number ?? '—' }}</td>
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
import { purchaseService } from '@/services/purchaseService';
import { useDebounce } from '@vueuse/core';
import { MagnifyingGlassIcon, PhotoIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';

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
const debouncedQuery = useDebounce(invoiceSearch, 300);

watch(debouncedQuery, async (q) => {
    if (!q.trim()) { searchResults.value = []; return; }
    try {
        const { data } = await purchaseService.index({ search: q, status: 'received', per_page: 10 });
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

async function selectPurchase(purchase) {
    showDropdown.value  = false;
    invoiceSearch.value = purchase.purchase_number;
    returnData.value    = null;
    saveError.value     = '';
    try {
        const { data } = await purchaseService.returnDetails(purchase.id);
        returnData.value = data;
        if (!data.items.length) {
            toast('warning', t('purchaseReturns.allReturned'));
        }
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

function recalcItem(item) {
    const qty  = Math.max(0, item.return_qty  ?? 0);
    const cost = Math.max(0, item.unit_cost ?? 0);
    if (qty > item.available_to_return) {
        item.return_qty = item.available_to_return;
    }
    item.line_total = Math.round(Math.max(0, item.return_qty ?? 0) * cost * 100) / 100;
}

async function saveReturn() {
    saveError.value = '';
    saving.value    = true;
    try {
        await purchaseService.storeReturn({
            purchase_id: returnData.value.purchase.id,
            return_date: form.value.return_date,
            note:        form.value.note || null,
            items: returnData.value.items.map(i => ({
                purchase_item_id: i.purchase_item_id,
                product_id:       i.product_id,
                return_qty:       i.return_qty ?? 0,
                unit_cost:        i.unit_cost  ?? 0,
            })),
        });
        toast('success', t('purchaseReturns.savedSuccess'));
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
        const { data } = await purchaseService.indexReturns({ per_page: 15 });
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
