<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\PayrollPeriod;
use App\Modules\HRM\Requests\StorePayrollPeriodRequest;
use App\Modules\HRM\Resources\PayrollPeriodResource;
use App\Modules\HRM\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PayrollPeriodController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PayrollPeriodResource::collection(
            $this->service->paginatePeriods($request->only(['status', 'year', 'per_page']))
        );
    }

    public function show(PayrollPeriod $period): PayrollPeriodResource
    {
        return new PayrollPeriodResource($this->service->findPeriod($period->id));
    }

    public function store(StorePayrollPeriodRequest $request): PayrollPeriodResource
    {
        return new PayrollPeriodResource($this->service->createPeriod($request->validated()));
    }

    public function update(StorePayrollPeriodRequest $request, PayrollPeriod $period): PayrollPeriodResource
    {
        try {
            return new PayrollPeriodResource($this->service->updatePeriod($period, $request->validated()));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function destroy(PayrollPeriod $period): JsonResponse
    {
        try {
            $this->service->deletePeriod($period);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }

    public function generate(PayrollPeriod $period): JsonResponse
    {
        try {
            $result = $this->service->generatePayslips($period);
            return response()->json([
                'data'    => $result,
                'message' => "Erstellt: {$result['created']}, Übersprungen: {$result['skipped']}",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function finalize(PayrollPeriod $period): PayrollPeriodResource
    {
        try {
            return new PayrollPeriodResource($this->service->finalizePeriod($period));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
