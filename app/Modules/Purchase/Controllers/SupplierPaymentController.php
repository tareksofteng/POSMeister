<?php

namespace App\Modules\Purchase\Controllers;

use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SupplierPaymentController extends Controller
{
    // ── Global list with filters ──────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $q = SupplierPayment::with(['supplier:id,name,code', 'creator:id,name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if (!empty($request->date_from)) {
            $q->whereDate('payment_date', '>=', $request->date_from);
        }
        if (!empty($request->date_to)) {
            $q->whereDate('payment_date', '<=', $request->date_to);
        }
        if (!empty($request->supplier_id)) {
            $q->where('supplier_id', $request->supplier_id);
        }
        if (!empty($request->payment_method)) {
            $q->where('payment_method', $request->payment_method);
        }

        $payments = $q->get();

        return response()->json([
            'data'    => $payments,
            'summary' => [
                'count'        => $payments->count(),
                'total_amount' => (float) $payments->sum('amount'),
            ],
        ]);
    }

    // ── Store a payment ───────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,other',
            'payment_date'   => 'required|date',
            'reference'      => 'nullable|string|max:100',
            'note'           => 'nullable|string',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);

        $payment = $supplier->payments()->create([
            'branch_id'      => auth()->user()->branch_id,
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'payment_date'   => $data['payment_date'],
            'reference'      => $data['reference'] ?? null,
            'note'           => $data['note'] ?? null,
            'created_by'     => auth()->id(),
        ]);

        $payment->load('supplier:id,name,code', 'creator:id,name');

        return response()->json(['data' => $payment], 201);
    }

    // ── Show single payment ───────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $payment = SupplierPayment::with([
            'supplier:id,name,code,phone,address',
            'creator:id,name',
            'branch:id,name',
        ])->findOrFail($id);

        return response()->json(['data' => $payment]);
    }
}
