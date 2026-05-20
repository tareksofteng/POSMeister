<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\CustomerWallet;
use App\Modules\CRM\Models\WalletTransaction;
use App\Modules\CRM\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function summary(int $customerId): JsonResponse
    {
        return response()->json(['data' => $this->wallets->summary($customerId)]);
    }

    public function settings(int $customerId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'allow_negative' => 'boolean',
            'currency'       => 'string|size:3',
        ]);
        $wallet = $this->wallets->wallet($customerId);
        $wallet->update($data);
        return response()->json(['data' => $wallet->fresh()]);
    }

    public function transactions(int $customerId, Request $request): JsonResponse
    {
        $q = WalletTransaction::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('id');

        if ($type = $request->input('type'))   $q->where('type', $type);
        if ($from = $request->input('from'))   $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->input('to'))     $q->whereDate('created_at', '<=', $to);

        if (Auth::user()?->role !== 'admin' && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function credit(int $customerId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'type'   => 'required|in:credit,refund,cashback,deposit',
            'note'   => 'required|string|max:255',
            'reference_type'   => 'nullable|string|max:32',
            'reference_id'     => 'nullable|integer',
            'reference_number' => 'nullable|string|max:64',
        ]);

        $txn = $this->wallets->credit(
            $customerId,
            (float) $data['amount'],
            $data['type'],
            $data['note'],
            Auth::user()?->branch_id,
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['reference_number'] ?? null,
        );

        return response()->json(['data' => $txn], 201);
    }

    public function debit(int $customerId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'note'   => 'required|string|max:255',
            'reference_type'   => 'nullable|string|max:32',
            'reference_id'     => 'nullable|integer',
            'reference_number' => 'nullable|string|max:64',
        ]);

        $txn = $this->wallets->debit(
            $customerId,
            (float) $data['amount'],
            $data['note'],
            Auth::user()?->branch_id,
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['reference_number'] ?? null,
        );

        return response()->json(['data' => $txn], 201);
    }

    public function adjust(int $customerId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'note'   => 'required|string|max:255',
        ]);

        $txn = $this->wallets->adjust(
            $customerId,
            (float) $data['amount'],
            $data['note'],
            Auth::user()?->branch_id,
        );

        return response()->json(['data' => $txn], 201);
    }

    public function recentAll(Request $request): JsonResponse
    {
        $q = WalletTransaction::query()
            ->with('customer:id,name,phone')
            ->orderByDesc('id');

        if (Auth::user()?->role !== 'admin' && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }
}
