<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Services\HrmReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HrmReportsController extends Controller
{
    public function __construct(private readonly HrmReportsService $service) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->service->dashboard()]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json(['data' => $this->service->attendance($data)]);
    }

    public function payroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_id' => 'nullable|integer|exists:payroll_periods,id',
        ]);

        return response()->json(['data' => $this->service->payroll($data['period_id'] ?? null)]);
    }
}
