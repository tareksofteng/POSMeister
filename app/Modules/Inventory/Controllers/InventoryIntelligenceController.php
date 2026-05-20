<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Services\InventoryIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InventoryIntelligenceController extends Controller
{
    public function __construct(private readonly InventoryIntelligenceService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'lookback_days' => 'nullable|integer|min:7|max:365',
        ]);

        return response()->json([
            'data' => $this->service->dashboard(
                $data['branch_id'] ?? null,
                $data['lookback_days'] ?? InventoryIntelligenceService::LOOKBACK_DAYS,
            ),
        ]);
    }

    public function movement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'      => 'nullable|integer|exists:branches,id',
            'lookback_days'  => 'nullable|integer|min:7|max:365',
            'classification' => 'nullable|in:fast_moving,medium_moving,slow_moving,dead_stock',
        ]);

        $rows = $this->service->movementClassification(
            $data['branch_id'] ?? null,
            $data['lookback_days'] ?? InventoryIntelligenceService::LOOKBACK_DAYS,
        );

        if (!empty($data['classification'])) {
            $rows = array_values(array_filter($rows, fn($r) => $r['classification'] === $data['classification']));
        }

        return response()->json(['data' => $rows]);
    }

    public function deadStock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'dead_days' => 'nullable|integer|min:30|max:365',
        ]);

        return response()->json([
            'data' => $this->service->deadStock(
                $data['branch_id'] ?? null,
                $data['dead_days'] ?? InventoryIntelligenceService::DEAD_STOCK_DAYS,
            ),
        ]);
    }

    public function aging(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->aging($data['branch_id'] ?? null),
        ]);
    }

    public function branchHealth(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->branchHealth(),
        ]);
    }
}
