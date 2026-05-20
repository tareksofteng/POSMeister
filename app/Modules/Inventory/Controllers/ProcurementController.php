<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Services\InventoryRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProcurementController extends Controller
{
    public function __construct(private readonly InventoryRecommendationService $service) {}

    public function suggestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'velocity_days' => 'nullable|integer|min:7|max:180',
            'safety_days'   => 'nullable|integer|min:0|max:60',
            'urgent_only'   => 'nullable|boolean',
        ]);

        return response()->json([
            'data' => $this->service->suggestions(
                $data['branch_id'] ?? null,
                [
                    'velocity_days' => $data['velocity_days'] ?? InventoryRecommendationService::VELOCITY_DAYS,
                    'safety_days'   => $data['safety_days']   ?? InventoryRecommendationService::SAFETY_DAYS,
                    'urgent_only'   => (bool) ($data['urgent_only'] ?? false),
                ],
            ),
        ]);
    }

    public function suggestionsBySupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'velocity_days' => 'nullable|integer|min:7|max:180',
            'safety_days'   => 'nullable|integer|min:0|max:60',
            'urgent_only'   => 'nullable|boolean',
        ]);

        return response()->json([
            'data' => $this->service->suggestionsBySupplier(
                $data['branch_id'] ?? null,
                [
                    'velocity_days' => $data['velocity_days'] ?? InventoryRecommendationService::VELOCITY_DAYS,
                    'safety_days'   => $data['safety_days']   ?? InventoryRecommendationService::SAFETY_DAYS,
                    'urgent_only'   => (bool) ($data['urgent_only'] ?? false),
                ],
            ),
        ]);
    }
}
