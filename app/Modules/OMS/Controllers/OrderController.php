<?php

namespace App\Modules\OMS\Controllers;

use App\Modules\Branch\Services\BranchContextService;
use App\Modules\OMS\Models\Order;
use App\Modules\OMS\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $q = Order::query()
            ->with('items', 'customer:id,name,phone', 'shipment:id,order_id,status,tracking_number')
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        if ($status = $request->input('status'))     $q->where('status', $status);
        if ($source = $request->input('source'))     $q->where('source', $source);
        if ($from = $request->input('from'))         $q->whereDate('placed_at', '>=', $from);
        if ($to   = $request->input('to'))           $q->whereDate('placed_at', '<=', $to);
        if ($search = trim((string) $request->input('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('order_number', 'like', "%{$search}%")
                   ->orWhere('customer_phone', 'like', "%{$search}%")
                   ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // Workspace context — admin in Chattogram must NOT see Dhaka
        // orders. The legacy "admin sees everything" branch was the leak.
        $q = app(BranchContextService::class)->scopeQuery($q);

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);
        // Workspace context overrides when no explicit branch is requested.
        $ctx = app(BranchContextService::class);
        $branchId = $data['branch_id'] ?? ($ctx->isMainBranch() ? null : $ctx->current());
        return response()->json(['data' => $this->orders->dashboard($branchId)]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load('items.product:id,name,sku', 'logs.author:id,name', 'shipment.courier', 'customer'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'    => 'nullable|integer|exists:customers,id',
            'branch_id'      => 'nullable|integer|exists:branches,id',
            'source'         => 'nullable|in:pos,web,manual,ecommerce',
            'payment_method' => 'nullable|in:cod,cash,card,bank,wallet,other',
            'payment_status' => 'nullable|in:unpaid,partial,paid,refunded',
            'discount'       => 'nullable|numeric|min:0',
            'shipping_cost'  => 'nullable|numeric|min:0',
            'paid_amount'    => 'nullable|numeric|min:0',
            'customer_name'  => 'nullable|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'delivery_address' => 'nullable|string',
            'delivery_city'    => 'nullable|string|max:100',
            'delivery_zip'     => 'nullable|string|max:20',
            'notes'            => 'nullable|string',
            'external_reference' => 'nullable|string|max:100',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.quantity'     => 'required|numeric|gt:0',
            'items.*.unit_price'   => 'required|numeric|gte:0',
            'items.*.cost_price'   => 'nullable|numeric|gte:0',
            'items.*.tax_rate'     => 'nullable|numeric|gte:0',
        ]);

        $order = $this->orders->create($data, $data['items']);
        return response()->json(['data' => $order], 201);
    }

    public function transition(Order $order, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:confirmed,packed,shipped,delivered,cancelled,returned',
            'note'   => 'nullable|string|max:255',
        ]);
        return response()->json(['data' => $this->orders->transition($order, $data['status'], $data['note'] ?? null)]);
    }

    public function fulfilPartial(Order $order, Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty'     => 'required|numeric|gt:0',
        ]);
        return response()->json(['data' => $this->orders->fulfilPartial($order, $data['items'])]);
    }

    public function markPaid(Order $order, Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'note'   => 'nullable|string|max:255',
        ]);
        return response()->json(['data' => $this->orders->markPaid($order, (float) $data['amount'], $data['note'] ?? null)]);
    }
}
