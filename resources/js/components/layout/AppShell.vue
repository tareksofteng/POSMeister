<template>
    <div class="flex h-full overflow-hidden bg-gray-50">

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
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Sidebar from './Sidebar.vue';
import Topbar  from './Topbar.vue';

const sidebarCollapsed = ref(false);
</script>
