<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Shift;
use App\Modules\HRM\Requests\StoreShiftRequest;
use App\Modules\HRM\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ShiftResource::collection(
                Shift::withCount('employees')
                    ->orderBy('start_time')
                    ->get()
            ),
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json([
            'data' => Shift::active()
                ->orderBy('start_time')
                ->get(['id', 'name', 'start_time', 'end_time']),
        ]);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = Shift::create($request->validated());
        return response()->json(['data' => new ShiftResource($shift)], 201);
    }

    public function update(StoreShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift->update($request->validated());
        return response()->json(['data' => new ShiftResource($shift->fresh())]);
    }

    public function toggleStatus(Shift $shift): JsonResponse
    {
        $shift->update(['is_active' => ! $shift->is_active]);
        return response()->json(['data' => new ShiftResource($shift->fresh())]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        if (Employee::where('shift_id', $shift->id)->exists()) {
            return response()->json([
                'message' => 'Schicht kann nicht gelöscht werden, da ihr Mitarbeiter zugeordnet sind.',
            ], 422);
        }

        $shift->delete();
        return response()->json(null, 204);
    }
}
