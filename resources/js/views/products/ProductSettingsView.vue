<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('products.settings.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('products.settings.subtitle') }}</p>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    :class="[
                        'pb-3 text-sm font-medium border-b-2 transition-colors',
                        activeTab === tab.key
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Categories Tab -->
        <div v-if="activeTab === 'categories'">
            <LookupTable
                :title="t('products.categories.title')"
                :rows="categories.rows"
                :loading="categories.loading"
                :error="categories.error"
                :has-description="true"
                :has-status="true"
                @add="openAdd('categories')"
                @edit="openEdit('categories', $event)"
                @delete="confirmDelete('categories', $event)"
                @toggle="confirmToggle('categories', $event)"
            />
        </div>

        <!-- Brands Tab -->
        <div v-if="activeTab === 'brands'">
            <LookupTable
                :title="t('products.brands.title')"
                :rows="brands.rows"
                :loading="brands.loading"
                :error="brands.error"
                :has-status="true"
                @add="openAdd('brands')"
                @edit="openEdit('brands', $event)"
                @delete="confirmDelete('brands', $event)"
                @toggle="confirmToggle('brands', $event)"
            />
        </div>

        <!-- Units Tab -->
        <div v-if="activeTab === 'units'">
            <LookupTable
                :title="t('products.units.title')"
                :rows="units.rows"
                :loading="units.loading"
                :error="units.error"
                :has-symbol="true"
                @add="openAdd('units')"
                @edit="openEdit('units', $event)"
                @delete="confirmDelete('units', $event)"
            />
        </div>
    </div>

    <!-- Add / Edit Modal -->
    <Modal
        v-model="formModal.open"
        :title="formModal.isEdit ? t('common.edit') : t('common.new')"
        size="sm"
    >
        <form id="lookup-form" @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <FormField :label="t('common.name')" :error="formModal.errors.name" required>
                <input v-model="formModal.form.name" type="text" :class="inputClass(formModal.errors.name)" />
            </FormField>

            <FormField v-if="activeTab === 'categories'" :label="t('common.description')" :error="formModal.errors.description">
                <textarea v-model="formModal.form.description" rows="3" :class="inputClass(formModal.errors.description)" />
            </FormField>

            <FormField v-if="activeTab === 'units'" :label="t('products.units.symbol')" :error="formModal.errors.symbol" required>
                <input v-model="formModal.form.symbol" type="text" maxlength="10" :class="inputClass(formModal.errors.symbol)" />
            </FormField>

            <div v-if="activeTab !== 'units'" class="flex items-center gap-3">
                <button
                    type="button"
                    @click="formModal.form.is_active = !formModal.form.is_active"
                    :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2', formModal.form.is_active ? 'bg-indigo-600' : 'bg-gray-300']"
                >
                    <span :class="['inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform', formModal.form.is_active ? 'translate-x-4' : 'translate-x-1']" />
                </button>
                <label class="text-sm font-medium text-gray-700">
                    {{ formModal.form.is_active ? t('common.active') : t('common.inactive') }}
                </label>
            </div>

            <p v-if="formModal.globalError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">
                {{ formModal.globalError }}
            </p>
        </form>

        <template #footer>
            <button type="button" @click="formModal.open = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                {{ t('common.cancel') }}
            </button>
            <button type="submit" form="lookup-form" :disabled="formModal.submitting" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                <svg v-if="formModal.submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ t('common.save') }}
            </button>
        </template>
    </Modal>

    <!-- Delete confirm -->
    <ConfirmDialog
        v-model="deleteDialog.open"
        :title="t('common.deleteConfirmTitle')"
        :message="t('common.deleteConfirmMessage', { name: deleteDialog.target?.name ?? '' })"
        :confirm-label="t('common.delete')"
        :danger="true"
        :loading="deleteDialog.loading"
        @confirm="executeDelete"
    />

    <!-- Toggle confirm -->
    <ConfirmDialog
        v-model="toggleDialog.open"
        :title="toggleDialog.target?.is_active ? t('common.deactivateTitle') : t('common.activateTitle')"
        :message="toggleDialog.target?.is_active ? t('common.deactivateMessage', { name: toggleDialog.target?.name ?? '' }) : t('common.activateMessage', { name: toggleDialog.target?.name ?? '' })"
        :confirm-label="toggleDialog.target?.is_active ? t('common.deactivate') : t('common.activate')"
        :danger="toggleDialog.target?.is_active"
        :loading="toggleDialog.loading"
        @confirm="executeToggle"
    />
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { categoryService } from '@/services/categoryService';
import { brandService }    from '@/services/brandService';
import { unitService }     from '@/services/unitService';
import Modal         from '@/components/ui/Modal.vue';
import FormField     from '@/components/ui/FormField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LookupTable   from './LookupTable.vue';

const { t } = useI18n();

const activeTab = ref('categories');

const tabs = computed(() => [
    { key: 'categories', label: t('products.categories.tab') },
    { key: 'brands',     label: t('products.brands.tab') },
    { key: 'units',      label: t('products.units.tab') },
]);

// ── Per-entity state ──────────────────────────────────────────────────────
const makeState = () => reactive({ rows: [], loading: false, error: '' });
const categories = makeState();
const brands     = makeState();
const units      = makeState();

const services = { categories: categoryService, brands: brandService, units: unitService };

async function loadTab(tab) {
    const state = { categories, brands, units }[tab];
    state.loading = true;
    state.error   = '';
    try {
        const { data } = await services[tab].index({ per_page: 100 });
        state.rows = data.data ?? data;
    } catch {
        state.error = t('common.unexpectedError');
    } finally {
        state.loading = false;
    }
}

async function loadUnits() {
    units.loading = true;
    units.error   = '';
    try {
        const { data } = await unitService.all();
        units.rows = Array.isArray(data) ? data : (data.data ?? []);
    } catch {
        units.error = t('common.unexpectedError');
    } finally {
        units.loading = false;
    }
}

onMounted(() => { loadTab('categories'); loadTab('brands'); loadUnits(); });

// ── Form modal ────────────────────────────────────────────────────────────
const formModal = reactive({
    open: false, isEdit: false, submitting: false, globalError: '',
    errors: { name: '', description: '', symbol: '' },
    form:   { name: '', description: '', symbol: '', is_active: true },
    target: null,
});

function openAdd(tab) {
    activeTab.value = tab;
    Object.assign(formModal, { open: true, isEdit: false, globalError: '', target: null });
    Object.assign(formModal.errors, { name: '', description: '', symbol: '' });
    Object.assign(formModal.form,   { name: '', description: '', symbol: '', is_active: true });
}

function openEdit(tab, row) {
    activeTab.value = tab;
    Object.assign(formModal, { open: true, isEdit: true, globalError: '', target: row });
    Object.assign(formModal.errors, { name: '', description: '', symbol: '' });
    Object.assign(formModal.form, {
        name:        row.name        ?? '',
        description: row.description ?? '',
        symbol:      row.symbol      ?? '',
        is_active:   row.is_active   ?? true,
    });
}

async function handleSubmit() {
    formModal.errors.name = '';
    formModal.globalError = '';
    if (!formModal.form.name.trim()) { formModal.errors.name = t('common.nameRequired'); return; }

    formModal.submitting = true;
    const svc     = services[activeTab.value];
    const payload = buildPayload();

    try {
        if (formModal.isEdit) {
            await svc.update(formModal.target.id, payload);
        } else {
            await svc.store(payload);
        }
        formModal.open = false;
        reloadCurrent();
    } catch (err) {
        const { status, data } = err.response ?? {};
        if (status === 422 && data?.errors) {
            Object.entries(data.errors).forEach(([f, msgs]) => {
                if (f in formModal.errors) formModal.errors[f] = msgs[0];
            });
        } else {
            formModal.globalError = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        formModal.submitting = false;
    }
}

function buildPayload() {
    if (activeTab.value === 'units')      return { name: formModal.form.name, symbol: formModal.form.symbol };
    if (activeTab.value === 'categories') return { name: formModal.form.name, description: formModal.form.description, is_active: formModal.form.is_active };
    return { name: formModal.form.name, is_active: formModal.form.is_active };
}

// ── Delete ────────────────────────────────────────────────────────────────
const deleteDialog = reactive({ open: false, loading: false, target: null, tab: '' });

function confirmDelete(tab, row) { deleteDialog.tab = tab; deleteDialog.target = row; deleteDialog.open = true; }

async function executeDelete() {
    deleteDialog.loading = true;
    try {
        await services[deleteDialog.tab].destroy(deleteDialog.target.id);
        deleteDialog.open = false;
        reloadCurrent();
    } catch (err) {
        alert(err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        deleteDialog.loading = false;
    }
}

// ── Toggle ────────────────────────────────────────────────────────────────
const toggleDialog = reactive({ open: false, loading: false, target: null, tab: '' });

function confirmToggle(tab, row) { toggleDialog.tab = tab; toggleDialog.target = row; toggleDialog.open = true; }

async function executeToggle() {
    toggleDialog.loading = true;
    try {
        const svc = services[toggleDialog.tab];
        await svc.update(toggleDialog.target.id, { ...toggleDialog.target, is_active: !toggleDialog.target.is_active });
        toggleDialog.open = false;
        reloadCurrent();
    } catch (err) {
        alert(err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        toggleDialog.loading = false;
    }
}

function reloadCurrent() {
    if (activeTab.value === 'units') { loadUnits(); return; }
    loadTab(activeTab.value);
}

function inputClass(error) {
    return ['block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors', error ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'];
}
</script>
