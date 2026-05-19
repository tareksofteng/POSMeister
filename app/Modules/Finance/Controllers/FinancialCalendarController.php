<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Services\FinancialCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinancialCalendarController extends Controller
{
    public function __construct(private readonly FinancialCalendarService $service) {}

    public function month(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'      => 'required|integer|min:2000|max:2100',
            'month'     => 'required|integer|min:1|max:12',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->month($data['year'], $data['month'], $data['branch_id'] ?? null),
        ]);
    }
}
