<template>
    <div class="relative" ref="rootEl">

        <!-- ── Collapsed: show selected product ──────────────────────── -->
        <div v-if="selected && !isOpen"
            class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-gray-200 bg-white cursor-pointer hover:border-indigo-400 transition-colors group"
            :class="{ 'opacity-60 cursor-default pointer-events-none': disabled }" @click="openAndFocus">
            <!-- Thumbnail -->
            <div
                class="w-7 h-7 flex-shrink-0 rounded overflow-hidden border border-gray-100 bg-gray-50 flex items-center justify-center">
                <img v-if="selected.image_url" :src="selected.image_url" class="w-full h-full object-cover" alt="" />
                <PhotoIcon v-else class="w-4 h-4 text-gray-300" />
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-gray-900 truncate leading-none">{{ selected.name }}</p>
                <p class="text-[10px] text-gray-400 font-mono mt-0.5 truncate">{{ selected.sku }}</p>
            </div>
            <!-- Change button -->
            <button v-if="!disabled" type="button" @click.stop="clear"
                class="p-0.5 text-gray-300 hover:text-red-400 transition-colors flex-shrink-0 opacity-0 group-hover:opacity-100"
                tabindex="-1">
                <XMarkIcon class="w-3.5 h-3.5" />
            </button>
            <PencilSquareIcon v-if="!disabled"
                class="w-3.5 h-3.5 text-gray-300 flex-shrink-0 group-hover:text-indigo-400 transition-colors" />
        </div>

        <!-- ── Search input ────────────────────────────────────────────── -->
        <div v-else class="relative">
            <div class="absolute inset-y-0 left-2.5 flex items-center pointer-events-none">
                <MagnifyingGlassIcon v-if="!isLoading" class="w-3.5 h-3.5 text-gray-400" />
                <span v-else
                    class="w-3.5 h-3.5 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin block" />
            </div>
            <input ref="inputEl" v-model="query" type="text" :placeholder="placeholder" :disabled="disabled"
                class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white disabled:bg-gray-50 disabled:text-gray-400 transition-colors"
                :class="{ 'border-indigo-400 ring-1 ring-indigo-500': isOpen }" @input="onInput"
                @keydown.down.prevent="moveDown" @keydown.up.prevent="moveUp" @keydown.enter.prevent="selectHighlighted"
                @keydown.escape="close" @focus="onFocus" @blur="onBlur" autocomplete="off" />
        </div>

        <!-- ── Dropdown ────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="isOpen" :style="dropdownStyle"
                class="fixed z-[200] bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden"
                @mousedown.prevent>
                <!-- Results -->
                <ul v-if="results.length" class="py-1 max-h-72 overflow-y-auto overscroll-contain">
                    <li v-for="(item, idx) in results" :key="item.id" @mouseenter="highlighted = idx"
                        @mousedown.prevent="select(item)" :class="[
                            'flex items-center gap-3 px-3 py-2 cursor-pointer transition-colors',
                            highlighted === idx ? 'bg-indigo-50' : 'hover:bg-gray-50',
                        ]">
                        <!-- Product image -->
                        <div
                            class="w-9 h-9 flex-shrink-0 rounded-lg overflow-hidden border border-gray-100 bg-gray-50 flex items-center justify-center">
                            <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover"
                                alt="" />
                            <PhotoIcon v-else class="w-4 h-4 text-gray-200" />
                        </div>

                        <!-- Info block -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate leading-tight">{{ item.name }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span
                                    class="inline-flex items-center px-1.5 py-0 rounded text-[10px] font-mono font-medium bg-gray-100 text-gray-500">{{
                                    item.sku }}</span>
                                <span v-if="item.unit_symbol" class="text-[10px] text-gray-400">{{ item.unit_symbol
                                    }}</span>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-semibold text-indigo-700">{{ fmtPrice(item.cost_price) }}</p>
                            <p class="text-[10px] text-gray-400">{{ t('purchases.unitCost') }}</p>
                        </div>
                    </li>
                </ul>

                <!-- Empty: typed 2+ chars but no results -->
                <div v-else-if="query.length >= 2 && !isLoading" class="px-4 py-5 text-center">
                    <MagnifyingGlassIcon class="w-6 h-6 text-gray-200 mx-auto mb-1.5" />
                    <p class="text-xs font-medium text-gray-400">{{ t('common.noResults') }}</p>
                    <p class="text-[10px] text-gray-300 mt-0.5">{{ query }}</p>
                </div>

                <!-- Loading -->
                <div v-if="isLoading && !results.length" class="px-4 py-4 flex items-center justify-center gap-2">
                    <span
                        class="w-3.5 h-3.5 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" />
                    <p class="text-xs text-gray-400">{{ t('common.loading') }}…</p>
                </div>
            </div>
        </Teleport>

    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDebounce } from '@vueuse/core';
import { useSettingsStore } from '@/stores/settings';
import api from '@/services/api';
import { PhotoIcon, MagnifyingGlassIcon, XMarkIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

// ── Props / emits ─────────────────────────────────────────────────────────
const props = defineProps({
    modelValue: { type: [Number, String], default: null },  // product_id
    product: { type: Object, default: null },             // pre-loaded product object
    placeholder: { type: String, default: '— Search product —' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);

const { t } = useI18n();
const settingsStore = useSettingsStore();

function fmtPrice(value) {
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value ?? 0);
}

// ── State ─────────────────────────────────────────────────────────────────
const rootEl = ref(null);
const inputEl = ref(null);
const query = ref('');
const results = ref([]);
const defaultResults = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const highlighted = ref(0);

// The currently selected product object
const selected = ref(props.product ?? null);

// Sync selected when parent passes product prop (edit mode)
watch(() => props.product, (p) => { selected.value = p; }, { immediate: true });

// ── Dropdown positioning via Teleport ─────────────────────────────────────
const dropdownStyle = ref({});

function updatePosition() {

    if (!inputEl.value) return;
    const rect = inputEl.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const dropH = Math.min(320, spaceBelow - 8);

    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 340)}px`,
        maxHeight: `${dropH}px`,
    };

}

// ── Search logic ──────────────────────────────────────────────────────────
const debouncedQuery = useDebounce(query, 280);

async function loadDefaults() {
    if (defaultResults.value.length) {
        results.value = defaultResults.value;
        return;
    }
    isLoading.value = true;
    try {
        const { data } = await api.get('/products/search', { params: { q: '' } });
        defaultResults.value = Array.isArray(data) ? data : [];
        results.value = defaultResults.value;
        highlighted.value = 0;
    } catch {
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}

watch(debouncedQuery, async (val) => {
    if (val.length < 2) {
        results.value = defaultResults.value;
        highlighted.value = 0;
        return;
    }
    isLoading.value = true;
    try {
        const { data } = await api.get('/products/search', { params: { q: val } });
        results.value = Array.isArray(data) ? data : [];
        highlighted.value = 0;
    } catch {
        results.value = [];
    } finally {
        isLoading.value = false;
    }
});

// ── Open / close ──────────────────────────────────────────────────────────
function openAndFocus() {
    if (props.disabled) return;
    isOpen.value = true;
    query.value = '';
    nextTick(() => {
        updatePosition();
        inputEl.value?.focus();
        loadDefaults();
    });
}

function onFocus() {
    isOpen.value = true;
    updatePosition();
    loadDefaults();
}

function onBlur() {
    // Delay so mousedown on a result fires first
    setTimeout(() => { isOpen.value = false; }, 150);
}

function close() {
    isOpen.value = false;
    query.value = '';
}

function onInput() {
    isOpen.value = true;
    updatePosition();
}

// ── Keyboard navigation ───────────────────────────────────────────────────
function moveDown() {
    if (results.value.length) {
        highlighted.value = (highlighted.value + 1) % results.value.length;
    }
}

function moveUp() {
    if (results.value.length) {
        highlighted.value = (highlighted.value - 1 + results.value.length) % results.value.length;
    }
}

function selectHighlighted() {
    if (results.value[highlighted.value]) {
        select(results.value[highlighted.value]);
    }
}

// ── Select ────────────────────────────────────────────────────────────────
function select(product) {
    selected.value = product;
    query.value = '';
    isOpen.value = false;
    emit('update:modelValue', product.id);
    emit('select', product);
}

function clear() {
    selected.value = null;
    query.value = '';
    results.value = [];
    emit('update:modelValue', null);
    emit('select', null);
    nextTick(() => inputEl.value?.focus());
}

// Reposition on scroll/resize
function onScroll() { if (isOpen.value) updatePosition(); }
window.addEventListener('scroll', onScroll, true);
window.addEventListener('resize', onScroll);
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll, true);
    window.removeEventListener('resize', onScroll);
});
</script>
