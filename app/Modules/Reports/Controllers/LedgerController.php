<?php

namespace App\Modules\Reports\Controllers;

use App\Modules\Reports\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function customer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json(
            $this->ledger->customer($data['customer_id'], $data['from'] ?? null, $data['to'] ?? null)
        );
    }

    public function supplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json(
            $this->ledger->supplier($data['supplier_id'], $data['from'] ?? null, $data['to'] ?? null)
        );
    }

    public function product(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date|after_or_equal:from',
            'branch_id'  => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json(
            $this->ledger->product(
                $data['product_id'],
                $data['from']      ?? null,
                $data['to']        ?? null,
                $data['branch_id'] ?? null,
            )
        );
    }
}
