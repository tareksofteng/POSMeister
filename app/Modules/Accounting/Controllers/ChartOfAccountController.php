<?php

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = ChartOfAccount::query()
            ->orderBy('account_code');

        if ($request->boolean('active_only', true)) {
            $q->where('is_active', true);
        }
        if ($type = $request->string('type')->toString()) {
            $q->where('account_type', $type);
        }
        if ($search = trim((string) $request->input('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('account_code', 'like', "%{$search}%")
                   ->orWhere('account_name', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_code'       => 'required|string|max:20|unique:chart_of_accounts,account_code',
            'account_name'       => 'required|string|max:150',
            'account_type'       => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance'     => 'required|in:debit,credit',
            'parent_id'          => 'nullable|integer|exists:chart_of_accounts,id',
            'branch_id'          => 'nullable|integer|exists:branches,id',
            'allow_manual_entry' => 'boolean',
            'is_active'          => 'boolean',
            'description'        => 'nullable|string',
        ]);

        $account = ChartOfAccount::create($data);
        return response()->json(['data' => $account], 201);
    }

    public function show(ChartOfAccount $account): JsonResponse
    {
        return response()->json(['data' => $account->load('parent', 'children')]);
    }

    public function update(Request $request, ChartOfAccount $account): JsonResponse
    {
        if ($account->is_system && $request->filled('account_code')
            && $request->input('account_code') !== $account->account_code) {
            abort(422, 'System accounts cannot be renumbered.');
        }

        $data = $request->validate([
            'account_code'       => 'sometimes|string|max:20|unique:chart_of_accounts,account_code,' . $account->id,
            'account_name'       => 'sometimes|string|max:150',
            'account_type'       => 'sometimes|in:asset,liability,equity,revenue,expense',
            'normal_balance'     => 'sometimes|in:debit,credit',
            'parent_id'          => 'nullable|integer|exists:chart_of_accounts,id',
            'branch_id'          => 'nullable|integer|exists:branches,id',
            'allow_manual_entry' => 'boolean',
            'is_active'          => 'boolean',
            'description'        => 'nullable|string',
        ]);

        $account->update($data);
        return response()->json(['data' => $account->fresh()]);
    }

    public function destroy(ChartOfAccount $account): JsonResponse
    {
        if ($account->is_system) {
            abort(422, 'System accounts cannot be deleted.');
        }
        if ($account->lines()->exists()) {
            abort(422, 'Account has journal entries and cannot be deleted. Deactivate it instead.');
        }
        $account->delete();
        return response()->json(['data' => ['ok' => true]]);
    }
}
