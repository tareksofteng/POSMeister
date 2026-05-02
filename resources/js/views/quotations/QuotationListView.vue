<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-white">

        <!-- ── Hero header ──────────────────────────────────────────────── -->
        <div class="bg-white border-b border-slate-200/80">
            <div class="px-6 lg:px-8 py-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-200">
                            <DocumentDuplicateIcon class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('quotations.title') }}</h1>
                            <p class="text-sm text-slate-500 mt-0.5">
                                {{ t('quotations.subtitle') }}
                                <span v-if="meta" class="text-slate-400">· {{ meta.total }} {{ t('common.total') }}</span>
                            </p>
                        </div>
                    </div>
                    <RouterLink :to="{ name: 'quotation-create' }" class="btn-violet self-start lg:self-auto">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('quotations.newQuotation') }}
                    </RouterLink>
                </div>

                <!-- Status pipeline strip -->
                <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                    <button
                        v-for="s in pipeline"
                        :key="s.key"
                        @click="toggleStatus(s.key)"
                        :class="[
                            'group relative rounded-xl border px-3 py-2.5 text-left transition-all duration-150',
                            statusFilter === s.key
                                ? `${s.activeBg} ${s.activeBorder} shadow-sm`
                                : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <span :class="['w-2 h-2 rounded-full', s.dot]"></span>
                            <span :class="['text-xs font-semibold uppercase tracking-wide', statusFilter === s.key ? s.activeText : 'text-slate-500']">{{ s.label }}</span>
                        </div>
                        <p :class="['text-lg font-bold mt-1', statusFilter === s.key ? s.activeText : 'text-slate-900']">{{ counts[s.key] ?? 0 }}</p>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Filter row ──────────────────────────────────────────────── -->
        <div class="px-6 lg:px-8 py-4 flex flex-col sm:flex-row gap-3 flex-wrap">
            <div class="relative flex-1 min-w-[220px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('quotations.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white"
                />
            </div>
            <input v-model="dateFrom" type="date" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white" />
            <input v-model="dateTo"   type="date" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white" />
            <button
                v-if="searchQuery || dateFrom || dateTo || statusFilter"
                @click="clearFilters"
                class="px-3 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1.5"
            >
                <XMarkIcon class="w-4 h-4" />
                {{ t('common.clear') }}
            </button>
        </div>

        <!-- ── Error ─────────────────────────────────────────────────────── -->
        <div v-if="listError" class="mx-6 lg:mx-8 mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ listError }}
        </div>

        <!-- ── List ──────────────────────────────────────────────────────── -->
        <div class="px-6 lg:px-8 pb-8">
            <!-- Loading -->
            <div v-if="loading" class="text-center py-16 text-slate-400">
                <div class="w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-sm">{{ t('common.loading') }}</p>
            </div>

            <!-- Empty -->
            <div v-else-if="rows.length === 0" class="bg-white rounded-2xl border border-dashed border-slate-300 py-16 px-6 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-violet-50 flex items-center justify-center mb-4">
                    <DocumentDuplicateIcon class="w-7 h-7 text-violet-400" />
                </div>
                <h3 class="text-base font-semibold text-slate-700">{{ t('quotations.emptyTitle') }}</h3>
                <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">{{ t('quotations.emptyMessage') }}</p>
                <RouterLink :to="{ name: 'quotation-create' }" class="btn-violet mt-5 inline-flex">
                    <PlusIcon class="w-4 h-4" />
                    {{ t('quotations.newQuotation') }}
                </RouterLink>
            </div>

            <!-- Cards -->
            <div v-else class="space-y-2.5">
                <div
                    v-for="row in rows"
                    :key="row.id"
                    class="group bg-white rounded-xl border border-slate-200 hover:border-violet-300 hover:shadow-md hover:shadow-violet-100/50 transition-all duration-150"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 p-4 items-center">

                        <!-- Number + date -->
                        <div class="lg:col-span-3 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-violet-50 to-purple-50 flex items-center justify-center flex-shrink-0 group-hover:from-violet-100 group-hover:to-purple-100 transition-colors">
                                <DocumentDuplicateIcon class="w-5 h-5 text-violet-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-bold text-slate-900 truncate">{{ row.quotation_number }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ formatDate(row.quotation_date) }}</p>
                            </div>
                        </div>

                        <!-- Customer -->
                        <div class="lg:col-span-3 min-w-0">
                            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-0.5">{{ t('quotations.customer') }}</p>
                            <p class="text-sm font-medium text-slate-800 truncate">{{ row.customer_name || t('common.walkin') }}</p>
                            <p v-if="row.customer_phone" class="text-xs text-slate-500 truncate mt-0.5">{{ row.customer_phone }}</p>
                        </div>

                        <!-- Valid until -->
                        <div class="lg:col-span-2 min-w-0">
                            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-0.5">{{ t('quotations.validUntil') }}</p>
                            <p
                                :class="[
                                    'text-sm font-medium truncate',
                                    !row.valid_until ? 'text-slate-400' : (row.is_expired ? 'text-red-600' : 'text-slate-800')
                                ]"
                            >
                                {{ row.valid_until ? formatDate(row.valid_until) : '—' }}
                            </p>
                            <p v-if="row.valid_until" class="text-xs mt-0.5" :class="row.is_expired ? 'text-red-500' : 'text-emerald-600'">
                                {{ row.is_expired ? t('quotations.expiredAgo') : t('quotations.daysLeft', { days: daysLeft(row.valid_until) }) }}
                            </p>
                        </div>

                        <!-- Items + total -->
                        <div class="lg:col-span-2 flex lg:flex-col lg:items-end gap-3 lg:gap-0.5">
                            <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                                <ListBulletIcon class="w-3.5 h-3.5" />
                                {{ row.items_count }} {{ t('quotations.items') }}
                            </span>
                            <p class="text-base font-bold text-slate-900 font-mono tabular-nums">{{ fmtCurrency(row.grand_total) }}</p>
                        </div>

                        <!-- Status + actions -->
                        <div class="lg:col-span-2 flex items-center justify-between lg:justify-end gap-2">
                            <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold', statusBadge(row.status).bg, statusBadge(row.status).text]">
                                <span :class="['w-1.5 h-1.5 rounded-full', statusBadge(row.status).dot]"></span>
                                {{ t(`quotations.status_${row.status}`) }}
                            </span>

                            <div class="flex items-center gap-1">
                                <RouterLink
                                    :to="{ name: 'quotation-invoice', params: { id: row.id } }"
                                    class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-colors"
                                    :title="t('quotations.viewInvoice')"
                                >
                                    <EyeIcon class="w-4 h-4" />
                                </RouterLink>
                                <RouterLink
                                    v-if="canEdit(row.status)"
                                    :to="{ name: 'quotation-edit', params: { id: row.id } }"
                                    class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                    :title="t('common.edit')"
                                >
                                    <PencilSquareIcon class="w-4 h-4" />
                                </RouterLink>
                                <button
                                    v-if="canDelete(row.status)"
                                    @click="confirmDelete(row)"
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    :title="t('common.delete')"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between bg-white rounded-xl border border-slate-200 px-4 py-3 mt-4">
                    <p class="text-xs text-slate-500">
                        {{ t('common.showing') }} {{ meta.from }}–{{ meta.to }} {{ t('common.of') }} {{ meta.total }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="p in pageList"
                            :key="p"
                            @click="fetchPage(p)"
                            :class="['w-8 h-8 text-xs font-medium rounded-lg transition-colors',
                                p === meta.current_page
                                    ? 'bg-violet-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-100']"
                        >
                            {{ p }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { quotationService } from '@/services/quotationService';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import {
    DocumentDuplicateIcon, PlusIcon, MagnifyingGlassIcon, XMarkIcon,
    EyeIcon, PencilSquareIcon, TrashIcon, ListBulletIcon,
} from '@heroicons/vue/24/outline';

const { t }              = useI18n();
const { toast, confirm } = useAlert();
const { fmtCurrency }    = useCurrency();

// ── State ──────────────────────────────────────────────────────────────────
const rows         = ref([]);
const meta         = ref(null);
const loading      = ref(false);
const listError    = ref('');
const counts       = ref({});

const searchQuery  = ref('');
const dateFrom     = ref('');
const dateTo       = ref('');
const statusFilter = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

const filters = ref({ search: '', status: '', date_from: '', date_to: '', page: 1, per_page: 20 });

// ── Pipeline definition ───────────────────────────────────────────────────
const pipeline = computed(() => [
    { key: '',          label: t('common.all'),                  dot: 'bg-slate-400',   activeBg: 'bg-slate-100',  activeBorder: 'border-slate-300',  activeText: 'text-slate-800' },
    { key: 'draft',     label: t('quotations.status_draft'),     dot: 'bg-slate-400',   activeBg: 'bg-slate-100',  activeBorder: 'border-slate-300',  activeText: 'text-slate-800' },
    { key: 'sent',      label: t('quotations.status_sent'),      dot: 'bg-blue-500',    activeBg: 'bg-blue-50',    activeBorder: 'border-blue-300',   activeText: 'text-blue-700' },
    { key: 'accepted',  label: t('quotations.status_accepted'),  dot: 'bg-emerald-500', activeBg: 'bg-emerald-50', activeBorder: 'border-emerald-300', activeText: 'text-emerald-700' },
    { key: 'rejected',  label: t('quotations.status_rejected'),  dot: 'bg-red-500',     activeBg: 'bg-red-50',     activeBorder: 'border-red-300',    activeText: 'text-red-700' },
    { key: 'converted', label: t('quotations.status_converted'), dot: 'bg-violet-500',  activeBg: 'bg-violet-50',  activeBorder: 'border-violet-300', activeText: 'text-violet-700' },
]);

// ── Watchers ──────────────────────────────────────────────────────────────
watch(debouncedSearch, (val) => fetchList({ search: val, page: 1 }));
watch(dateFrom,        (val) => fetchList({ date_from: val, page: 1 }));
watch(dateTo,          (val) => fetchList({ date_to: val, page: 1 }));
watch(statusFilter,    (val) => fetchList({ status: val, page: 1 }));

// ── Helpers ───────────────────────────────────────────────────────────────
function statusBadge(status) {
    const map = {
        draft:     { bg: 'bg-slate-100',   text: 'text-slate-700',    dot: 'bg-slate-500' },
        sent:      { bg: 'bg-blue-100',    text: 'text-blue-700',     dot: 'bg-blue-500' },
        accepted:  { bg: 'bg-emerald-100', text: 'text-emerald-700',  dot: 'bg-emerald-500' },
        rejected:  { bg: 'bg-red-100',     text: 'text-red-700',      dot: 'bg-red-500' },
        expired:   { bg: 'bg-amber-100',   text: 'text-amber-700',    dot: 'bg-amber-500' },
        converted: { bg: 'bg-violet-100',  text: 'text-violet-700',   dot: 'bg-violet-500' },
    };
    return map[status] ?? map.draft;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });
}

function daysLeft(dateStr) {
    if (!dateStr) return 0;
    const target = new Date(dateStr + 'T00:00:00').getTime();
    const today  = new Date(new Date().toDateString()).getTime();
    return Math.max(0, Math.round((target - today) / 86_400_000));
}

function canEdit(status)   { return ['draft', 'sent'].includes(status); }
function canDelete(status) { return status !== 'converted'; }

function toggleStatus(key) {
    statusFilter.value = statusFilter.value === key ? '' : key;
}

function clearFilters() {
    searchQuery.value  = '';
    dateFrom.value     = '';
    dateTo.value       = '';
    statusFilter.value = '';
}

// ── Pagination helpers ───────────────────────────────────────────────────
const pageList = computed(() => {
    if (!meta.value) return [];
    const total = meta.value.last_page;
    const cur   = meta.value.current_page;
    const span  = 2;
    const start = Math.max(1,     cur - span);
    const end   = Math.min(total, cur + span);
    const pages = [];
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
});

// ── Data ──────────────────────────────────────────────────────────────────
async function fetchList(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value   = true;
    listError.value = '';
    try {
        const { data } = await quotationService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
        // count per status from current page (best effort)
        recomputeCounts();
    } catch (err) {
        listError.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

function recomputeCounts() {
    const c = { '': meta.value?.total ?? 0 };
    for (const r of rows.value) {
        c[r.status] = (c[r.status] ?? 0) + 1;
    }
    counts.value = c;
}

function fetchPage(page) { fetchList({ page }); }

async function confirmDelete(row) {
    const ok = await confirm({
        title:       t('quotations.deleteTitle'),
        text:        t('quotations.deleteMessage', { number: row.quotation_number }),
        confirmText: t('common.delete'),
        danger:      true,
    });
    if (!ok) return;
    try {
        await quotationService.destroy(row.id);
        toast('success', t('quotations.deletedSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(() => fetchList());
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-violet {
    @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg transition-all duration-150 shadow-sm bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 hover:shadow-md hover:shadow-violet-200;
}
</style>
