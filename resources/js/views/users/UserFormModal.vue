<template>
    <Modal
        :model-value="open"
        :title="isEdit ? t('users.editTitle') : t('users.createTitle')"
        size="md"
        @update:model-value="$emit('update:open', $event)"
    >
        <form id="user-form" @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <!-- Name + Email -->
            <div class="grid grid-cols-2 gap-4">
                <FormField :label="t('users.fullName')" :error="errors.name" required>
                    <input
                        v-model="form.name"
                        type="text"
                        :placeholder="t('users.fullNamePlaceholder')"
                        :class="inputClass(errors.name)"
                    />
                </FormField>

                <FormField :label="t('users.email')" :error="errors.email" required>
                    <input
                        v-model="form.email"
                        type="email"
                        :placeholder="t('users.emailPlaceholder')"
                        :class="inputClass(errors.email)"
                    />
                </FormField>
            </div>

            <!-- Phone + Role -->
            <div class="grid grid-cols-2 gap-4">
                <FormField :label="t('common.phone')" :error="errors.phone">
                    <input
                        v-model="form.phone"
                        type="tel"
                        :placeholder="t('users.phonePlaceholder')"
                        :class="inputClass(errors.phone)"
                    />
                </FormField>

                <FormField :label="t('users.role')" :error="errors.role" required>
                    <select v-model="form.role" :class="inputClass(errors.role)">
                        <option value="">{{ t('users.selectRole') }}</option>
                        <option value="admin">{{ t('users.roles.admin') }}</option>
                        <option value="manager">{{ t('users.roles.manager') }}</option>
                        <option value="cashier">{{ t('users.roles.cashier') }}</option>
                    </select>
                </FormField>
            </div>

            <!-- Branch -->
            <FormField :label="t('users.branch')" :error="errors.branch_id">
                <select v-model="form.branch_id" :class="inputClass(errors.branch_id)">
                    <option value="">{{ t('users.noBranch') }}</option>
                    <option
                        v-for="opt in branchStore.branchOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
            </FormField>

            <!-- Password -->
            <FormField
                :label="t('users.password')"
                :error="errors.password"
                :required="!isEdit"
                :hint="isEdit ? t('users.passwordHint') : ''"
            >
                <div class="relative">
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :placeholder="t('users.passwordPlaceholder')"
                        :class="[inputClass(errors.password), 'pr-10']"
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                        tabindex="-1"
                    >
                        <EyeSlashIcon v-if="showPassword" class="w-4 h-4" />
                        <EyeIcon v-else class="w-4 h-4" />
                    </button>
                </div>
            </FormField>

            <!-- Status -->
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="form.is_active = !form.is_active"
                    :class="[
                        'relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                        form.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                    ]"
                >
                    <span :class="['inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform', form.is_active ? 'translate-x-4' : 'translate-x-1']" />
                </button>
                <label class="text-sm font-medium text-gray-700">
                    {{ form.is_active ? t('common.active') : t('common.inactive') }}
                </label>
            </div>

            <!-- Global error -->
            <p v-if="globalError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">
                {{ globalError }}
            </p>
        </form>

        <template #footer>
            <button
                type="button"
                @click="$emit('update:open', false)"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="submit"
                form="user-form"
                :disabled="submitting"
                class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors"
            >
                <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ isEdit ? t('users.saveChanges') : t('users.createUser') }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal     from '@/components/ui/Modal.vue';
import FormField from '@/components/ui/FormField.vue';
import { userService } from '@/services/userService';
import { useBranchStore } from '@/stores/branch';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();

const props = defineProps({
    open:   { type: Boolean, required: true },
    user:   { type: Object,  default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const branchStore    = useBranchStore();
const isEdit         = computed(() => !!props.user?.id);
const submitting     = ref(false);
const globalError    = ref('');
const showPassword   = ref(false);

const form = reactive({
    name:      '',
    email:     '',
    phone:     '',
    role:      '',
    branch_id: '',
    password:  '',
    is_active: true,
});

const errors = reactive({
    name: '', email: '', phone: '', role: '', branch_id: '', password: '',
});

onMounted(() => branchStore.fetchAllActive());

watch(() => props.user, (val) => {
    clearErrors();
    globalError.value = '';
    showPassword.value = false;
    if (val) {
        form.name      = val.name      ?? '';
        form.email     = val.email     ?? '';
        form.phone     = val.phone     ?? '';
        form.role      = val.role      ?? '';
        form.branch_id = val.branch_id ?? '';
        form.password  = '';
        form.is_active = val.is_active ?? true;
    } else {
        form.name = form.email = form.phone = form.role = form.password = '';
        form.branch_id = '';
        form.is_active = true;
    }
}, { immediate: true });

async function handleSubmit() {
    clearErrors();
    globalError.value = '';

    if (!clientValidate()) return;

    submitting.value = true;

    const payload = { ...form };
    if (isEdit.value && !payload.password) delete payload.password;
    if (!payload.branch_id) payload.branch_id = null;

    try {
        if (isEdit.value) {
            await userService.update(props.user.id, payload);
        } else {
            await userService.store(payload);
        }
        emit('saved');
    } catch (err) {
        const { status, data } = err.response ?? {};

        if (status === 422 && data?.errors) {
            Object.entries(data.errors).forEach(([field, msgs]) => {
                if (field in errors) errors[field] = msgs[0];
            });
        } else {
            globalError.value = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        submitting.value = false;
    }
}

function clientValidate() {
    let valid = true;
    if (!form.name.trim())  { errors.name  = t('users.nameRequired'); valid = false; }
    if (!form.email.trim()) { errors.email = t('users.emailRequired'); valid = false; }
    if (!form.role)         { errors.role  = t('users.roleRequired'); valid = false; }
    if (!isEdit.value && !form.password) { errors.password = t('users.passwordRequired'); valid = false; }
    return valid;
}

function clearErrors() {
    Object.keys(errors).forEach(k => (errors[k] = ''));
}

function inputClass(error) {
    return [
        'block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400',
        'focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors',
        error ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white',
    ];
}
</script>
