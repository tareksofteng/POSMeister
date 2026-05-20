<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Services\InventoryAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InventoryAnalyticsController extends Controller
{
    public function __construct(private readonly InventoryAnalyticsService $service) {}

    public function valuation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->valuation($data['branch_id'] ?? null),
        ]);
    }

    public function profitability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->profitability($data['from'], $data['to'], $data['branch_id'] ?? null),
        ]);
    }

    public function movement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->movement($data['from'], $data['to'], $data['branch_id'] ?? null),
        ]);
    }
}
