<?php

namespace App\Modules\RolePermission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RolePermission\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function __construct(private RolePermissionService $service) {}

    /**
     * GET /api/role-permissions
     * Returns permissions grouped by role + the full module list.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data'    => $this->service->getGrouped(),
            'modules' => RolePermissionService::ALL_MODULES,
        ]);
    }

    /**
     * PUT /api/role-permissions/{role}
     * Sync module access for a given configurable role (manager | cashier).
     */
    public function update(Request $request, string $role): JsonResponse
    {
        if (! in_array($role, ['manager', 'cashier'], true)) {
            return response()->json(['message' => 'Invalid role. Only manager and cashier are configurable.'], 422);
        }

        $validated = $request->validate([
            'modules'   => ['required', 'array'],
            'modules.*' => ['string'],
        ]);

        $this->service->syncRole($role, $validated['modules']);

        return response()->json([
            'message'     => "Permissions for {$role} updated successfully.",
            'permissions' => $this->service->getForRole($role),
        ]);
    }
}
