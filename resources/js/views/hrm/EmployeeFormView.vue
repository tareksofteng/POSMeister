<template>
    <div class="p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

        <div class="flex items-center gap-3">
            <RouterLink :to="{ name: 'hrm-employees' }" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                <ArrowLeftIcon class="w-5 h-5" />
            </RouterLink>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ isEdit ? t('hrm.form.editTitle') : t('hrm.form.createTitle') }}
                    <span v-if="isEdit && form.employee_id" class="text-indigo-600 font-mono text-lg ml-2">
                        {{ form.employee_id }}
                    </span>
                </h1>
                <p class="text-sm text-slate-500">{{ isEdit ? t('hrm.form.editSubtitle') : t('hrm.form.createSubtitle') }}</p>
            </div>
        </div>

        <div v-if="serverError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ serverError }}</div>

        <form @submit.prevent="save" class="space-y-5">

            <section class="card">
                <header class="card-head"><UserIcon class="w-4 h-4 text-indigo-600" /><h2>{{ t('hrm.form.personal') }}</h2></header>
                <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.fields.first_name') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.first_name" type="text" class="ctrl" />
                        <p v-if="errors.first_name" class="err">{{ errors.first_name }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.last_name') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.last_name" type="text" class="ctrl" />
                        <p v-if="errors.last_name" class="err">{{ errors.last_name }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.gender') }} <span class="text-rose-500">*</span></label>
                        <select v-model="form.gender" class="ctrl">
                            <option value="male">{{ t('hrm.gender.male') }}</option>
                            <option value="female">{{ t('hrm.gender.female') }}</option>
                            <option value="other">{{ t('hrm.gender.other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.date_of_birth') }}</label>
                        <input v-model="form.date_of_birth" type="date" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.blood_group') }}</label>
                        <select v-model="form.blood_group" class="ctrl">
                            <option :value="null">—</option>
                            <option v-for="bg in bloodGroups" :key="bg" :value="bg">{{ bg }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.marital_status') }}</label>
                        <select v-model="form.marital_status" class="ctrl">
                            <option :value="null">—</option>
                            <option value="single">{{ t('hrm.marital.single') }}</option>
                            <option value="married">{{ t('hrm.marital.married') }}</option>
                            <option value="divorced">{{ t('hrm.marital.divorced') }}</option>
                            <option value="widowed">{{ t('hrm.marital.widowed') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.nationality') }}</label>
                        <input v-model="form.nationality" type="text" class="ctrl" placeholder="Deutsch" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.religion') }}</label>
                        <input v-model="form.religion" type="text" class="ctrl" />
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="card-head"><EnvelopeIcon class="w-4 h-4 text-indigo-600" /><h2>{{ t('hrm.form.contact') }}</h2></header>
                <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.fields.email') }}</label>
                        <input v-model="form.email" type="email" class="ctrl" />
                        <p v-if="errors.email" class="err">{{ errors.email }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.phone') }}</label>
                        <input v-model="form.phone" type="text" class="ctrl" placeholder="+49 ..." />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.emergency_contact') }}</label>
                        <input v-model="form.emergency_contact" type="text" class="ctrl" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="lbl">{{ t('hrm.fields.address') }}</label>
                        <input v-model="form.address" type="text" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.postal_code') }}</label>
                        <input v-model="form.postal_code" type="text" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.city') }}</label>
                        <input v-model="form.city" type="text" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.country') }}</label>
                        <input v-model="form.country" type="text" class="ctrl" />
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="card-head"><BriefcaseIcon class="w-4 h-4 text-indigo-600" /><h2>{{ t('hrm.form.employment') }}</h2></header>
                <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.fields.joining_date') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.joining_date" type="date" class="ctrl" />
                        <p v-if="errors.joining_date" class="err">{{ errors.joining_date }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.employment_type') }} <span class="text-rose-500">*</span></label>
                        <select v-model="form.employment_type" class="ctrl">
                            <option value="full_time">{{ t('hrm.employment.full_time') }}</option>
                            <option value="part_time">{{ t('hrm.employment.part_time') }}</option>
                            <option value="contract">{{ t('hrm.employment.contract') }}</option>
                            <option value="intern">{{ t('hrm.employment.intern') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.branch') }}</label>
                        <select v-model.number="form.branch_id" class="ctrl">
                            <option :value="null">—</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.department') }}</label>
                        <select v-model.number="form.department_id" @change="onDepartmentChange" class="ctrl">
                            <option :value="null">—</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.designation') }}</label>
                        <select v-model.number="form.designation_id" class="ctrl">
                            <option :value="null">—</option>
                            <option v-for="d in filteredDesignations" :key="d.id" :value="d.id">{{ d.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.shift') }}</label>
                        <select v-model.number="form.shift_id" class="ctrl">
                            <option :value="null">—</option>
                            <option v-for="s in shifts" :key="s.id" :value="s.id">
                                {{ s.name }} ({{ s.start_time }}-{{ s.end_time }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.basic_salary') }} (EUR)</label>
                        <input v-model.number="form.basic_salary" type="number" min="0" step="0.01" class="ctrl text-right" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('common.status') }}</label>
                        <select v-model="form.status" class="ctrl">
                            <option value="active">{{ t('hrm.status_active') }}</option>
                            <option value="inactive">{{ t('hrm.status_inactive') }}</option>
                            <option value="terminated">{{ t('hrm.status_terminated') }}</option>
                            <option value="resigned">{{ t('hrm.status_resigned') }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="card-head"><DocumentTextIcon class="w-4 h-4 text-indigo-600" /><h2>{{ t('hrm.form.documents') }}</h2></header>
                <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.fields.national_id') }}</label>
                        <input v-model="form.national_id" type="text" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.passport_number') }}</label>
                        <input v-model="form.passport_number" type="text" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.fields.work_permit_no') }}</label>
                        <input v-model="form.work_permit_no" type="text" class="ctrl" />
                    </div>
                    <div class="md:col-span-3">
                        <label class="lbl">{{ t('hrm.fields.photo') }}</label>
                        <div class="flex items-center gap-4">
                            <EmployeeAvatar :src="photoPreview" :name="fullNamePreview" size="lg" />
                            <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" @change="onPhotoChange" class="hidden" />
                            <button type="button" @click="fileInput?.click()" class="btn-soft">
                                <ArrowUpTrayIcon class="w-4 h-4" />
                                {{ photoFile ? t('hrm.form.changePhoto') : t('hrm.form.uploadPhoto') }}
                            </button>
                            <button v-if="photoFile" type="button" @click="clearPhoto" class="text-xs text-slate-500 hover:text-rose-600">
                                {{ t('common.cancel') }}
                            </button>
                        </div>
                        <p class="text-xs text-slate-400 mt-1.5">{{ t('hrm.form.photoHint') }}</p>
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="card-head"><PencilIcon class="w-4 h-4 text-indigo-600" /><h2>{{ t('hrm.form.notes') }}</h2></header>
                <div class="card-body">
                    <textarea v-model="form.notes" rows="3" class="ctrl resize-none" :placeholder="t('hrm.form.notesPlaceholder')"></textarea>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3 pt-2">
                <RouterLink :to="{ name: 'hrm-employees' }" class="btn-soft">{{ t('common.cancel') }}</RouterLink>
                <button type="submit" :disabled="saving" class="btn-primary">
                    <CheckIcon v-if="!saving" class="w-4 h-4" />
                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import {
    employeeService, departmentService, designationService, shiftService,
} from '@/services/hrmService';
import { branchService } from '@/services/branchService';
import { useAlert } from '@/composables/useAlert';
import EmployeeAvatar from '@/components/hrm/EmployeeAvatar.vue';
import {
    ArrowLeftIcon, ArrowUpTrayIcon, CheckIcon, ArrowPathIcon,
    UserIcon, EnvelopeIcon, BriefcaseIcon, DocumentTextIcon, PencilIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { toast } = useAlert();

const isEdit = computed(() => !!route.params.id);

const bloodGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

const departments = ref([]);
const designations = ref([]);
const shifts = ref([]);
const branches = ref([]);

const fileInput = ref(null);
const photoFile = ref(null);
const photoPreview = ref('');

const errors = ref({});
const serverError = ref('');
const saving = ref(false);

const form = ref({
    employee_id: '',
    first_name: '', last_name: '',
    email: '', phone: '', emergency_contact: '',
    gender: 'male', date_of_birth: '', blood_group: null, marital_status: null,
    nationality: 'Deutsch', religion: '',
    address: '', city: '', postal_code: '', country: 'Deutschland',
    joining_date: new Date().toISOString().slice(0, 10),
    employment_type: 'full_time',
    designation_id: null, department_id: null, branch_id: null, shift_id: null,
    basic_salary: 0,
    national_id: '', passport_number: '', work_permit_no: '',
    status: 'active', notes: '',
});

const fullNamePreview = computed(() => `${form.value.first_name} ${form.value.last_name}`.trim());

const filteredDesignations = computed(() => {
    if (!form.value.department_id) return designations.value;
    return designations.value.filter(d => !d.department_id || d.department_id === form.value.department_id);
});

function onDepartmentChange() {
    // If current designation belongs to a different department, reset it
    const dept = form.value.department_id;
    const dsg = designations.value.find(d => d.id === form.value.designation_id);
    if (dept && dsg && dsg.department_id && dsg.department_id !== dept) {
        form.value.designation_id = null;
    }
}

function onPhotoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    photoFile.value = file;
    photoPreview.value = URL.createObjectURL(file);
}

function clearPhoto() {
    photoFile.value = null;
    photoPreview.value = '';
    if (fileInput.value) fileInput.value.value = '';
}

async function loadLookups() {
    try {
        const [d, de, s, b] = await Promise.all([
            departmentService.all(),
            designationService.all(),
            shiftService.all(),
            branchService.all(),
        ]);
        departments.value  = d.data.data ?? [];
        designations.value = de.data.data ?? [];
        shifts.value       = s.data.data ?? [];
        branches.value     = b.data.data ?? [];
    } catch {
        // dropdowns failing should not block the form entirely
    }
}

async function loadEmployee() {
    try {
        const { data } = await employeeService.show(route.params.id);
        const e = data.data ?? data;
        form.value = {
            ...form.value,
            ...e,
            blood_group:    e.blood_group ?? null,
            marital_status: e.marital_status ?? null,
            designation_id: e.designation_id ?? null,
            department_id:  e.department_id ?? null,
            branch_id:      e.branch_id ?? null,
            shift_id:       e.shift_id ?? null,
        };
        photoPreview.value = e.photo_url || '';
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
        router.push({ name: 'hrm-employees' });
    }
}

async function save() {
    errors.value = {};
    serverError.value = '';
    saving.value = true;

    const fd = new FormData();
    Object.entries(form.value).forEach(([k, v]) => {
        if (v === null || v === undefined || v === '') return;
        if (k === 'employee_id' && !isEdit.value) return;
        fd.append(k, v);
    });
    if (photoFile.value) fd.append('photo', photoFile.value);

    try {
        const { data } = isEdit.value
            ? await employeeService.update(route.params.id, fd)
            : await employeeService.store(fd);

        toast('success', isEdit.value ? t('common.updatedSuccess') : t('common.createdSuccess'));
        const id = (data.data ?? data).id;
        router.push({ name: 'hrm-employee-show', params: { id } });
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
            serverError.value = t('hrm.form.validationError');
        } else {
            serverError.value = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await loadLookups();
    if (isEdit.value) await loadEmployee();
});
</script>

<style scoped>
@reference '../../../css/app.css';

.card     { @apply bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden; }
.card-head{ @apply flex items-center gap-2 px-5 py-3 border-b border-slate-100; }
.card-head h2 { @apply text-sm font-semibold text-slate-700 uppercase tracking-wide; }
.card-body{ @apply p-5; }

.lbl  { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.err  { @apply text-xs text-rose-600 mt-1; }

.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
</style>
