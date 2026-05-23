<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-5xl mx-auto">
        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('systemOps.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('systemOps.environment.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('systemOps.environment.subtitle') }}</p>
            </div>
            <button @click="load" :disabled="loading" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" /> {{ t('common.refresh') }}
            </button>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="3" />

        <template v-if="d">
            <section v-for="g in groups" :key="g.key" class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="card-title">{{ t('systemOps.environment.groups.' + g.key) }}</h3>
                    <span :class="['text-[10px] font-bold tracking-wider uppercase px-2 py-0.5 rounded-full',
                        d[g.key]?.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700']">
                        {{ d[g.key]?.ok ? t('systemOps.environment.ok') : t('systemOps.environment.fail') }}
                    </span>
                </div>
                <p class="text-sm text-slate-700 dark:text-slate-200">{{ d[g.key]?.message }}</p>

                <ul v-if="g.key === 'extensions'" class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
                    <li v-for="x in d.extensions.required" :key="x.name"
                        :class="['flex items-center gap-2 px-3 py-2 rounded-lg border text-xs',
                            x.loaded ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40']">
                        <span :class="['w-2 h-2 rounded-full', x.loaded ? 'bg-emerald-500' : 'bg-rose-500']" />
                        <span class="font-mono">{{ x.name }}</span>
                    </li>
                </ul>

                <ul v-if="g.key === 'storage'" class="divide-y divide-slate-100 dark:divide-slate-800 mt-3 text-sm">
                    <li v-for="p in d.storage.paths" :key="p.path" class="flex items-center gap-3 py-2">
                        <span :class="['w-2 h-2 rounded-full', p.writable ? 'bg-emerald-500' : 'bg-rose-500']" />
                        <span class="font-mono text-xs text-slate-700 dark:text-slate-200 flex-1">{{ p.path }}</span>
                        <span class="text-[10px] font-bold tracking-wider uppercase"
                              :class="p.writable ? 'text-emerald-700' : 'text-rose-700'">
                            {{ p.writable ? t('system.health.writable') : (p.exists ? t('system.health.notWritable') : t('system.health.missing')) }}
                        </span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemOpsService } from '@/services/systemOpsService';
import SkeletonLoader from '@/components/SkeletonLoader.vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const d = ref(null);
const loading = ref(false);

const groups = [
    { key: 'php' }, { key: 'extensions' }, { key: 'database' },
    { key: 'cache' }, { key: 'queue' }, { key: 'mail' },
    { key: 'storage' }, { key: 'security' }, { key: 'scheduler' },
];

async function load() {
    loading.value = true;
    try {
        const { data } = await systemOpsService.environment();
        d.value = data.data;
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider; }
.btn-soft   { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
</style>
