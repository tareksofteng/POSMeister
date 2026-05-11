<?php

namespace Database\Seeders;

use App\Modules\RolePermission\Services\RolePermissionService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(RolePermissionService $service): void
    {
        // Manager: broad operational access, no system administration
        $service->syncRole('manager', [
            'pos', 'sales', 'purchases', 'quotations',
            'products', 'inventory',
            'customers', 'suppliers',
            'reports',
            'hrm',
        ]);

        // Cashier: front-desk only
        $service->syncRole('cashier', [
            'pos', 'sales', 'customers',
        ]);

        $this->command->info('Role permissions seeded.');
    }
}
