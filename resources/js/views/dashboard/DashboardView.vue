<template>
    <div class="p-6 lg:p-8 space-y-8">

        <!-- Page header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">
                Welcome back, <span class="font-medium text-gray-700">{{ auth.userName }}</span>.
                Here is what is happening today.
            </p>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

            <StatCard
                label="Today's Revenue"
                value="—"
                change=""
                :icon="BanknotesIcon"
                icon-color="bg-green-100 text-green-600"
            />
            <StatCard
                label="Sales Today"
                value="—"
                change=""
                :icon="ShoppingCartIcon"
                icon-color="bg-indigo-100 text-indigo-600"
            />
            <StatCard
                label="Purchases Today"
                value="—"
                change=""
                :icon="TruckIcon"
                icon-color="bg-amber-100 text-amber-600"
            />
            <StatCard
                label="Low Stock Items"
                value="—"
                change=""
                :icon="ExclamationTriangleIcon"
                icon-color="bg-red-100 text-red-600"
            />

        </div>

        <!-- Content grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent sales (spans 2 cols) -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Recent Sales</h2>
                    <span class="text-xs text-gray-400">Last 10 transactions</span>
                </div>
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <DocumentTextIcon class="w-10 h-10 text-gray-200 mb-3" />
                    <p class="text-sm font-medium text-gray-400">No sales data yet</p>
                    <p class="text-xs text-gray-300 mt-1">Sales will appear here once the module is active.</p>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="p-4 space-y-2">
                    <QuickAction label="New Sale"     description="Open POS terminal"  :icon="ShoppingCartIcon"  color="indigo" />
                    <QuickAction label="Add Product"  description="Add to catalogue"    :icon="TagIcon"           color="violet" />
                    <QuickAction label="New Purchase" description="Record a purchase"   :icon="TruckIcon"         color="amber"  />
                    <QuickAction label="Add Customer" description="Register customer"   :icon="UserPlusIcon"      color="green"  />
                </div>
            </div>

        </div>

        <!-- Low stock alert placeholder -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-6 py-4 flex items-start gap-4">
            <ExclamationTriangleIcon class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-amber-800">Inventory module not yet active</p>
                <p class="text-xs text-amber-600 mt-0.5">
                    Low stock alerts will appear here once the Product and Inventory modules are implemented.
                </p>
            </div>
        </div>

    </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth';
import {
    BanknotesIcon,
    ShoppingCartIcon,
    TruckIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    TagIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';

import StatCard    from './StatCard.vue';
import QuickAction from './QuickAction.vue';

const auth = useAuthStore();
</script>
