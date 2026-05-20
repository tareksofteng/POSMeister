<?php

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\AccountingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BankAccountController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $q = BankAccount::query()->with('account:id,account_code,account_name', 'branch:id,name');
        if ($request->boolean('active_only', true)) $q->where('is_active', true);
        if ($branchId = $request->input('branch_id')) $q->where('branch_id', $branchId);

        $rows = $q->orderBy('name')->get();
        $asOf = $request->input('as_of', now()->toDateString());

        $rows->each(function ($r) use ($asOf) {
            $code = $r->account?->account_code ?? '1100';
            $r->current_balance = round(
                (float) $r->opening_balance
                    + $this->reports->accountBalance($code, $asOf, $r->branch_id),
                2,
            );
        });

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'bank_name'       => 'nullable|string|max:120',
            'account_number'  => 'nullable|string|max:64',
            'iban'            => 'nullable|string|max:34',
            'bic'             => 'nullable|string|max:11',
            'currency'        => 'nullable|string|size:3',
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'coa_account_id'  => 'required|integer|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'is_active'       => 'boolean',
            'notes'           => 'nullable|string',
        ]);

        $bank = BankAccount::create($data);
        return response()->json(['data' => $bank], 201);
    }

    public function update(Request $request, BankAccount $bank): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:120',
            'bank_name'       => 'nullable|string|max:120',
            'account_number'  => 'nullable|string|max:64',
            'iban'            => 'nullable|string|max:34',
            'bic'             => 'nullable|string|max:11',
            'currency'        => 'nullable|string|size:3',
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'coa_account_id'  => 'sometimes|integer|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'is_active'       => 'boolean',
            'notes'           => 'nullable|string',
        ]);

        $bank->update($data);
        return response()->json(['data' => $bank->fresh()]);
    }

    public function destroy(BankAccount $bank): JsonResponse
    {
        $bank->delete();
        return response()->json(['data' => ['ok' => true]]);
    }
}
