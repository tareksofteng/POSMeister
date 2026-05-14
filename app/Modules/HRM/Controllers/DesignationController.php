<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Designation;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Requests\StoreDesignationRequest;
use App\Modules\HRM\Resources\DesignationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Designation::with('department:id,name')
            ->withCount('employees');

        if ($request->filled('department_id')) {
            $q->where('department_id', $request->integer('department_id'));
        }

        return response()->json([
            'data' => DesignationResource::collection(
                $q->orderBy('hierarchy_level')->orderBy('title')->get()
            ),
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $q = Designation::active();
        if ($request->filled('department_id')) {
            $q->where('department_id', $request->integer('department_id'));
        }
        return response()->json([
            'data' => $q->orderBy('title')->get(['id', 'title', 'department_id', 'hierarchy_level']),
        ]);
    }

    public function store(StoreDesignationRequest $request): JsonResponse
    {
        $designation = Designation::create($request->validated());
        $designation->load('department:id,name');
        return response()->json(['data' => new DesignationResource($designation)], 201);
    }

    public function update(StoreDesignationRequest $request, Designation $designation): JsonResponse
    {
        $designation->update($request->validated());
        $designation->load('department:id,name');
        return response()->json(['data' => new DesignationResource($designation)]);
    }

    public function toggleStatus(Designation $designation): JsonResponse
    {
        $designation->update(['is_active' => ! $designation->is_active]);
        $designation->load('department:id,name');
        return response()->json(['data' => new DesignationResource($designation)]);
    }

    public function destroy(Designation $designation): JsonResponse
    {
        if (Employee::where('designation_id', $designation->id)->exists()) {
            return response()->json([
                'message' => 'Position kann nicht gelöscht werden, da ihr Mitarbeiter zugeordnet sind.',
            ], 422);
        }

        $designation->delete();
        return response()->json(null, 204);
    }
}
