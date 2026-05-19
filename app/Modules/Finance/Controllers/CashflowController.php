<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Services\CashflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CashflowController extends Controller
{
    public function __construct(private readonly CashflowService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->dashboard($data['from'] ?? null, $data['to'] ?? null, $data['branch_id'] ?? null),
        ]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lookback_months' => 'nullable|integer|min:1|max:12',
            'branch_id'       => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->forecast($data['lookback_months'] ?? 3, $data['branch_id'] ?? null),
        ]);
    }
}
