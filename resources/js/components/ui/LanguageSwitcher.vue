<template>
    <div class="relative" ref="menuRef">
        <!-- Trigger button -->
        <button
            @click="open = !open"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors"
            :title="t('language.select')"
        >
            <span class="text-base leading-none">{{ currentConfig.flag }}</span>
            <span class="hidden sm:block font-medium text-xs">{{ currentConfig.label }}</span>
            <ChevronDownIcon class="w-3 h-3 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" />
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
                v-if="open"
                class="absolute right-0 top-full mt-1.5 w-44 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black/5 z-50 overflow-hidden"
            >
                <div class="py-1">
                    <button
                        v-for="(config, code) in SUPPORTED_LOCALES"
                        :key="code"
                        @click="select(code)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors',
                            locale === code
                                ? 'bg-indigo-50 text-indigo-700 font-semibold'
                                : 'text-gray-700 hover:bg-gray-50',
                        ]"
                    >
                        <span class="text-base">{{ config.flag }}</span>
                        <span class="flex-1 text-left">{{ config.label }}</span>
                        <CheckIcon v-if="locale === code" class="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                        <span v-if="config.dir === 'rtl'" class="text-xs text-gray-400 flex-shrink-0">RTL</span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { onClickOutside } from '@vueuse/core';
import { ChevronDownIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { useLocale } from '@/composables/useLocale';

const { t } = useI18n();
const { locale, currentConfig, SUPPORTED_LOCALES, setLocale } = useLocale();

const open   = ref(false);
const menuRef = ref(null);

onClickOutside(menuRef, () => { open.value = false; });

function select(code) {
    setLocale(code);
    open.value = false;
}
</script>
