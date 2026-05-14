<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Department;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Requests\StoreDepartmentRequest;
use App\Modules\HRM\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => DepartmentResource::collection(
                Department::withCount(['designations', 'employees'])
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json([
            'data' => Department::active()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());
        return response()->json(['data' => new DepartmentResource($department)], 201);
    }

    public function update(StoreDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());
        return response()->json(['data' => new DepartmentResource($department->fresh())]);
    }

    public function toggleStatus(Department $department): JsonResponse
    {
        $department->update(['is_active' => ! $department->is_active]);
        return response()->json(['data' => new DepartmentResource($department->fresh())]);
    }

    public function destroy(Department $department): JsonResponse
    {
        if (Employee::where('department_id', $department->id)->exists()) {
            return response()->json([
                'message' => 'Abteilung kann nicht gelöscht werden, da ihr Mitarbeiter zugeordnet sind.',
            ], 422);
        }

        $department->delete();
        return response()->json(null, 204);
    }
}
