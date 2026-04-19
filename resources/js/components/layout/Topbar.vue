<template>
    <header class="flex h-16 flex-shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6">

        <!-- Sidebar toggle -->
        <button
            @click="$emit('toggle-sidebar')"
            class="p-2 -ml-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
            :aria-label="t('menu.dashboard')"
        >
            <Bars3Icon class="w-5 h-5" />
        </button>

        <!-- Breadcrumb -->
        <nav class="flex-1 min-w-0">
            <ol class="flex items-center gap-1.5 text-sm">
                <li class="text-gray-400 truncate">POSmeister</li>
                <template v-if="routeTitle">
                    <li class="text-gray-300">/</li>
                    <li class="font-medium text-gray-700 truncate">{{ routeTitle }}</li>
                </template>
            </ol>
        </nav>

        <!-- Right actions -->
        <div class="flex items-center gap-1">

            <!-- Language switcher -->
            <LanguageSwitcher />

            <!-- Notifications (placeholder) -->
            <button
                class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                aria-label="Notifications"
            >
                <BellIcon class="w-5 h-5" />
            </button>

            <!-- User menu -->
            <div class="relative" ref="userMenuRef">
                <button
                    @click="userMenuOpen = !userMenuOpen"
                    class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                >
                    <div class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                        {{ userInitial }}
                    </div>
                    <span class="hidden sm:block text-sm font-medium text-gray-700 max-w-32 truncate">
                        {{ auth.userName }}
                    </span>
                    <ChevronDownIcon class="hidden sm:block w-3.5 h-3.5 text-gray-400 transition-transform" :class="{ 'rotate-180': userMenuOpen }" />
                </button>

                <!-- Dropdown -->
                <Transition
                    enter-active-class="transition ease-out duration-100"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="userMenuOpen"
                        class="absolute right-0 top-full mt-1.5 w-52 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black/5 divide-y divide-gray-100 z-50"
                    >
                        <div class="px-4 py-3">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ auth.userName }}</p>
                            <p class="text-xs text-gray-500 capitalize truncate">{{ auth.userRole }}</p>
                        </div>
                        <div class="py-1">
                            <button
                                @click="handleLogout"
                                class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                            >
                                <ArrowRightStartOnRectangleIcon class="w-4 h-4" />
                                {{ t('auth.signOut') }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

        </div>
    </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { onClickOutside } from '@vueuse/core';
import { useAuthStore } from '@/stores/auth';

import {
    Bars3Icon,
    BellIcon,
    ChevronDownIcon,
    ArrowRightStartOnRectangleIcon,
} from '@heroicons/vue/24/outline';

import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue';

defineEmits(['toggle-sidebar']);

const { t }   = useI18n();
const auth    = useAuthStore();
const router  = useRouter();
const route   = useRoute();

const userMenuOpen = ref(false);
const userMenuRef  = ref(null);

const userInitial = computed(() =>
    auth.userName ? auth.userName.charAt(0).toUpperCase() : '?'
);

// Translate the route title key on every navigation + locale change
const routeTitle = computed(() => {
    const key = route.meta?.titleKey;
    return key ? t(key) : '';
});

onClickOutside(userMenuRef, () => { userMenuOpen.value = false; });

async function handleLogout() {
    userMenuOpen.value = false;
    await auth.logout();
    router.push({ name: 'login' });
}
</script>
