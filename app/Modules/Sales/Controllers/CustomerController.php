<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    // ── List / Search ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $customers = $this->service->paginate($request->only(['search', 'is_active', 'per_page']));
        return response()->json($customers);
    }

    public function all(): JsonResponse
    {
        return response()->json(['data' => $this->service->all()]);
    }

    // ── Single (with ledger summary) ──────────────────────────────────────

    public function show(Customer $customer): JsonResponse
    {
        $c = $this->service->find($customer->id);

        $saleDues  = (float) ($c->total_due_raw ?? 0);
        $paidDues  = (float) ($c->total_paid_due ?? 0);
        $currentDue = max(0, $saleDues - $paidDues);

        return response()->json([
            'data' => array_merge($c->toArray(), [
                'total_sales_amount' => (float) ($c->total_sales_amount ?? 0),
                'total_sales_count'  => (int)   ($c->total_sales_count ?? 0),
                'current_due'        => $currentDue,
            ]),
        ]);
    }

    // ── Due Report ────────────────────────────────────────────────────────

    public function dueReport(Request $request): JsonResponse
    {
        $rows = $this->service->dueReport(
            $request->only('customer_id')
        );

        $withDue = $rows->filter(fn($r) => $r['due_amount'] > 0);

        return response()->json([
            'data'    => $rows->values(),
            'summary' => [
                'total_customers'     => $rows->count(),
                'customers_with_due'  => $withDue->count(),
                'total_bill'          => round($rows->sum('bill_amount'),    2),
                'total_paid'          => round($rows->sum('total_paid'),     2),
                'total_due'           => round($rows->sum('due_amount'),     2),
            ],
        ]);
    }

    // ── Payments ──────────────────────────────────────────────────────────

    public function payments(Customer $customer): JsonResponse
    {
        $payments     = $this->service->getPayments($customer);
        $recentSales  = $this->service->getRecentSales($customer);

        $saleDues  = (float) $customer->sales()->where('status', 'active')->sum('due_amount');
        $paidDues  = (float) $customer->payments()->sum('amount');

        return response()->json([
            'current_due'  => max(0, $saleDues - $paidDues),
            'payments'     => $payments,
            'recent_sales' => $recentSales,
        ]);
    }

    public function storePayment(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,other',
            'payment_date'   => 'required|date',
            'reference'      => 'nullable|string|max:100',
            'note'           => 'nullable|string',
        ]);

        $payment = $this->service->storePayment($customer, $request->only([
            'amount', 'payment_method', 'payment_date', 'reference', 'note',
        ]));

        return response()->json(['data' => $payment], 201);
    }

    // ── Create / Update ───────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:150',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:150',
            'address'       => 'nullable|string',
            'customer_type' => 'nullable|in:retail,wholesale',
            'credit_limit'  => 'nullable|numeric|min:0',
        ]);

        $customer = $this->service->store($request->only([
            'name', 'phone', 'email', 'address', 'customer_type', 'credit_limit',
        ]));

        return response()->json(['data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'name'          => 'sometimes|required|string|max:150',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:150',
            'address'       => 'nullable|string',
            'customer_type' => 'nullable|in:retail,wholesale',
            'credit_limit'  => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]);

        $updated = $this->service->update($customer, $request->only([
            'name', 'phone', 'email', 'address', 'customer_type', 'credit_limit', 'is_active',
        ]));

        return response()->json(['data' => $updated]);
    }
}
