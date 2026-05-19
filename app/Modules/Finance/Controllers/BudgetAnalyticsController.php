<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Models\Budget;
use App\Modules\Finance\Services\BudgetAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BudgetAnalyticsController extends Controller
{
    public function __construct(private readonly BudgetAnalyticsService $service) {}

    public function show(Budget $budget): JsonResponse
    {
        return response()->json(['data' => $this->service->analyze($budget)]);
    }
}
