<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.shifts.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.shifts.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('hrm.shifts.add') }}
            </button>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div v-if="loading" class="text-center py-12 text-slate-400">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <div v-else-if="shifts.length === 0" class="bg-white rounded-xl border border-dashed border-slate-300 py-16 text-center">
            <ClockIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
            <p class="text-sm text-slate-500">{{ t('hrm.shifts.empty') }}</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="s in shifts" :key="s.id"
                class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 hover:border-indigo-300 hover:shadow transition"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <ClockIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900 truncate">{{ s.name }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ s.employees_count ?? 0 }} {{ t('hrm.departments.employees') }}
                            </p>
                        </div>
                    </div>
                    <button @click="toggleStatus(s)">
                        <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium',
                            s.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600']">
                            <span :class="['w-1.5 h-1.5 rounded-full', s.is_active ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                            {{ t(s.is_active ? 'common.active' : 'common.inactive') }}
                        </span>
                    </button>
                </div>

                <div class="mt-4 flex items-center justify-between px-3 py-2.5 bg-slate-50/70 rounded-lg">
                    <div class="text-center">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('hrm.shifts.start') }}</p>
                        <p class="text-base font-mono font-semibold text-slate-800">{{ s.start_time }}</p>
                    </div>
                    <ArrowLongRightIcon class="w-5 h-5 text-slate-300" />
                    <div class="text-center">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('hrm.shifts.end') }}</p>
                        <p class="text-base font-mono font-semibold text-slate-800">{{ s.end_time }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('hrm.shifts.grace') }}</p>
                        <p class="text-base font-mono font-semibold text-slate-600">{{ s.grace_minutes }}'</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-1 mt-3">
                    <button @click="openEdit(s)" class="action-btn" :title="t('common.edit')">
                        <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="confirmDelete(s)" class="action-btn hover:text-rose-600 hover:bg-rose-50" :title="t('common.delete')">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <Modal v-model="modalOpen" :title="editing ? t('hrm.shifts.editTitle') : t('hrm.shifts.createTitle')" size="md">
            <form @submit.prevent="save" class="space-y-4" id="shift-form">
                <div>
                    <label class="lbl">{{ t('hrm.fields.shift_name') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.name" type="text" class="ctrl" placeholder="Tagschicht" required />
                    <p v-if="errors.name" class="err">{{ errors.name }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.shifts.start') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.start_time" type="time" class="ctrl" required />
                        <p v-if="errors.start_time" class="err">{{ errors.start_time }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.shifts.end') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.end_time" type="time" class="ctrl" required />
                        <p v-if="errors.end_time" class="err">{{ errors.end_time }}</p>
                    </div>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.shifts.grace') }} ({{ t('hrm.shifts.minutes') }})</label>
                    <input v-model.number="form.grace_minutes" type="number" min="0" max="120" class="ctrl" />
                    <p class="text-[11px] text-slate-400 mt-1">{{ t('hrm.shifts.graceHint') }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    {{ t('common.active') }}
                </label>
            </form>
            <template #footer>
                <button @click="modalOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                <button @click="save" :disabled="saving" type="submit" form="shift-form" class="btn-primary">
                    <CheckIcon v-if="!saving" class="w-4 h-4" />
                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { shiftService } from '@/services/hrmService';
import { useAlert } from '@/composables/useAlert';
import Modal from '@/components/ui/Modal.vue';
import {
    PlusIcon, PencilSquareIcon, TrashIcon, CheckIcon, ArrowPathIcon,
    ClockIcon, ArrowLongRightIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();

const shifts = ref([]);
const loading = ref(false);
const errorMsg = ref('');

const modalOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = ref({
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    grace_minutes: 10,
    is_active: true,
});

async function fetchList() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await shiftService.index();
        shifts.value = data.data ?? [];
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    form.value = { name: '', start_time: '08:00', end_time: '17:00', grace_minutes: 10, is_active: true };
    errors.value = {};
    modalOpen.value = true;
}

function openEdit(s) {
    editing.value = s;
    form.value = {
        name: s.name,
        start_time: s.start_time,
        end_time: s.end_time,
        grace_minutes: s.grace_minutes,
        is_active: s.is_active,
    };
    errors.value = {};
    modalOpen.value = true;
}

async function save() {
    errors.value = {};
    saving.value = true;
    try {
        if (editing.value) {
            await shiftService.update(editing.value.id, form.value);
            toast('success', t('common.updatedSuccess'));
        } else {
            await shiftService.store(form.value);
            toast('success', t('common.createdSuccess'));
        }
        modalOpen.value = false;
        fetchList();
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            toast('error', data?.message ?? t('common.unexpectedError'));
        }
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(s) {
    try {
        await shiftService.toggleStatus(s.id);
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmDelete(s) {
    const ok = await confirm({
        title: t('hrm.shifts.deleteTitle'),
        text:  t('hrm.shifts.deleteMessage', { name: s.name }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await shiftService.destroy(s.id);
        toast('success', t('common.deletedSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(fetchList);
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>
