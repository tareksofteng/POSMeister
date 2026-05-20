<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Services\SupplierAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SupplierAnalyticsController extends Controller
{
    public function __construct(private readonly SupplierAnalyticsService $service) {}

    public function leaderboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);
        return response()->json([
            'data' => $this->service->leaderboard($data['from'] ?? null, $data['to'] ?? null),
        ]);
    }

    public function show(int $supplierId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);
        return response()->json([
            'data' => $this->service->show($supplierId, $data['from'] ?? null, $data['to'] ?? null),
        ]);
    }
}
