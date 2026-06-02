<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('oms.sync.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('oms.sync.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" /> {{ t('oms.sync.addConnector') }}
            </button>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="c in connectors" :key="c.id" class="card hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900">{{ c.name }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5 uppercase tracking-wider font-semibold">{{ c.type }}</p>
                    </div>
                    <span :class="['inline-block w-2 h-2 rounded-full mt-2', c.is_active ? 'bg-emerald-500' : 'bg-slate-300']"></span>
                </div>
                <p class="text-xs text-slate-500 truncate mb-3">{{ c.api_url }}</p>
                <p class="text-[11px] text-slate-500 mb-3">
                    {{ t('oms.sync.lastSync') }}:
                    <span class="font-mono text-slate-700">{{ c.last_sync_at ? formatDate(c.last_sync_at) : '—' }}</span>
                </p>
                <div class="border-t border-slate-100 pt-3 flex flex-wrap gap-2">
                    <button v-for="entity in entities" :key="entity"
                            @click="runSync(c, entity)"
                            class="text-[11px] px-2 py-1 rounded-md border border-slate-200 hover:bg-indigo-50 hover:border-indigo-200 transition-colors font-medium text-slate-700">
                        {{ entity }}
                    </button>
                </div>
                <div class="border-t border-slate-100 pt-3 mt-3 flex justify-end gap-3 text-xs">
                    <button @click="openEdit(c)" class="text-indigo-600 hover:underline">{{ t('common.edit') }}</button>
                    <button @click="destroyConnector(c)" class="text-rose-600 hover:underline">{{ t('common.delete') }}</button>
                </div>
            </div>
            <div v-if="!loading && connectors.length === 0" class="col-span-full text-center text-sm text-slate-400 py-12">
                {{ t('oms.sync.noConnectors') }}
            </div>
        </section>

        <section class="card">
            <h3 class="card-title">{{ t('oms.sync.recentJobs') }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('oms.sync.connector') }}</th>
                        <th class="py-2">{{ t('oms.sync.entity') }}</th>
                        <th class="py-2">{{ t('oms.sync.direction') }}</th>
                        <th class="py-2">{{ t('common.status') }}</th>
                        <th class="py-2 text-right">{{ t('oms.sync.processed') }}</th>
                        <th class="py-2">{{ t('oms.sync.startedAt') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="j in jobs" :key="j.id" class="hover:bg-slate-50/60">
                        <td class="py-2 text-slate-800">{{ j.connector?.name ?? '—' }}</td>
                        <td class="py-2 text-xs font-mono">{{ j.entity }}</td>
                        <td class="py-2 text-xs">{{ j.direction }}</td>
                        <td class="py-2">
                            <span :class="statusBadge(j.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ j.status }}
                            </span>
                        </td>
                        <td class="py-2 text-right font-mono">{{ j.records_processed }}</td>
                        <td class="py-2 text-xs text-slate-600">{{ formatDate(j.started_at) }}</td>
                    </tr>
                    <tr v-if="!jobsLoading && jobs.length === 0">
                        <td colspan="6" class="py-8 text-center text-sm text-slate-400">{{ t('oms.sync.noJobs') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Connector modal -->
        <div v-if="formOpen" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-xl">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ editing?.id ? t('oms.sync.editConnector') : t('oms.sync.addConnector') }}
                    </h3>
                    <button @click="formOpen = false"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
                </header>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label class="lbl">{{ t('oms.sync.name') }}</label>
                        <input v-model="form.name" class="ctrl w-full" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.sync.type') }}</label>
                        <select v-model="form.type" :disabled="!!editing" class="ctrl w-full">
                            <option value="woocommerce">WooCommerce</option>
                            <option value="shopify">Shopify</option>
                            <option value="custom">Custom Laravel store</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.sync.apiUrl') }}</label>
                        <input v-model="form.api_url" class="ctrl w-full" placeholder="https://shop.example.com" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">API Key</label>
                            <input v-model="form.api_key" class="ctrl w-full font-mono" />
                        </div>
                        <div>
                            <label class="lbl">API Secret</label>
                            <input v-model="form.api_secret" class="ctrl w-full font-mono" type="password" />
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300" />
                        {{ t('common.active') }}
                    </label>
                </div>
                <footer class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="formOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                    <button @click="save" :disabled="saving" class="btn-primary">{{ t('common.save') }}</button>
                </footer>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ecommerceService } from '@/services/omsService';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { toast, confirm } = useAlert();

const connectors = ref([]);
const jobs = ref([]);
const loading = ref(false);
const jobsLoading = ref(false);
const entities = ['products', 'stock', 'customers', 'orders'];

const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = reactive({ name: '', type: 'woocommerce', api_url: '', api_key: '', api_secret: '', is_active: true });

function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'en-US',
        { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
}
function statusBadge(s) {
    return {
        queued:    'bg-slate-100 text-slate-700',
        running:   'bg-indigo-100 text-indigo-800',
        completed: 'bg-emerald-100 text-emerald-800',
        partial:   'bg-amber-100 text-amber-800',
        failed:    'bg-rose-100 text-rose-800',
        cancelled: 'bg-slate-100 text-slate-700',
    }[s] ?? 'bg-slate-100 text-slate-700';
}

async function load() {
    loading.value = true;
    try {
        const [{ data: cs }, { data: js }] = await Promise.all([
            ecommerceService.connectors(),
            ecommerceService.jobs({ per_page: 25 }),
        ]);
        connectors.value = cs.data ?? [];
        jobs.value = js.data ?? [];
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    Object.assign(form, { name: '', type: 'woocommerce', api_url: '', api_key: '', api_secret: '', is_active: true });
    formOpen.value = true;
}
function openEdit(c) {
    editing.value = c;
    Object.assign(form, {
        name: c.name, type: c.type, api_url: c.api_url,
        api_key: '', api_secret: '', is_active: !!c.is_active,
    });
    formOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await ecommerceService.updateConnector(editing.value.id, form);
            toast.success(t('common.updated'));
        } else {
            await ecommerceService.storeConnector(form);
            toast.success(t('common.created'));
        }
        formOpen.value = false;
        load();
    } finally {
        saving.value = false;
    }
}
async function destroyConnector(c) {
    if (!(await confirm(t('oms.sync.deleteConfirm', { name: c.name })))) return;
    await ecommerceService.deleteConnector(c.id);
    toast.success(t('common.deleted'));
    load();
}
async function runSync(c, entity) {
    await ecommerceService.startSync(c.id, { entity, direction: 'pull' });
    toast.success(t('oms.sync.queued'));
    load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
