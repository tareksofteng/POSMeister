<template>
    <div class="flex flex-col h-screen bg-slate-100 overflow-hidden">

        <!-- ── Top bar ──────────────────────────────────────────────────── -->
        <header class="h-14 bg-slate-900 flex items-center px-4 gap-4 flex-shrink-0 shadow-lg z-10">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center flex-shrink-0">
                    <ShoppingCartIcon class="w-4 h-4 text-white" />
                </div>
                <span class="text-white font-bold text-sm tracking-tight hidden sm:block">POSmeister</span>
            </div>

            <div class="h-6 w-px bg-slate-700 hidden sm:block" />

            <!-- Invoice number -->
            <span class="text-indigo-300 font-mono text-sm font-bold tracking-wide">{{ form.sale_number }}</span>

            <div class="flex-1" />

            <!-- Date -->
            <span class="text-slate-400 text-xs hidden md:block">{{ todayFormatted }}</span>

            <!-- New sale -->
            <button @click="resetSale" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold rounded-lg transition-colors">
                <PlusIcon class="w-3.5 h-3.5" />
                {{ t('pos.newSale') }}
            </button>

            <!-- Go to sales list -->
            <RouterLink :to="{ name: 'sales' }" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold rounded-lg transition-colors">
                <ListBulletIcon class="w-3.5 h-3.5" />
                {{ t('pos.salesList') }}
            </RouterLink>
        </header>

        <!-- ── Main area ─────────────────────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden">

            <!-- LEFT: product search + cart ──────────────────────────────── -->
            <div class="flex flex-col flex-1 overflow-hidden p-4 gap-3">

                <!-- Search bar -->
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="search"
                        :placeholder="t('pos.searchPlaceholder')"
                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        autocomplete="off"
                        @keydown.escape="searchQuery = ''; searchResults = []"
                        @keydown.enter.prevent="handleSearchEnter"
                    />
                    <!-- Search results dropdown -->
                    <div
                        v-if="searchResults.length"
                        class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-xl z-20 overflow-hidden"
                    >
                        <div
                            v-for="p in searchResults"
                            :key="p.id"
                            @click="addToCart(p)"
                            class="flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50 cursor-pointer transition-colors border-b border-gray-50 last:border-0"
                        >
                            <div class="w-9 h-9 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <PhotoIcon class="w-4 h-4 text-gray-300" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ p.name }}</p>
                                <p class="text-xs text-gray-400">{{ p.sku }} · {{ p.unit_symbol || p.unit_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-sm font-bold text-indigo-600">{{ formatCurrency(form.sale_type === 'wholesale' && p.wholesale_price > 0 ? p.wholesale_price : p.selling_price) }}</p>
                                <p :class="['text-xs font-medium', p.is_service ? 'text-indigo-400' : p.stock <= 0 ? 'text-red-500' : p.stock <= 5 ? 'text-amber-500' : 'text-emerald-500']">
                                    {{ p.is_service ? t('pos.service') : p.stock <= 0 ? t('pos.outOfStock') : `${t('pos.stock')}: ${p.stock}` }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart -->
                <div class="flex-1 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">

                    <!-- Cart header -->
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50/80">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            {{ t('pos.cart') }}
                            <span v-if="cart.length" class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ cart.length }}</span>
                        </span>
                        <button
                            v-if="cart.length"
                            @click="clearCart"
                            class="text-xs text-red-400 hover:text-red-600 font-medium transition-colors flex items-center gap-1"
                        >
                            <TrashIcon class="w-3.5 h-3.5" />
                            {{ t('pos.clearCart') }}
                        </button>
                    </div>

                    <!-- Cart empty state -->
                    <div v-if="!cart.length" class="flex-1 flex flex-col items-center justify-center text-center py-12">
                        <ShoppingCartIcon class="w-14 h-14 text-gray-200 mb-3" />
                        <p class="text-sm font-medium text-gray-400">{{ t('pos.cartEmpty') }}</p>
                        <p class="text-xs text-gray-300 mt-1">{{ t('pos.cartEmptyHint') }}</p>
                    </div>

                    <!-- Cart rows -->
                    <div v-else class="flex-1 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-gray-50 border-b border-gray-100 z-10">
                                <tr>
                                    <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 w-8">#</th>
                                    <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500">{{ t('pos.product') }}</th>
                                    <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 w-28">{{ t('pos.qty') }}</th>
                                    <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 w-28 hidden sm:table-cell">{{ t('pos.price') }}</th>
                                    <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 w-28">{{ t('pos.total') }}</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr
                                    v-for="(line, idx) in cart"
                                    :key="line._key"
                                    :class="['hover:bg-gray-50/60 transition-colors', line.is_service ? 'bg-violet-50/30' : '']"
                                >
                                    <td class="px-3 py-2 text-gray-400 text-xs">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 border border-gray-100">
                                                <img v-if="line.image_url" :src="line.image_url" :alt="line.name" class="w-full h-full object-cover" />
                                                <div v-else class="w-full h-full flex items-center justify-center">
                                                    <PhotoIcon class="w-3.5 h-3.5 text-gray-300" />
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 text-sm truncate max-w-[160px]">{{ line.name }}</p>
                                                <p class="text-xs text-gray-400">{{ line.sku }} · {{ line.unit_symbol || line.unit_name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            v-model.number="line.quantity"
                                            @input="onQtyChange(line)"
                                            class="w-20 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-400 tabular-nums"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-600 tabular-nums hidden sm:table-cell">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            v-model.number="line.unit_price"
                                            @input="recalcLine(line)"
                                            class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 tabular-nums"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-right font-bold text-gray-900 tabular-nums">
                                        {{ formatCurrency(line.line_total) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <button @click="removeFromCart(idx)" class="p-1 text-gray-300 hover:text-red-500 transition-colors rounded">
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT: customer + totals + payment ───────────────────────── -->
            <div class="w-80 xl:w-96 flex flex-col bg-white border-l border-gray-200 overflow-y-auto flex-shrink-0 shadow-xl">

                <!-- Kunde section -->
                <div class="p-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3 flex items-center gap-1.5">
                        <UserIcon class="w-3.5 h-3.5" />
                        {{ t('pos.customer') }}
                    </h3>

                    <!-- Walk-in / Registered toggle -->
                    <div class="flex rounded-lg border border-gray-200 overflow-hidden mb-3">
                        <button
                            @click="customer.type = 'walkin'"
                            :class="['flex-1 py-1.5 text-xs font-semibold transition-colors',
                                customer.type === 'walkin' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']"
                        >{{ t('pos.walkin') }}</button>
                        <button
                            @click="customer.type = 'registered'"
                            :class="['flex-1 py-1.5 text-xs font-semibold transition-colors',
                                customer.type === 'registered' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']"
                        >{{ t('pos.registered') }}</button>
                    </div>

                    <!-- Walk-in fields -->
                    <div v-if="customer.type === 'walkin'" class="space-y-2">
                        <input v-model="customer.name" type="text" :placeholder="t('pos.customerName')"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                        <input v-model="customer.phone" type="text" :placeholder="t('pos.customerPhone')"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                    </div>

                    <!-- Registered customer select -->
                    <div v-else class="space-y-2">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                            <input
                                v-model="customerSearch"
                                type="search"
                                :placeholder="t('pos.searchCustomer')"
                                class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            />
                        </div>
                        <div v-if="filteredCustomers.length" class="max-h-36 overflow-y-auto rounded-lg border border-gray-200">
                            <div
                                v-for="c in filteredCustomers"
                                :key="c.id"
                                @click="selectCustomer(c)"
                                :class="['px-3 py-2 text-sm cursor-pointer transition-colors hover:bg-indigo-50 border-b border-gray-50 last:border-0',
                                    customer.id === c.id ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700']"
                            >
                                <p class="font-medium">{{ c.name }}</p>
                                <p class="text-xs text-gray-400">{{ c.code }} · {{ c.phone ?? '—' }}</p>
                            </div>
                        </div>
                        <div v-if="customer.id" class="flex items-center justify-between bg-emerald-50 rounded-lg px-3 py-2 text-xs">
                            <span class="text-emerald-700 font-semibold">{{ customer.name }}</span>
                            <button @click="clearCustomer" class="text-gray-400 hover:text-red-500"><XMarkIcon class="w-3.5 h-3.5" /></button>
                        </div>
                    </div>
                </div>

                <!-- Sale type + note -->
                <div class="p-4 border-b border-gray-100 space-y-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('pos.saleType') }}</label>
                        <div class="flex rounded-lg border border-gray-200 overflow-hidden mt-1.5">
                            <button @click="onSaleTypeChange('retail')"
                                :class="['flex-1 py-1.5 text-xs font-semibold transition-colors',
                                    form.sale_type === 'retail' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">
                                {{ t('pos.retail') }}
                            </button>
                            <button @click="onSaleTypeChange('wholesale')"
                                :class="['flex-1 py-1.5 text-xs font-semibold transition-colors',
                                    form.sale_type === 'wholesale' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">
                                {{ t('pos.wholesale') }}
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="text-xs text-gray-400 mb-1 block">{{ t('pos.discount') }}</label>
                            <input v-model.number="form.discount" type="number" min="0" step="0.01"
                                class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 tabular-nums" />
                        </div>
                        <div class="flex-1">
                            <label class="text-xs text-gray-400 mb-1 block">{{ t('pos.freight') }}</label>
                            <input v-model.number="form.freight" type="number" min="0" step="0.01"
                                class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 tabular-nums" />
                        </div>
                    </div>
                </div>

                <!-- Totals summary (dark card) -->
                <div class="mx-4 my-3 bg-slate-800 rounded-xl p-4 text-sm space-y-2">
                    <div class="flex justify-between text-slate-300">
                        <span>{{ t('pos.subtotal') }}</span>
                        <span class="font-mono">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div v-if="form.discount > 0" class="flex justify-between text-red-400">
                        <span>{{ t('pos.discount') }}</span>
                        <span class="font-mono">−{{ formatCurrency(form.discount) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-300">
                        <span>{{ t('pos.vat') }}</span>
                        <span class="font-mono">{{ formatCurrency(vatTotal) }}</span>
                    </div>
                    <div v-if="form.freight > 0" class="flex justify-between text-slate-300">
                        <span>{{ t('pos.freight') }}</span>
                        <span class="font-mono">{{ formatCurrency(form.freight) }}</span>
                    </div>
                    <div class="border-t border-slate-600 pt-2 flex justify-between">
                        <span class="font-bold text-white text-base">{{ t('pos.grandTotal') }}</span>
                        <span class="font-bold text-indigo-300 text-lg font-mono tabular-nums">{{ formatCurrency(grandTotal) }}</span>
                    </div>
                </div>

                <!-- Payment section -->
                <div class="p-4 border-t border-gray-100 space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 flex items-center gap-1.5">
                        <BanknotesIcon class="w-3.5 h-3.5" />
                        {{ t('pos.payment') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-medium text-gray-500 flex items-center gap-1 mb-1">
                                <BanknotesIcon class="w-3 h-3" /> {{ t('pos.cash') }}
                            </label>
                            <input v-model.number="payment.cash" type="number" min="0" step="0.01"
                                @focus="$event.target.select()"
                                class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 tabular-nums font-semibold" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 flex items-center gap-1 mb-1">
                                <CreditCardIcon class="w-3 h-3" /> {{ t('pos.card') }}
                            </label>
                            <input v-model.number="payment.card" type="number" min="0" step="0.01"
                                @focus="$event.target.select()"
                                class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 tabular-nums font-semibold" />
                        </div>
                    </div>

                    <!-- Change / Due display -->
                    <div v-if="change > 0" class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                        <span class="text-sm font-medium text-emerald-700">{{ t('pos.change') }}</span>
                        <span class="font-bold text-emerald-700 font-mono tabular-nums">{{ formatCurrency(change) }}</span>
                    </div>
                    <div v-else-if="due > 0" class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        <span class="text-sm font-medium text-amber-700">{{ t('pos.due') }}</span>
                        <span class="font-bold text-amber-700 font-mono tabular-nums">{{ formatCurrency(due) }}</span>
                    </div>

                    <!-- Error -->
                    <p v-if="saleError" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ saleError }}</p>
                </div>

                <!-- Action buttons -->
                <div class="p-4 pt-0 mt-auto space-y-2">
                    <button
                        @click="confirmSale"
                        :disabled="!cart.length || saving"
                        class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-base rounded-xl shadow-md transition-colors flex items-center justify-center gap-2"
                    >
                        <svg v-if="saving" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <CheckBadgeIcon v-else class="w-5 h-5" />
                        {{ saving ? t('pos.processing') : t('pos.confirmSale') }}
                    </button>
                </div>

            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink, useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useAuthStore }     from '@/stores/auth';
import { useAlert }         from '@/composables/useAlert';
import { saleService }      from '@/services/saleService';
import { customerService }  from '@/services/customerService';
import { useDebounce }      from '@vueuse/core';

import {
    ShoppingCartIcon, MagnifyingGlassIcon, PlusIcon, ListBulletIcon,
    TrashIcon, XMarkIcon, UserIcon, PhotoIcon, BanknotesIcon,
    CreditCardIcon, CheckBadgeIcon,
} from '@heroicons/vue/24/outline';

const { t }          = useI18n();
const { toast, confirm } = useAlert();
const settingsStore  = useSettingsStore();
const authStore      = useAuthStore();
const router         = useRouter();

const todayFormatted = new Date().toLocaleDateString('de-DE', {
    weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric',
});

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value);
}

// ── State ──────────────────────────────────────────────────────────────────
const searchInputRef = ref(null);
const searchQuery    = ref('');
const searchResults  = ref([]);
const searchLoading  = ref(false);

const cart = ref([]);
let   cartKey = 0;

const customer = reactive({
    type: 'walkin',
    id: null,
    name: '',
    phone: '',
    address: '',
});

const customerSearch     = ref('');
const allCustomers       = ref([]);
const form = reactive({
    sale_number: '',
    sale_date:   new Date().toISOString().substring(0, 10),
    sale_type:   'retail',
    discount:    0,
    freight:     0,
    note:        '',
});

const payment = reactive({ cash: 0, card: 0 });
const saving    = ref(false);
const saleError = ref('');

// ── Computed ───────────────────────────────────────────────────────────────
const subtotal  = computed(() => cart.value.reduce((s, l) => s + l.line_total, 0));
const vatTotal  = computed(() => cart.value.reduce((s, l) => s + l.line_vat, 0));
const grandTotal = computed(() => Math.max(0, subtotal.value - form.discount + vatTotal.value + form.freight));
const totalPaid  = computed(() => payment.cash + payment.card);
const change     = computed(() => Math.max(0, totalPaid.value - grandTotal.value));
const due        = computed(() => Math.max(0, grandTotal.value - totalPaid.value));

const filteredCustomers = computed(() => {
    const q = customerSearch.value.toLowerCase().trim();
    if (!q) return allCustomers.value.slice(0, 20);
    return allCustomers.value.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.code.toLowerCase().includes(q) ||
        (c.phone ?? '').includes(q)
    ).slice(0, 10);
});

// ── Debounced product search ───────────────────────────────────────────────
const debouncedSearch = useDebounce(searchQuery, 300);

watch(debouncedSearch, async (q) => {
    if (!q.trim()) { searchResults.value = []; return; }
    searchLoading.value = true;
    try {
        const { data } = await saleService.posSearch(q, authStore.branchId);
        searchResults.value = data;
    } catch {
        searchResults.value = [];
    } finally {
        searchLoading.value = false;
    }
});

// ── Barcode scanner: Enter key → auto-add if exactly one match ────────────
async function handleSearchEnter() {
    const q = searchQuery.value.trim();
    if (!q) return;

    // If results are already loaded and there's exactly one → add it
    if (searchResults.value.length === 1) {
        addToCart(searchResults.value[0]);
        return;
    }

    // Otherwise do an immediate (non-debounced) lookup — barcode scanners send
    // the full value followed by Enter in a single burst, so debounce may not
    // have fired yet.
    searchLoading.value = true;
    try {
        const { data } = await saleService.posSearch(q, authStore.branchId);
        searchResults.value = data;
        if (data.length === 1) {
            addToCart(data[0]);
        }
    } catch {
        searchResults.value = [];
    } finally {
        searchLoading.value = false;
    }
}

// Auto-set cash = grand total when total changes and no amount entered yet
watch(grandTotal, (val) => {
    if (payment.cash === 0 && payment.card === 0) {
        payment.cash = Math.round(val * 100) / 100;
    }
});

// ── Cart methods ───────────────────────────────────────────────────────────
function getLinePrice(product) {
    return form.sale_type === 'wholesale' && product.wholesale_price > 0
        ? product.wholesale_price
        : product.selling_price;
}

function buildLine(product) {
    const unitPrice = getLinePrice(product);
    const lineTotal = Math.round(product.quantity * unitPrice * 100) / 100;
    const lineVat   = Math.round(lineTotal * (product.tax_rate / 100) * 100) / 100;
    return {
        _key:       ++cartKey,
        product_id: product.id,
        sku:        product.sku,
        name:       product.name,
        image_url:  product.image_url,
        unit_name:  product.unit_name,
        unit_symbol:product.unit_symbol,
        is_service: product.is_service,
        stock:      product.stock,
        cost_price: product.cost_price,
        tax_rate:   product.tax_rate,
        quantity:   product.quantity ?? 1,
        unit_price: unitPrice,
        line_total: lineTotal,
        line_vat:   lineVat,
    };
}

function addToCart(product) {
    searchQuery.value   = '';
    searchResults.value = [];

    // Out-of-stock check
    if (!product.is_service && product.stock <= 0) {
        toast('error', t('pos.outOfStockAlert', { name: product.name }));
        return;
    }

    const existing = cart.value.find(l => l.product_id === product.id);
    if (existing) {
        if (!product.is_service && existing.quantity >= product.stock) {
            toast('warning', t('pos.stockLimitAlert', { name: product.name }));
            return;
        }
        existing.quantity += 1;
        recalcLine(existing);
    } else {
        cart.value.push(buildLine({ ...product, quantity: 1 }));
    }
    nextTick(() => searchInputRef.value?.focus());
}

function recalcLine(line) {
    const qty   = Math.max(0.01, line.quantity ?? 0);
    line.quantity   = qty;
    line.line_total = Math.round(qty * line.unit_price * 100) / 100;
    line.line_vat   = Math.round(line.line_total * (line.tax_rate / 100) * 100) / 100;
}

function onQtyChange(line) {
    if (!line.quantity || line.quantity <= 0) {
        const idx = cart.value.indexOf(line);
        cart.value.splice(idx, 1);
        return;
    }
    recalcLine(line);
}

function removeFromCart(idx) {
    cart.value.splice(idx, 1);
}

function clearCart() {
    cart.value = [];
    payment.cash = 0;
    payment.card = 0;
}

// When sale type changes, re-calculate prices
function onSaleTypeChange(type) {
    form.sale_type = type;
}

// ── Customer methods ───────────────────────────────────────────────────────
function selectCustomer(c) {
    customer.id    = c.id;
    customer.name  = c.name;
    customer.phone = c.phone ?? '';
    customerSearch.value = '';
}

function clearCustomer() {
    customer.id    = null;
    customer.name  = '';
    customer.phone = '';
}

// ── Save ───────────────────────────────────────────────────────────────────
async function confirmSale() {
    saleError.value = '';
    if (!cart.value.length) return;

    // Auto-fill cash if still 0
    if (payment.cash === 0 && payment.card === 0) {
        payment.cash = grandTotal.value;
    }

    saving.value = true;
    try {
        const payload = {
            sale_date:        form.sale_date,
            sale_type:        form.sale_type,
            discount_amount:  form.discount,
            freight_amount:   form.freight,
            note:             form.note || null,
            cash_paid:        payment.cash,
            card_paid:        payment.card,
            total_paid:       totalPaid.value,
            customer_type:    customer.type,
            customer_id:      customer.type === 'registered' ? customer.id : null,
            customer_name:    customer.name  || null,
            customer_phone:   customer.phone || null,
            customer_address: customer.address || null,
            items: cart.value.map(l => ({
                product_id:  l.product_id,
                quantity:    l.quantity,
                unit_price:  l.unit_price,
                cost_price:  l.cost_price,
                tax_rate:    l.tax_rate,
                is_service:  l.is_service,
            })),
        };

        const { data } = await saleService.store(payload);
        const saleId   = (data.data ?? data).id;

        toast('success', t('pos.saleSuccess'));

        const wantsPrint = await confirm({
            title:       t('pos.printPromptTitle'),
            text:        t('pos.printPromptText'),
            confirmText: t('pos.printNow'),
            cancelText:  t('pos.skipPrint'),
        });

        if (wantsPrint) {
            router.push({ name: 'sale-invoice', params: { id: saleId } });
        } else {
            resetSale();
        }
    } catch (err) {
        saleError.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        saving.value = false;
    }
}

// ── Reset / init ───────────────────────────────────────────────────────────
function resetSale() {
    cart.value         = [];
    form.discount      = 0;
    form.freight       = 0;
    form.note          = '';
    form.sale_date     = new Date().toISOString().substring(0, 10);
    payment.cash       = 0;
    payment.card       = 0;
    saleError.value    = '';
    searchQuery.value  = '';
    searchResults.value = [];
    customer.type      = 'walkin';
    customer.id        = null;
    customer.name      = '';
    customer.phone     = '';
    nextTick(() => searchInputRef.value?.focus());
}

async function loadCustomers() {
    try {
        const { data } = await customerService.all();
        allCustomers.value = data.data ?? data;
    } catch { /* non-critical */ }
}

onMounted(() => {
    loadCustomers();
    nextTick(() => searchInputRef.value?.focus());
});
</script>

<style scoped>
@reference '../../../css/app.css';
</style>
