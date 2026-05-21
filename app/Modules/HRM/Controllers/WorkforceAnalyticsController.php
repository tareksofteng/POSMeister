<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Services\WorkforceAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkforceAnalyticsController extends Controller
{
    public function __construct(private readonly WorkforceAnalyticsService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'lookback_days' => 'nullable|integer|min:7|max:365',
        ]);
        return response()->json([
            'data' => $this->service->dashboard(
                $data['branch_id'] ?? null,
                $data['lookback_days'] ?? WorkforceAnalyticsService::DEFAULT_DAYS,
            ),
        ]);
    }

    public function branchEfficiency(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => 'nullable|date']);
        $from = $data['from'] ?? Carbon::today()->startOfMonth()->toDateString();
        return response()->json(['data' => $this->service->branchEfficiency($from)]);
    }

    public function utilisation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->utilisation($data['branch_id'] ?? null, $data['from'], $data['to']),
        ]);
    }
}
