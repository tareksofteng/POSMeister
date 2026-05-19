<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Services\FinancialAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinancialAlertController extends Controller
{
    public function __construct(private readonly FinancialAlertService $service) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;
        return response()->json(['data' => $this->service->active($branchId)]);
    }
}
