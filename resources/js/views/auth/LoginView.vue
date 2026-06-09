<template>
    <div class="min-h-screen flex">

        <!-- ── Left panel (brand) ── shown md+ ──────────────────────────── -->
        <div class="hidden md:flex md:w-1/2 lg:w-2/5 flex-col justify-between bg-slate-900 p-10">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-600">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <span class="text-white font-bold text-lg tracking-tight">POSmeister</span>
            </div>

            <div>
                <blockquote class="text-slate-300 text-2xl font-light leading-snug">
                    {{ t('app.tagline') }}
                </blockquote>
                <p class="mt-6 text-slate-500 text-sm">{{ t('app.subtitle') }}</p>
            </div>

            <div class="flex gap-6 text-xs text-slate-600">
                <span>{{ t('app.features.multiBranch') }}</span>
                <span>{{ t('app.features.realTimeStock') }}</span>
                <span>{{ t('app.features.fullAccounting') }}</span>
            </div>
        </div>

        <!-- ── Right panel (form) ─────────────────────────────────────────── -->
        <div class="flex flex-1 flex-col justify-center items-center px-6 py-12 bg-white">

            <!-- Language switcher (top-right on login page) -->
            <div class="absolute top-4 right-4">
                <LanguageSwitcher />
            </div>

            <!-- Mobile logo -->
            <div class="mb-8 flex md:hidden items-center gap-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-600">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <span class="font-bold text-lg text-gray-900">POSmeister</span>
            </div>

            <div class="w-full max-w-sm anim-fade-up">
                <div class="mb-8">
                    <h1 class="h1-display">{{ t('auth.signIn') }}</h1>
                    <p class="mt-1.5 t-body">{{ t('auth.signInSubtitle') }}</p>
                </div>

                <!-- Error alert — design-system .card-alert chrome, fades in
                     so the user notices it without it being aggressive. -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                >
                    <div v-if="auth.error" class="mb-5 card card-alert card-alert-danger flex items-start gap-3 text-sm">
                        <ExclamationCircleIcon class="w-5 h-5 flex-shrink-0 mt-0.5" />
                        <p>{{ auth.error }}</p>
                    </div>
                </Transition>

                <form @submit.prevent="handleSubmit" class="space-y-4" novalidate>

                    <FormField id="email" :label="t('auth.emailLabel')" :error="fieldErrors.email" required>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            :placeholder="t('auth.emailPlaceholder')"
                            :class="['form-input', { 'is-invalid': fieldErrors.email }]"
                        />
                    </FormField>

                    <FormField id="password" :label="t('auth.passwordLabel')" :error="fieldErrors.password" required>
                        <div class="form-input-group">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                placeholder="••••••••"
                                :class="['form-input form-input-with-icon-right', { 'is-invalid': fieldErrors.password }]"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="form-input-icon-right text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                tabindex="-1"
                                :aria-label="showPassword ? t('auth.hidePassword', 'Hide password') : t('auth.showPassword', 'Show password')"
                            >
                                <EyeSlashIcon v-if="showPassword" class="w-4 h-4" />
                                <EyeIcon      v-else             class="w-4 h-4" />
                            </button>
                        </div>
                    </FormField>

                    <Button
                        type="submit"
                        variant="primary"
                        size="lg"
                        block
                        :loading="auth.loading"
                    >
                        {{ auth.loading ? t('auth.signingIn') : t('auth.signInButton') }}
                    </Button>

                </form>
            </div>
        </div>

    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { ExclamationCircleIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue';
import FormField       from '@/components/ui/FormField.vue';
import Button          from '@/components/ui/Button.vue';

const { t } = useI18n();
const auth  = useAuthStore();
const router = useRouter();
const route  = useRoute();

const showPassword = ref(false);
const form = reactive({ email: '', password: '' });
const fieldErrors = reactive({ email: '', password: '' });

function validate() {
    fieldErrors.email    = '';
    fieldErrors.password = '';
    let valid = true;

    if (!form.email) {
        fieldErrors.email = t('auth.emailRequired'); valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        fieldErrors.email = t('auth.emailInvalid'); valid = false;
    }
    if (!form.password) {
        fieldErrors.password = t('auth.passwordRequired'); valid = false;
    }
    return valid;
}

async function handleSubmit() {
    if (!validate()) return;
    const success = await auth.login({ email: form.email, password: form.password });
    if (success) router.push(route.query.redirect || '/dashboard');
}
</script>
