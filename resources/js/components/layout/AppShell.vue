<template>
    <div class="flex h-full overflow-hidden bg-gray-50 dark:bg-slate-950">

        <!-- Sidebar -->
        <Sidebar :collapsed="sidebarCollapsed" />

        <!-- Content area -->
        <div class="flex flex-1 flex-col min-w-0 overflow-hidden">

            <!-- Top bar -->
            <Topbar @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed" />

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <RouterView />
            </main>

        </div>

        <!-- Global command palette — always mounted, opens on Ctrl/Cmd+K -->
        <CommandPalette />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Sidebar from './Sidebar.vue';
import Topbar  from './Topbar.vue';
import CommandPalette from '../CommandPalette.vue';
import { useSettingsStore } from '@/stores/settings';
import { useThemeStore } from '@/stores/theme';

const sidebarCollapsed = ref(false);

// Load app settings once on shell mount — available to all child components via store
onMounted(() => useSettingsStore().load());

// Activate theme store so the dark/light class lands on <html> at boot
useThemeStore();
</script>
