<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Services\FinancialDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinancialDashboardController extends Controller
{
    public function __construct(private readonly FinancialDashboardService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json([
            'data' => $this->service->dashboard(
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['branch_id'] ?? null,
            ),
        ]);
    }

    public function salesTrend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'      => 'nullable|integer|min:2000|max:2100',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json([
            'data' => $this->service->salesTrend(
                $data['year'] ?? (int) date('Y'),
                $data['branch_id'] ?? null,
            ),
        ]);
    }

    public function profitAnalysis(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'      => 'nullable|integer|min:2000|max:2100',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json([
            'data' => $this->service->profitAnalysis(
                $data['year'] ?? (int) date('Y'),
                $data['branch_id'] ?? null,
            ),
        ]);
    }

    public function branchPerformance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $data['to']   ?? now()->endOfMonth()->toDateString();

        return response()->json([
            'data' => [
                'period' => ['from' => $from, 'to' => $to],
                'rows'   => $this->service->branchPerformance($from, $to),
            ],
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'limit'     => 'nullable|integer|min:1|max:50',
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $data['to']   ?? now()->endOfMonth()->toDateString();

        return response()->json([
            'data' => [
                'period' => ['from' => $from, 'to' => $to],
                'rows'   => $this->service->topProducts($from, $to, $data['branch_id'] ?? null, $data['limit'] ?? 10),
            ],
        ]);
    }

    public function topCustomers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'limit'     => 'nullable|integer|min:1|max:50',
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $data['to']   ?? now()->endOfMonth()->toDateString();

        return response()->json([
            'data' => [
                'period' => ['from' => $from, 'to' => $to],
                'rows'   => $this->service->topCustomers($from, $to, $data['branch_id'] ?? null, $data['limit'] ?? 10),
            ],
        ]);
    }

    public function expenseBreakdown(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $data['to']   ?? now()->endOfMonth()->toDateString();

        return response()->json([
            'data' => [
                'period' => ['from' => $from, 'to' => $to],
                'rows'   => $this->service->expenseBreakdown($from, $to, $data['branch_id'] ?? null),
            ],
        ]);
    }

    public function inventoryInsights(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json([
            'data' => $this->service->inventoryInsights($data['branch_id'] ?? null),
        ]);
    }
}
