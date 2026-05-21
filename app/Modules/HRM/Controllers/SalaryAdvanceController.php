<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\SalaryAdvance;
use App\Modules\HRM\Services\SalaryAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class SalaryAdvanceController extends Controller
{
    public function __construct(private readonly SalaryAdvanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = SalaryAdvance::query()
            ->with('employee:id,first_name,last_name,employee_id', 'branch:id,name')
            ->orderByDesc('id');

        if ($status = $request->input('status')) $q->where('status', $status);
        if ($employeeId = $request->input('employee_id')) $q->where('employee_id', $employeeId);

        if (Auth::user()?->role !== 'admin' && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'amount'      => 'required|numeric|gt:0',
            'reason'      => 'nullable|string|max:255',
            'granted_on'  => 'nullable|date',
            'branch_id'   => 'nullable|integer|exists:branches,id',
        ]);

        $advance = $this->service->grant(
            (int) $data['employee_id'],
            (float) $data['amount'],
            $data['reason'] ?? '',
            $data['branch_id'] ?? null,
            $data['granted_on'] ?? null,
        );
        return response()->json(['data' => $advance], 201);
    }

    public function cancel(SalaryAdvance $advance, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        return response()->json(['data' => $this->service->cancel($advance, $data['reason'])]);
    }

    public function outstandingForEmployee(int $employeeId): JsonResponse
    {
        return response()->json([
            'data' => [
                'employee_id'  => $employeeId,
                'outstanding'  => $this->service->outstandingTotal($employeeId),
            ],
        ]);
    }
}
