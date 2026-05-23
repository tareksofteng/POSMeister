<template>
    <header class="topbar sticky top-0 z-20 flex h-14 sm:h-16 flex-shrink-0 items-center gap-2 sm:gap-4 border-b border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 sm:px-6 pt-safe">

        <!-- Sidebar toggle — hamburger on mobile, collapse-rail on desktop -->
        <button
            @click="$emit('toggle-sidebar')"
            class="touch-target -ml-1.5 flex items-center justify-center text-gray-500 hover:text-gray-900 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
            :aria-label="t('menu.dashboard')"
        >
            <Bars3Icon class="w-5 h-5" />
        </button>

        <!-- Breadcrumb — current title only on mobile to save space -->
        <nav class="flex-1 min-w-0">
            <ol class="flex items-center gap-1.5 text-sm">
                <li class="hidden sm:block text-gray-400 truncate">POSmeister</li>
                <li class="hidden sm:block text-gray-300" v-if="routeTitle">/</li>
                <li v-if="routeTitle" class="font-medium text-gray-800 dark:text-slate-200 truncate">{{ routeTitle }}</li>
            </ol>
        </nav>

        <!-- Right actions -->
        <div class="flex items-center gap-0.5 sm:gap-1">

            <!-- Quick search (opens command palette) -->
            <button
                @click="openPalette"
                class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 text-sm text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg transition-colors"
                :title="t('palette.openHint')"
            >
                <MagnifyingGlassIcon class="w-4 h-4" />
                <span>{{ t('palette.openLabel') }}</span>
                <kbd class="text-[10px] font-mono font-semibold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded px-1.5 py-0.5">⌘K</kbd>
            </button>

            <!-- PWA / connectivity status -->
            <PwaStatusPill />

            <!-- Language switcher -->
            <LanguageSwitcher />

            <!-- Theme toggle -->
            <button
                @click="theme.toggle()"
                class="p-2 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                :title="theme.mode === 'dark' ? t('palette.actions.toLight') : t('palette.actions.toDark')"
            >
                <SunIcon v-if="theme.mode === 'dark'" class="w-5 h-5" />
                <MoonIcon v-else class="w-5 h-5" />
            </button>

            <!-- Notifications (placeholder) — hidden on small mobile to keep header tidy -->
            <button
                class="hidden xs:inline-flex relative touch-target items-center justify-center text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                aria-label="Notifications"
            >
                <BellIcon class="w-5 h-5" />
            </button>

            <!-- User menu -->
            <div class="relative" ref="userMenuRef">
                <button
                    @click="userMenuOpen = !userMenuOpen"
                    class="flex items-center gap-2 sm:gap-2.5 px-2 sm:px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
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
    MagnifyingGlassIcon,
    SunIcon,
    MoonIcon,
} from '@heroicons/vue/24/outline';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

function openPalette() {
    // CommandPalette listens for a custom event so we don't depend on the
    // exact key combination handler being mounted.
    window.dispatchEvent(new CustomEvent('posmeister:palette:open'));
}

import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue';
import PwaStatusPill from '@/components/PwaStatusPill.vue';

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
