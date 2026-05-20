<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\LoyaltyTransaction;
use App\Modules\CRM\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function summary(int $customerId): JsonResponse
    {
        return response()->json(['data' => $this->loyalty->summary($customerId)]);
    }

    public function transactions(int $customerId, Request $request): JsonResponse
    {
        $q = LoyaltyTransaction::query()
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

    public function adjust(int $customerId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'points' => 'required|numeric|not_in:0',
            'note'   => 'required|string|max:255',
        ]);

        $txn = $this->loyalty->adjust(
            $customerId,
            (float) $data['points'],
            $data['note'],
            Auth::user()?->branch_id,
        );

        return response()->json(['data' => $txn], 201);
    }

    public function redeem(int $customerId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'points'        => 'required|integer|min:1',
            'note'          => 'nullable|string|max:255',
            'sale_id'       => 'nullable|integer|exists:sales,id',
            'sale_number'   => 'nullable|string|max:64',
        ]);

        $result = $this->loyalty->redeem(
            $customerId,
            (int) $data['points'],
            $data['note'] ?? '',
            Auth::user()?->branch_id,
            $data['sale_id'] ?? null,
            $data['sale_number'] ?? null,
        );

        return response()->json(['data' => $result], 201);
    }
}
