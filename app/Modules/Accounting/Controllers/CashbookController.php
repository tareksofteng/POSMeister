<?php

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\Cashbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CashbookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Cashbook::query()->with('account:id,account_code,account_name', 'branch:id,name');
        if ($request->boolean('active_only', true)) $q->where('is_active', true);
        if ($branchId = $request->input('branch_id')) $q->where('branch_id', $branchId);

        return response()->json(['data' => $q->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'coa_account_id'  => 'required|integer|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'is_active'       => 'boolean',
        ]);

        $cb = Cashbook::create($data);
        return response()->json(['data' => $cb], 201);
    }

    public function update(Request $request, Cashbook $cashbook): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:120',
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'coa_account_id'  => 'sometimes|integer|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'is_active'       => 'boolean',
        ]);
        $cashbook->update($data);
        return response()->json(['data' => $cashbook->fresh()]);
    }

    public function destroy(Cashbook $cashbook): JsonResponse
    {
        $cashbook->delete();
        return response()->json(['data' => ['ok' => true]]);
    }
}
