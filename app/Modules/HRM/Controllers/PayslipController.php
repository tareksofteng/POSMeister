<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Payslip;
use App\Modules\HRM\Resources\PayslipResource;
use App\Modules\HRM\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PayslipController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PayslipResource::collection(
            $this->service->paginatePayslips($request->only([
                'payroll_period_id', 'status', 'branch_id', 'employee_id', 'per_page',
            ]))
        );
    }

    public function show(Payslip $payslip): PayslipResource
    {
        return new PayslipResource($this->service->findPayslip($payslip->id));
    }

    public function update(Request $request, Payslip $payslip): PayslipResource
    {
        $data = $request->validate([
            'basic_salary' => ['sometimes', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
        ]);

        return new PayslipResource($this->service->updatePayslip($payslip, $data));
    }

    public function addItem(Request $request, Payslip $payslip): PayslipResource
    {
        $data = $request->validate([
            'type'   => ['required', 'in:allowance,bonus,overtime,deduction,tax'],
            'name'   => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes'  => ['nullable', 'string', 'max:255'],
        ]);

        return new PayslipResource($this->service->addItem($payslip, $data));
    }

    public function removeItem(Payslip $payslip, int $itemId): PayslipResource
    {
        return new PayslipResource($this->service->removeItem($payslip, $itemId));
    }

    public function pay(Request $request, Payslip $payslip): PayslipResource
    {
        $data = $request->validate([
            'paid_amount'       => ['nullable', 'numeric', 'min:0'],
            'payment_date'      => ['nullable', 'date'],
            'payment_method'    => ['nullable', 'in:cash,bank_transfer,card,other'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        return new PayslipResource($this->service->pay($payslip, $data));
    }

    public function destroy(Payslip $payslip): JsonResponse
    {
        try {
            $this->service->destroyPayslip($payslip);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }
}
