<?php

namespace App\Modules\Expense\Controllers;

use App\Modules\Expense\Services\ExpenseReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpenseReportsController extends Controller
{
    public function __construct(private readonly ExpenseReportService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;
        return response()->json(['data' => $this->service->dashboard($branchId)]);
    }

    public function categoryBreakdown(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json(['data' => $this->service->categoryBreakdown($data)]);
    }

    public function monthlyTrend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'      => 'nullable|integer|min:2000|max:2100',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        $year = $data['year'] ?? (int) date('Y');
        return response()->json(['data' => $this->service->monthlyTrend($year, $data['branch_id'] ?? null)]);
    }

    public function branchBreakdown(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);
        return response()->json(['data' => $this->service->branchBreakdown($data)]);
    }
}
