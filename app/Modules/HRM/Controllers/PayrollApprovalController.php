<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Payslip;
use App\Modules\HRM\Services\PayrollApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PayrollApprovalController extends Controller
{
    public function __construct(private readonly PayrollApprovalService $approvals) {}

    public function queue(Request $request): JsonResponse
    {
        $approval = $request->input('approval_status');
        $q = Payslip::query()
            ->with('employee:id,first_name,last_name,employee_id', 'branch:id,name')
            ->orderByDesc('id');

        if ($approval) $q->where('approval_status', $approval);
        if (Auth::user()?->role !== 'admin' && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function counts(): JsonResponse
    {
        $branch = Auth::user()?->role === 'admin' ? null : Auth::user()?->branch_id;
        return response()->json(['data' => $this->approvals->queueCounts($branch)]);
    }

    public function submit(Payslip $payslip, Request $request): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);
        return response()->json(['data' => $this->approvals->submit($payslip, $data['note'] ?? null)]);
    }

    public function approve(Payslip $payslip, Request $request): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);
        return response()->json(['data' => $this->approvals->approve($payslip, $data['note'] ?? null)]);
    }

    public function reject(Payslip $payslip, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        return response()->json(['data' => $this->approvals->reject($payslip, $data['reason'])]);
    }

    public function reopen(Payslip $payslip): JsonResponse
    {
        return response()->json(['data' => $this->approvals->reopen($payslip)]);
    }
}
