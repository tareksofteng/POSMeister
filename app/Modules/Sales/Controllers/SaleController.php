<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Resources\SaleResource;
use App\Modules\Sales\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $sales = $this->service->paginate($request->only([
            'search', 'status', 'date_from', 'date_to', 'branch_id', 'per_page',
        ]));

        return SaleResource::collection($sales);
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource(
            $this->service->find($sale->id)
        );
    }

    public function store(Request $request): SaleResource
    {
        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|integer|exists:products,id',
            'items.*.quantity'       => 'required|numeric|min:0.01',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.tax_rate'       => 'required|numeric|min:0|max:100',
            'sale_date'              => 'required|date',
            'cash_paid'              => 'nullable|numeric|min:0',
            'card_paid'              => 'nullable|numeric|min:0',
            'discount_amount'        => 'nullable|numeric|min:0',
            'freight_amount'         => 'nullable|numeric|min:0',
        ]);

        try {
            $sale = $this->service->store($request->all());
            return new SaleResource($sale);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function record(Request $request): JsonResponse
    {
        $sales = $this->service->record($request->only('date_from', 'date_to', 'customer_id', 'status', 'sale_type'));

        return response()->json([
            'data'    => SaleResource::collection($sales),
            'summary' => [
                'count'           => $sales->count(),
                'subtotal'        => round($sales->sum('subtotal'), 2),
                'vat_amount'      => round($sales->sum('vat_amount'), 2),
                'discount_amount' => round($sales->sum('discount_amount'), 2),
                'freight_amount'  => round($sales->sum('freight_amount'), 2),
                'grand_total'     => round($sales->sum('grand_total'), 2),
                'total_paid'      => round($sales->sum('total_paid'), 2),
                'due_amount'      => round($sales->sum('due_amount'), 2),
            ],
        ]);
    }

    public function cancel(Sale $sale): SaleResource
    {
        try {
            return new SaleResource($this->service->cancel($sale));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    // POS product search — returns extra fields (cost_price, stock, image) needed by cart
    public function posSearch(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->value());
        if (strlen($term) < 1) {
            return response()->json([]);
        }

        $user     = auth()->user();
        // $request->integer() returns 0 (not null) when absent — ?: null prevents
        // the null-coalescing chain from stopping at 0 and missing the branch fallback.
        $branchId = $user->branch_id
            ?? ($request->integer('branch_id') ?: null)
            ?? \App\Modules\Branch\Models\Branch::where('is_active', true)->value('id');

        return response()->json(
            $this->service->posProductSearch($term, $branchId)
        );
    }
}
