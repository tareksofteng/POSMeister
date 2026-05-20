<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('oms.notify.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('oms.notify.subtitle') }}</p>
        </header>

        <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden">
            <button @click="tab = 'inbox'"    :class="['px-3 py-2 text-xs font-medium', tab === 'inbox' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                {{ t('oms.notify.inbox') }}
            </button>
            <button @click="tab = 'templates'":class="['px-3 py-2 text-xs font-medium', tab === 'templates' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                {{ t('oms.notify.templates') }}
            </button>
        </div>

        <!-- Inbox -->
        <section v-if="tab === 'inbox'" class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('oms.notify.channel') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.notify.recipient') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.notify.subject') }}</th>
                        <th class="px-4 py-2.5">{{ t('common.status') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.notify.queuedAt') }}</th>
                        <th class="px-4 py-2.5 text-right w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="n in rows" :key="n.id" :class="['hover:bg-slate-50/60', n.status === 'read' ? 'opacity-60' : '']">
                        <td class="px-4 py-2">
                            <span :class="channelBadge(n.channel)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ n.channel }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-700">{{ n.recipient_type }} #{{ n.recipient_id }}</td>
                        <td class="px-4 py-2 text-slate-800 truncate max-w-md">{{ n.subject || n.body?.slice(0, 60) }}</td>
                        <td class="px-4 py-2">
                            <span :class="statusBadge(n.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ t('oms.notify.statuses.' + n.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ formatDate(n.created_at) }}</td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="n.status !== 'read'" @click="markRead(n)" class="text-xs text-indigo-600 hover:underline">
                                {{ t('oms.notify.markRead') }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('oms.notify.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Templates -->
        <section v-if="tab === 'templates'" class="space-y-4">
            <div class="flex justify-end">
                <button @click="openTemplateCreate" class="btn-primary">
                    <PlusIcon class="w-4 h-4" /> {{ t('oms.notify.newTemplate') }}
                </button>
            </div>
            <div class="card overflow-hidden p-0">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50/70">
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                            <th class="px-4 py-2.5">{{ t('oms.notify.code') }}</th>
                            <th class="px-4 py-2.5">{{ t('oms.notify.name') }}</th>
                            <th class="px-4 py-2.5">{{ t('oms.notify.channel') }}</th>
                            <th class="px-4 py-2.5">{{ t('oms.notify.subject') }}</th>
                            <th class="px-4 py-2.5 text-center">{{ t('common.status') }}</th>
                            <th class="px-4 py-2.5 text-right w-32"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="tpl in templates" :key="tpl.id">
                            <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ tpl.code }}</td>
                            <td class="px-4 py-2 text-slate-800">{{ tpl.name }}</td>
                            <td class="px-4 py-2">
                                <span :class="channelBadge(tpl.channel)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">{{ tpl.channel }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-700 truncate max-w-sm">{{ tpl.subject }}</td>
                            <td class="px-4 py-2 text-center">
                                <span :class="['inline-block w-2 h-2 rounded-full', tpl.is_active ? 'bg-emerald-500' : 'bg-slate-300']"></span>
                            </td>
                            <td class="px-4 py-2 text-right text-xs space-x-2">
                                <button @click="openTemplateEdit(tpl)" class="text-indigo-600 hover:underline">{{ t('common.edit') }}</button>
                                <button @click="destroyTemplate(tpl)" class="text-rose-600 hover:underline">{{ t('common.delete') }}</button>
                            </td>
                        </tr>
                        <tr v-if="!templatesLoading && templates.length === 0">
                            <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('oms.notify.noTemplates') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Template modal -->
        <div v-if="tplFormOpen" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-xl rounded-xl shadow-xl">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ tplEditing?.id ? t('oms.notify.editTemplate') : t('oms.notify.newTemplate') }}
                    </h3>
                    <button @click="tplFormOpen = false"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
                </header>
                <div class="px-5 py-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">{{ t('oms.notify.code') }}</label>
                            <input v-model="tplForm.code" class="ctrl w-full font-mono" placeholder="order_shipped" />
                        </div>
                        <div>
                            <label class="lbl">{{ t('oms.notify.channel') }}</label>
                            <select v-model="tplForm.channel" class="ctrl w-full">
                                <option value="sms">sms</option>
                                <option value="whatsapp">whatsapp</option>
                                <option value="email">email</option>
                                <option value="in_app">in_app</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.notify.name') }}</label>
                        <input v-model="tplForm.name" class="ctrl w-full" />
                    </div>
                    <div v-if="tplForm.channel === 'email'">
                        <label class="lbl">{{ t('oms.notify.subject') }}</label>
                        <input v-model="tplForm.subject" class="ctrl w-full" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.notify.body') }}</label>
                        <textarea v-model="tplForm.body" rows="6" class="ctrl w-full" placeholder="Hello {{ customer_name }}, ..."></textarea>
                        <p class="text-[10px] text-slate-500 mt-1">{{ t('oms.notify.varsHint') }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="tplForm.is_active" class="rounded border-slate-300" />
                        {{ t('common.active') }}
                    </label>
                </div>
                <footer class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="tplFormOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                    <button @click="saveTemplate" :disabled="tplSaving" class="btn-primary">{{ t('common.save') }}</button>
                </footer>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { notificationService } from '@/services/omsService';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { toast, confirm } = useAlert();

const tab = ref('inbox');
const rows = ref([]);
const loading = ref(false);

const templates = ref([]);
const templatesLoading = ref(false);

const tplFormOpen = ref(false);
const tplSaving = ref(false);
const tplEditing = ref(null);
const tplForm = reactive({ code: '', name: '', channel: 'in_app', subject: '', body: '', is_active: true });

function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'de-DE',
        { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
}
function channelBadge(c) {
    return {
        sms:      'bg-amber-100 text-amber-800',
        whatsapp: 'bg-emerald-100 text-emerald-800',
        email:    'bg-indigo-100 text-indigo-800',
        in_app:   'bg-slate-100 text-slate-700',
    }[c] ?? 'bg-slate-100 text-slate-700';
}
function statusBadge(s) {
    return {
        queued:  'bg-slate-100 text-slate-700',
        sending: 'bg-amber-100 text-amber-800',
        sent:    'bg-emerald-100 text-emerald-800',
        failed:  'bg-rose-100 text-rose-800',
        read:    'bg-indigo-100 text-indigo-800',
    }[s] ?? 'bg-slate-100 text-slate-700';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await notificationService.index({ per_page: 50 });
        rows.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function loadTemplates() {
    templatesLoading.value = true;
    try {
        const { data } = await notificationService.templates();
        templates.value = data.data ?? [];
    } finally {
        templatesLoading.value = false;
    }
}

async function markRead(n) {
    await notificationService.markRead(n.id);
    load();
}

function openTemplateCreate() {
    tplEditing.value = null;
    Object.assign(tplForm, { code: '', name: '', channel: 'in_app', subject: '', body: '', is_active: true });
    tplFormOpen.value = true;
}
function openTemplateEdit(tpl) {
    tplEditing.value = tpl;
    Object.assign(tplForm, {
        code: tpl.code, name: tpl.name, channel: tpl.channel,
        subject: tpl.subject ?? '', body: tpl.body ?? '', is_active: !!tpl.is_active,
    });
    tplFormOpen.value = true;
}
async function saveTemplate() {
    tplSaving.value = true;
    try {
        if (tplEditing.value) {
            await notificationService.updateTemplate(tplEditing.value.id, tplForm);
            toast.success(t('common.updated'));
        } else {
            await notificationService.saveTemplate(tplForm);
            toast.success(t('common.created'));
        }
        tplFormOpen.value = false;
        loadTemplates();
    } finally {
        tplSaving.value = false;
    }
}
async function destroyTemplate(tpl) {
    if (!(await confirm(t('oms.notify.deleteTemplateConfirm', { name: tpl.name })))) return;
    await notificationService.deleteTemplate(tpl.id);
    toast.success(t('common.deleted'));
    loadTemplates();
}

watch(tab, (v) => { v === 'templates' ? loadTemplates() : load(); });
onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
