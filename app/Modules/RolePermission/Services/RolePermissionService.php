<?php

namespace App\Modules\RolePermission\Services;

use App\Modules\RolePermission\Models\RolePermission;

class RolePermissionService
{
    /** All configurable module keys (admin always has all) */
    public const ALL_MODULES = [
        'pos', 'sales', 'purchases', 'quotations',
        'products', 'inventory',
        'customers', 'suppliers',
        'finance', 'employees',
        'reports',
        'branches', 'users',
    ];

    /** Permissions grouped by role — for the management UI */
    public function getGrouped(): array
    {
        $rows = RolePermission::whereIn('role', ['manager', 'cashier'])->get()->groupBy('role');

        return [
            'manager' => $rows->get('manager', collect())->pluck('module')->values()->all(),
            'cashier' => $rows->get('cashier', collect())->pluck('module')->values()->all(),
        ];
    }

    /** All module keys the given role may access */
    public function getForRole(string $role): array
    {
        if ($role === 'admin') {
            return self::ALL_MODULES;
        }

        return RolePermission::where('role', $role)
            ->pluck('module')
            ->all();
    }

    /** Replace the full permission set for a role */
    public function syncRole(string $role, array $modules): void
    {
        $valid = array_values(array_intersect($modules, self::ALL_MODULES));

        RolePermission::where('role', $role)->delete();

        foreach ($valid as $module) {
            RolePermission::create(['role' => $role, 'module' => $module]);
        }
    }
}
